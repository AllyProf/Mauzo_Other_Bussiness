<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Services\CashierPaymentGuard;
use App\Services\SalePaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtController extends ApiController
{
  use UsesWebBranchContext;

  public function index(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    $branchFilterId = $this->bootWebBranchContext($request);
    $filter = $this->branchBusinessFilterContext($request);
    $businessId = $this->apiBusinessId();
    $business = $this->apiBusiness();
    $today = now()->toDateString();
    $activeBusinessType = $filter['activeBusinessType'];

    $baseQuery = $this->debtSalesQuery($businessId, $branchFilterId)
      ->whereNotIn('payment_status', ['paid', 'cancelled'])
      ->whereColumn('total_amount', '>', 'amount_paid');

    if ($activeBusinessType) {
      $baseQuery->whereHas('items.item.category', fn ($q) => $q->where('source_business_type_key', $activeBusinessType));
    }

    $allOutstanding = (clone $baseQuery)->get();

    $stats = [
      'total_outstanding' => (float) $allOutstanding->sum(fn (Sale $s) => $this->balance($s)),
      'open_accounts' => $allOutstanding->count(),
      'overdue_count' => $allOutstanding->filter(fn (Sale $s) => $s->due_date && $s->due_date->toDateString() < $today)->count(),
      'customers' => $allOutstanding->pluck('customer_name')->filter()->unique()->count(),
    ];

    $query = (clone $baseQuery)->with(['user:id,name', 'customer:id,name,phone', 'items.item', 'items.service']);

    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('reference_no', 'like', "%{$search}%")
          ->orWhere('customer_name', 'like', "%{$search}%")
          ->orWhere('customer_phone', 'like', "%{$search}%");
      });
    }

    if (in_array($request->status, ['debt', 'partial', 'pending'], true)) {
      $query->where('payment_status', $request->status);
    }

    if ($request->get('filter') === 'overdue') {
      $query->whereNotNull('due_date')->whereDate('due_date', '<', $today);
    }

    $debts = $query->latest()->paginate($this->perPage($request, 15));

    $customerSummaries = $allOutstanding
      ->groupBy(fn (Sale $s) => $s->customer_name ?: 'Walk-in / Unnamed')
      ->map(fn ($sales, $name) => [
        'name' => $name,
        'phone' => $sales->first()->customer_phone,
        'orders' => $sales->count(),
        'balance' => (float) $sales->sum(fn (Sale $s) => $this->balance($s)),
      ])
      ->sortByDesc('balance')
      ->take(10)
      ->values();

    return $this->success([
      'stats' => $stats,
      'debts' => collect($debts->items())->map(fn (Sale $s) => $this->debtPayload($s, true))->values(),
      'top_customers' => $customerSummaries,
      'payment_methods' => $this->collectPaymentMethods(),
      'customers' => Customer::where('business_id', $businessId)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'phone']),
      'filters' => $this->filterMetaPayload($filter) + [
        'search' => $request->get('search'),
        'status' => $request->get('status'),
        'filter' => $request->get('filter'),
        'scoped_to_self' => ! $request->user()->seesBusinessWideData(),
      ],
      'meta' => $this->paginationMeta($debts),
    ]);
  }

  public function show(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    if ($deny = $this->ensureDebtAccess($request, $sale)) {
      return $deny;
    }

    $sale->load(['user:id,name', 'customer', 'items.item', 'items.itemPackaging.packagingType', 'items.service', 'payments.user:id,name']);

    return $this->success([
      'debt' => $this->debtPayload($sale, true) + [
        'notes' => $sale->notes,
        'items' => $sale->items->map(fn (SaleItem $line) => [
          'id' => $line->id,
          'name' => $line->item?->name ?? $line->service?->name ?? $line->line_description,
          'packaging' => $line->itemPackaging?->packagingType?->name,
          'quantity' => (float) $line->quantity,
          'unit_price' => (float) $line->unit_price,
          'subtotal' => (float) $line->subtotal,
        ])->values(),
        'payments' => $sale->payments->sortByDesc('created_at')->map(fn (SalePayment $p) => $this->paymentPayload($p))->values(),
      ],
      'payment_methods' => $this->collectPaymentMethods(),
    ]);
  }

  public function history(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    $branchFilterId = $this->bootWebBranchContext($request);
    $businessId = $this->apiBusinessId();
    $tab = $request->get('tab') === 'settled' ? 'settled' : 'payments';

    $scope = fn ($q) => $this->applyDebtSaleScope($q, $businessId, $branchFilterId);
    $debtSales = $this->debtSalesQuery($businessId, $branchFilterId);

    $stats = [
      'total_collected' => (float) SalePayment::whereHas('sale', $scope)->sum('amount'),
      'payments_count' => SalePayment::whereHas('sale', $scope)->count(),
      'settled_accounts' => (clone $debtSales)->where('payment_status', 'paid')->count(),
      'open_balance' => (float) (clone $debtSales)
        ->whereNotIn('payment_status', ['paid', 'cancelled'])
        ->whereColumn('total_amount', '>', 'amount_paid')
        ->sum(DB::raw('total_amount - amount_paid')),
    ];

    if ($tab === 'settled') {
      $page = (clone $debtSales)
        ->where('payment_status', 'paid')
        ->with(['user:id,name', 'payments'])
        ->latest('updated_at')
        ->paginate($this->perPage($request));

      $rows = collect($page->items())->map(fn (Sale $s) => $this->debtPayload($s) + [
        'payments_count' => $s->payments->count(),
        'settled_at' => $s->payments->max('created_at')?->toIso8601String() ?? $s->updated_at?->toIso8601String(),
      ])->values();
    } else {
      $page = SalePayment::query()
        ->whereHas('sale', $scope)
        ->with(['sale:id,reference_no,customer_name,customer_phone,total_amount,amount_paid,payment_status', 'user:id,name'])
        ->latest()
        ->paginate($this->perPage($request));

      $rows = collect($page->items())->map(fn (SalePayment $p) => $this->paymentPayload($p) + [
        'sale' => $p->sale ? [
          'id' => $p->sale->id,
          'reference_no' => $p->sale->reference_no,
          'customer_name' => $p->sale->customer_name,
          'customer_phone' => $p->sale->customer_phone,
          'balance_due' => $this->balance($p->sale),
          'payment_status' => $p->sale->payment_status,
        ] : null,
      ])->values();
    }

    return $this->success([
      'tab' => $tab,
      'stats' => $stats,
      'rows' => $rows,
      'meta' => $this->paginationMeta($page),
    ]);
  }

  public function collect(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'collect_payments', 'process_sales'])) {
      return $deny;
    }

    if ($deny = $this->ensureDebtAccess($request, $sale)) {
      return $deny;
    }

    $balanceDue = $this->balance($sale);
    if ($balanceDue <= 0 || in_array($sale->payment_status, ['paid', 'cancelled'], true)) {
      return $this->error('No outstanding balance on this account.', 422);
    }

    if ($reason = $request->user()->paymentCollectionBlockedReason()) {
      return $this->error($reason, 403, ['code' => 'PAYMENT_COLLECTION_DISABLED']);
    }

    if ($lockMessage = CashierPaymentGuard::lockedByOtherMessage($sale, $request->user())) {
      return $this->error($lockMessage, 409, ['code' => 'SALE_LOCKED', 'locked_by' => CashierPaymentGuard::lockHolder($sale->id)['name'] ?? null]);
    }

    $enabledKeys = collect($this->collectPaymentMethods())->pluck('key')->all();

    $rules = SalePaymentRecorder::validatePaymentRules($request, $enabledKeys, $balanceDue);
    $request->validate($rules);

    DB::beginTransaction();
    try {
      $message = SalePaymentRecorder::for($sale)->applyFromRequest($request, $balanceDue);
      SalePaymentRecorder::for($sale->fresh())->refreshShiftTotals();
      DB::commit();
      CashierPaymentGuard::release($sale, $request->user());

      $sale->refresh()->load(['payments', 'customer', 'user:id,name']);

      return $this->success([
        'debt' => $this->debtPayload($sale),
        'payment_message' => $message,
      ], 'Collection recorded');
    } catch (\Illuminate\Validation\ValidationException $e) {
      DB::rollBack();

      return $this->error('Validation failed.', 422, $e->errors());
    } catch (\Throwable $e) {
      DB::rollBack();

      return $this->error($e->getMessage(), 422);
    }
  }

  /**
   * @return array<string, mixed>
   */
  private function debtPayload(Sale $sale, bool $withItemsSummary = false): array
  {
    $balance = $this->balance($sale);

    $payload = [
      'id' => $sale->id,
      'reference_no' => $sale->reference_no,
      'customer_id' => $sale->customer_id,
      'customer_name' => $sale->customer_name ?: $sale->customer?->name,
      'customer_phone' => $sale->customer_phone ?: $sale->customer?->phone,
      'total_amount' => (float) $sale->total_amount,
      'amount_paid' => (float) $sale->amount_paid,
      'balance_due' => $balance,
      'payment_status' => $sale->payment_status,
      'due_date' => $sale->due_date?->toDateString(),
      'is_overdue' => $sale->due_date ? $sale->due_date->isPast() && ! $sale->due_date->isToday() && $balance > 0 : false,
      'days_overdue' => $sale->due_date && $sale->due_date->isPast() && $balance > 0
        ? (int) $sale->due_date->copy()->startOfDay()->diffInDays(now()->startOfDay())
        : 0,
      'sale_date' => $sale->sale_date,
      'cashier' => $sale->user?->name,
    ];

    if ($withItemsSummary && $sale->relationLoaded('items')) {
      $payload['items_summary'] = $sale->items
        ->map(fn (SaleItem $line) => trim(($line->item?->name ?? $line->service?->name ?? $line->line_description).' × '.rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.')))
        ->implode(', ');
    }

    return $payload;
  }

  /**
   * @return array<string, mixed>
   */
  private function paymentPayload(SalePayment $payment): array
  {
    return [
      'id' => $payment->id,
      'amount' => (float) $payment->amount,
      'payment_method' => $payment->payment_method,
      'payment_method_label' => collect($this->apiBusiness()?->enabledPaymentMethods() ?? [])->firstWhere('key', $payment->payment_method)['label'] ?? ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
      'payment_provider' => $payment->payment_provider,
      'transaction_reference' => $payment->transaction_reference,
      'paid_at' => $payment->created_at?->toIso8601String(),
      'recorded_by' => $payment->user?->name,
    ];
  }

  /**
   * Debt collection cannot use Pay Later (credit).
   *
   * @return array<int, array<string, mixed>>
   */
  private function collectPaymentMethods(): array
  {
    return collect($this->apiBusiness()?->enabledPaymentMethods() ?? [])
      ->reject(fn ($m) => ($m['type'] ?? '') === 'credit')
      ->values()
      ->all();
  }

  private function balance(Sale $sale): float
  {
    return max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
  }

  private function ensureDebtAccess(Request $request, Sale $sale): ?JsonResponse
  {
    if ((int) $sale->business_id !== $this->apiBusinessId()) {
      return $this->forbidden();
    }

    $user = $request->user();
    if ($user->seesBusinessWideData() || (int) $sale->user_id === (int) $user->id) {
      return null;
    }

    if (! $user->isPaymentCashier() || ! $this->scopeSalesForCashierBranch(Sale::whereKey($sale->id), $user)->exists()) {
      return $this->forbidden('You can only access your own customers\' debts.');
    }

    return null;
  }

  private function debtSalesQuery(int $businessId, ?int $branchFilterId)
  {
    return $this->applyDebtSaleScope(Sale::query(), $businessId, $branchFilterId);
  }

  /**
   * Same scope as web DebtController: non-cancelled sales that have a customer or are on credit.
   */
  private function applyDebtSaleScope($query, int $businessId, ?int $branchFilterId)
  {
    $query->where('business_id', $businessId)
      ->whereNotIn('payment_status', ['cancelled'])
      ->where(function ($inner) {
        $inner->whereNotNull('customer_name')
          ->orWhereNotNull('customer_id')
          ->orWhereIn('payment_status', ['debt', 'partial']);
      });

    $user = auth()->user();
    if (! $user->seesBusinessWideData()) {
      return $user->isPaymentCashier()
        ? $this->scopeSalesForCashierBranch($query, $user)
        : $query->where('user_id', $user->id);
    }

    if ($branchFilterId) {
      $query->whereHas('items.item.category', fn ($q) => $q->where('branch_id', $branchFilterId));
    }

    return $query;
  }
}
