<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Sale;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use App\Services\SalePaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebtController extends ApiController
{
  public function index(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'process_sales', 'collect_payments'])) {
      return $deny;
    }

    $businessId = $this->apiBusinessId();
    $today = now()->toDateString();

    $query = $this->debtQuery($businessId)
      ->with(['user:id,name', 'customer:id,name,phone']);

    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('reference_no', 'like', "%{$search}%")
          ->orWhere('customer_name', 'like', "%{$search}%")
          ->orWhere('customer_phone', 'like', "%{$search}%");
      });
    }

    if ($request->get('filter') === 'overdue') {
      $query->whereNotNull('due_date')->whereDate('due_date', '<', $today);
    }

    $allOutstanding = (clone $query)->get();
    $stats = [
      'total_outstanding' => (float) $allOutstanding->sum(fn (Sale $s) => max(0, (float) $s->total_amount - (float) $s->amount_paid)),
      'open_accounts' => $allOutstanding->count(),
      'overdue_count' => $allOutstanding->filter(fn (Sale $s) => $s->due_date && $s->due_date->toDateString() < $today)->count(),
    ];

    $debts = $query->latest()->paginate(min(50, (int) $request->get('per_page', 20)));

    return $this->success([
      'stats' => $stats,
      'debts' => collect($debts->items())->map(fn (Sale $s) => $this->debtPayload($s))->values(),
      'meta' => [
        'current_page' => $debts->currentPage(),
        'last_page' => $debts->lastPage(),
        'per_page' => $debts->perPage(),
        'total' => $debts->total(),
      ],
    ]);
  }

  public function collect(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['manage_debts', 'collect_payments', 'process_sales'])) {
      return $deny;
    }

    if ($sale->business_id != $this->apiBusinessId()) {
      return $this->forbidden();
    }

    $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
    if ($balanceDue <= 0 || in_array($sale->payment_status, ['paid', 'cancelled'], true)) {
      return $this->error('No outstanding balance on this account.', 422);
    }

    $business = $this->apiBusiness();
    $enabledKeys = collect($business->enabledPaymentMethods())
      ->where(fn ($m) => ($m['type'] ?? '') !== 'credit')
      ->pluck('key')
      ->all();

    $rules = SalePaymentRecorder::validatePaymentRules($request, $enabledKeys, $balanceDue);
    $request->validate($rules);

    DB::beginTransaction();
    try {
      $message = SalePaymentRecorder::for($sale)->applyFromRequest($request, $balanceDue);
      SalePaymentRecorder::for($sale->fresh())->refreshShiftTotals();
      DB::commit();

      $sale->refresh()->load(['payments', 'customer']);

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
  private function debtPayload(Sale $sale): array
  {
    $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);

    return [
      'id' => $sale->id,
      'reference_no' => $sale->reference_no,
      'customer_name' => $sale->customer_name,
      'customer_phone' => $sale->customer_phone,
      'customer_id' => $sale->customer_id,
      'total_amount' => (float) $sale->total_amount,
      'amount_paid' => (float) $sale->amount_paid,
      'balance_due' => $balance,
      'payment_status' => $sale->payment_status,
      'due_date' => $sale->due_date?->toDateString(),
      'is_overdue' => $sale->due_date ? $sale->due_date->isPast() && $balance > 0 : false,
      'sale_date' => $sale->sale_date,
      'cashier' => $sale->user?->name,
    ];
  }

  private function debtQuery(int $businessId)
  {
    $query = Sale::query()
      ->where('business_id', $businessId)
      ->whereNotIn('payment_status', ['paid', 'cancelled'])
      ->whereColumn('total_amount', '>', 'amount_paid');

    $user = auth()->user();
    if (! $user->seesBusinessWideData()) {
      $query->where('user_id', $user->id);
    } else {
      $ctx = app(ApiTenantContext::class);
      $branchId = $ctx->branchId();
      if ($branchId) {
        $query->whereIn('user_id', User::query()
          ->where('business_id', $businessId)
          ->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
          })
          ->pluck('id'));
      }
    }

    return $query;
  }
}
