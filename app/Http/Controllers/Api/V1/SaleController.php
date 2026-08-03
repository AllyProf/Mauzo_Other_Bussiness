<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalePaymentRecorder;
use App\Services\SaleStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends ApiController
{
  public function index(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['view_sales_history', 'process_sales'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();

    $query = Sale::query()
      ->where('business_id', $businessId)
      ->where('sale_source', 'pos')
      ->with(['user:id,name', 'customer:id,name,phone'])
      ->latest('sale_date')
      ->latest('id');

    if ($request->filled('shift_id')) {
      $query->where('shift_id', (int) $request->shift_id);
    }

    if ($request->filled('date')) {
      $query->whereDate('sale_date', $request->date);
    }

    if ($request->filled('payment_status')) {
      $query->where('payment_status', $request->payment_status);
    }

    $branchFilterId = $this->resolveSalesBranchFilterId($user, $request);

    if (! $user->seesBusinessWideData()) {
      $query->where('user_id', $user->id);
    } else {
      $this->scopeSalesToBranchCatalog($query, $branchFilterId);
    }

    $sales = $query->paginate(min(50, (int) $request->get('per_page', 20)));

    return $this->success([
      'sales' => collect($sales->items())->map(fn (Sale $s) => $this->saleSummary($s))->values(),
      'meta' => [
        'current_page' => $sales->currentPage(),
        'last_page' => $sales->lastPage(),
        'per_page' => $sales->perPage(),
        'total' => $sales->total(),
        'branch_id' => $branchFilterId,
        'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
      ],
    ]);
  }

  public function show(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['view_sales_history', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    if ($sale->business_id != $this->apiBusinessId()) {
      return $this->forbidden();
    }

    if ($deny = $this->ensureCanAccessSale($request->user(), $sale)) {
      return $deny;
    }

    $sale->load(['items.item', 'items.itemPackaging.packagingType', 'user:id,name', 'payments.user:id,name', 'customer']);

    return $this->success(['sale' => $this->saleDetail($sale)]);
  }

  public function store(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['process_sales'])) {
      return $deny;
    }

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $openShift = Shift::openForUser($user->id, $businessId);

    if ($user->requiresOpenShift() && ! $openShift) {
      return $this->error('Your shift is not open. Complete stock check first.', 422, ['code' => 'SHIFT_REQUIRED']);
    }

    $request->validate([
      'sale_date' => 'required|date',
      'items' => 'required|array|min:1',
      'items.*.id' => 'required|exists:items,id',
      'items.*.item_packaging_id' => 'nullable|exists:item_packagings,id',
      'items.*.qty' => 'required|integer|min:1',
      'items.*.price' => 'required|numeric|min:0.01',
      'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
      'customer_name' => 'nullable|string|max:255',
      'customer_phone' => 'nullable|string|max:50',
      'notes' => 'nullable|string|max:1000',
    ]);

    $activeItems = array_values(array_filter($request->items, fn ($i) => ($i['qty'] ?? 0) > 0));
    if ($activeItems === []) {
      return $this->error('Add at least one item with quantity > 0.', 422);
    }

    DB::beginTransaction();
    try {
      $total = 0.0;
      foreach ($activeItems as $i) {
        $total += (float) $i['qty'] * (float) $i['price'];
      }

      $stockContext = app(SaleStockService::class)->shiftStockContext($openShift);
      $stockService = app(SaleStockService::class);

      foreach ($activeItems as $i) {
        $item = Item::with('packagings')->where('business_id', $businessId)->findOrFail($i['id']);
        $packaging = ! empty($i['item_packaging_id'])
          ? $item->packagings->firstWhere('id', (int) $i['item_packaging_id'])
          : $item->packagings->sortBy('quantity_per_unit')->first();
        $stockNeeded = $item->stockUnitsForPackaging((int) $i['qty'], $packaging);
        $available = $stockService->availableStockForShift($item, $openShift, $stockContext);

        if ($stockNeeded > $available) {
          DB::rollBack();
          $unitLabel = $packaging?->packagingType?->name ?? 'unit';

          return $this->error("Not enough stock for {$item->name} ({$unitLabel}). Available: {$available} pieces.", 422);
        }
      }

      $customerFields = $this->resolveCustomerFields($request);
      $ref = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

      $sale = Sale::create([
        'business_id' => $businessId,
        'user_id' => $user->id,
        'shift_id' => $openShift?->id,
        'reference_no' => $ref,
        'sale_source' => 'pos',
        'stock_deducted' => false,
        'sale_date' => $request->sale_date,
        'total_amount' => $total,
        'amount_paid' => 0,
        'payment_status' => 'pending',
        'customer_id' => $customerFields['customer_id'],
        'customer_name' => $customerFields['customer_name'],
        'customer_phone' => $customerFields['customer_phone'],
        'notes' => $request->notes,
      ]);

      foreach ($activeItems as $i) {
        $item = Item::with('packagings')->find($i['id']);
        $packaging = ! empty($i['item_packaging_id'])
          ? $item->packagings->firstWhere('id', (int) $i['item_packaging_id'])
          : $item->packagings->sortBy('quantity_per_unit')->first();
        $subtotal = (float) $i['qty'] * (float) $i['price'];
        $unitCost = (float) (optional($packaging)->cost_price ?? 0);

        SaleItem::create([
          'sale_id' => $sale->id,
          'item_id' => $i['id'],
          'item_packaging_id' => $packaging?->id,
          'quantity' => $i['qty'],
          'unit_price' => $i['price'],
          'list_unit_price' => $i['price'],
          'cost_price' => $unitCost,
          'subtotal' => $subtotal,
        ]);
      }

      DB::commit();
      $openShift?->refreshTotals();

      $sale->load(['items.item', 'items.itemPackaging.packagingType', 'user:id,name']);

      return $this->success(['sale' => $this->saleDetail($sale)], 'Order created. Complete payment.', 201);
    } catch (\Throwable $e) {
      DB::rollBack();

      return $this->error('Failed to create sale: '.$e->getMessage(), 500);
    }
  }

  public function pay(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments', 'collect_invoice_payments', 'process_sales'])) {
      return $deny;
    }

    $business = $this->apiBusiness();
    $businessId = $this->apiBusinessId();

    if ($sale->business_id != $businessId) {
      return $this->forbidden();
    }

    if ($deny = $this->ensureCanAccessSale($request->user(), $sale)) {
      return $deny;
    }

    if (in_array($sale->payment_status, ['paid', 'cancelled'], true)) {
      return $this->error('Sale is already paid or cancelled.', 422);
    }

    $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
    if ($balanceDue <= 0) {
      return $this->error('No balance remaining.', 422);
    }

    $enabledKeys = $business->enabledPaymentMethodKeys();
    $rules = SalePaymentRecorder::validatePaymentRules($request, $enabledKeys, $balanceDue);
    $request->validate($rules);

    DB::beginTransaction();
    try {
      $message = SalePaymentRecorder::for($sale)->applyFromRequest($request, $balanceDue);
      SalePaymentRecorder::for($sale->fresh())->refreshShiftTotals();
      DB::commit();

      $sale->refresh()->load(['items.item', 'payments.user:id,name', 'customer']);

      return $this->success([
        'sale' => $this->saleDetail($sale),
        'payment_message' => $message,
      ], 'Payment recorded');
    } catch (\Illuminate\Validation\ValidationException $e) {
      DB::rollBack();

      return $this->error('Validation failed.', 422, $e->errors());
    } catch (\Throwable $e) {
      DB::rollBack();

      return $this->error($e->getMessage(), 422);
    }
  }

  public function cancel(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['cancel_sales'])) {
      return $deny;
    }

    if ($sale->business_id != $this->apiBusinessId()) {
      return $this->forbidden();
    }

    if ($deny = $this->ensureCanAccessSale($request->user(), $sale)) {
      return $deny;
    }

    if ($sale->payment_status === 'cancelled') {
      return $this->error('Sale is already cancelled.', 422);
    }

    if ((float) $sale->amount_paid > 0) {
      return $this->error('Cannot cancel a sale that already has payments.', 422);
    }

    $sale->update(['payment_status' => 'cancelled']);
    if ($sale->shift_id) {
      Shift::find($sale->shift_id)?->refreshTotals();
    }

    return $this->success(['sale' => $this->saleSummary($sale->fresh())], 'Sale cancelled');
  }

  /**
   * @return array<string, mixed>
   */
  private function saleSummary(Sale $sale): array
  {
    return [
      'id' => $sale->id,
      'reference_no' => $sale->reference_no,
      'sale_date' => $sale->sale_date,
      'total_amount' => (float) $sale->total_amount,
      'amount_paid' => (float) $sale->amount_paid,
      'balance_due' => max(0, (float) $sale->total_amount - (float) $sale->amount_paid),
      'payment_status' => $sale->payment_status,
      'payment_method' => $sale->payment_method,
      'customer_name' => $sale->customer_name,
      'customer_phone' => $sale->customer_phone,
      'cashier' => $sale->user?->name,
      'shift_id' => $sale->shift_id,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function saleDetail(Sale $sale): array
  {
    return array_merge($this->saleSummary($sale), [
      'due_date' => $sale->due_date?->toDateString(),
      'notes' => $sale->notes,
      'items' => $sale->items->map(fn (SaleItem $line) => [
        'id' => $line->id,
        'item_id' => $line->item_id,
        'name' => $line->item?->name ?? $line->line_description,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
        'subtotal' => (float) $line->subtotal,
        'packaging' => $line->itemPackaging?->packagingType?->name,
      ])->values(),
      'payments' => $sale->payments->map(fn (SalePayment $p) => [
        'id' => $p->id,
        'amount' => (float) $p->amount,
        'payment_method' => $p->payment_method,
        'payment_provider' => $p->payment_provider,
        'transaction_reference' => $p->transaction_reference,
        'paid_at' => $p->created_at?->toIso8601String(),
        'recorded_by' => $p->user?->name,
      ])->values(),
    ]);
  }

  private function ensureCanAccessSale(User $user, Sale $sale): ?JsonResponse
  {
    if ($user->seesBusinessWideData()) {
      return null;
    }

    if ((int) $sale->user_id !== (int) $user->id) {
      return $this->forbidden('You can only access your own sales.');
    }

    return null;
  }

  /**
   * Same idea as web /sales: owners use active branch (or ?branch_id=); staff locked to own branch.
   * Pass branch_id=0 or omit with no switched branch to view all (owners only).
   */
  private function resolveSalesBranchFilterId(User $user, Request $request): ?int
  {
    if (! $user->seesBusinessWideData() && $user->branch_id) {
      return (int) $user->branch_id;
    }

    if ($request->exists('branch_id')) {
      $raw = $request->input('branch_id');
      if ($raw === null || $raw === '' || (int) $raw === 0) {
        return null;
      }
      $requested = (int) $raw;
      if ($requested > 0 && $this->tenantContext()->ownerBranches()->contains('id', $requested)) {
        return $requested;
      }

      return null;
    }

    return $this->tenantContext()->branchId();
  }

  /**
   * Match web SaleController: filter by product/service catalog branch, not only seller's user.branch_id.
   */
  private function scopeSalesToBranchCatalog($query, ?int $branchId): void
  {
    if (! $branchId) {
      return;
    }

    $query->where(function ($q) use ($branchId) {
      $q->whereHas('items.item.category', function ($categoryQuery) use ($branchId) {
        $categoryQuery->where('branch_id', $branchId);
      })->orWhereHas('items.service', function ($serviceQuery) use ($branchId) {
        $serviceQuery->where('branch_id', $branchId);
      });
    });
  }
}
