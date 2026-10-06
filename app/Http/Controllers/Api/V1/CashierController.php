<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\CashierPaymentGuard;
use App\Services\CashierPerformanceService;
use App\Services\CashierQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierController extends ApiController
{
  public function __construct(private CashierQueueService $queue)
  {
  }

  /**
   * Same as web /cashier/queue: unpaid orders from sales officers in the cashier's branch.
   */
  public function queue(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments'])) {
      return $deny;
    }

    $request->validate([
      'tab' => 'nullable|in:unpaid,paid',
      'status' => 'nullable|in:pending,partial',
      'q' => 'nullable|string|max:100',
      'date' => 'nullable|date',
      'officer_id' => 'nullable|integer',
      'branch_id' => 'nullable|integer',
      'per_page' => 'nullable|integer|min:1|max:50',
    ]);

    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $openShift = Shift::openForUser($user->id, $businessId);

    $tab = CashierQueueService::tab($request);
    $stats = $this->queue->stats($this->queue->queueQuery($user, $businessId, $request, 'unpaid'));
    $stats['paid_orders'] = $this->queue->queueQuery($user, $businessId, $request, 'paid')->count();
    $sales = $this->queue->queueQuery($user, $businessId, $request, $tab)
      ->with(['user:id,name', 'items.item', 'items.service', 'payments.user:id,name'])
      ->latest($tab === 'paid' ? 'updated_at' : 'id')
      ->paginate((int) $request->get('per_page', 20));

    $business = $this->apiBusiness();
    $locks = CashierPaymentGuard::lockHolders(collect($sales->items())->pluck('id')->all());

    return $this->success([
      'tab' => $tab,
      'latest_order_id' => $this->queue->latestOrderId($user, $businessId, $request),
      'shift' => $openShift ? ['id' => $openShift->id, 'opened_at' => $openShift->opened_at?->toIso8601String()] : null,
      'payment_collection_mode' => $business?->paymentCollectionMode() ?? 'both',
      'can_collect' => $user->canCollectCustomerPayments() && (! $user->isPaymentCashier() || (bool) $openShift),
      'cannot_collect_reason' => $user->paymentCollectionBlockedReason()
        ?? ($user->isPaymentCashier() && ! $openShift ? 'Open your cashier shift before collecting payments.' : null),
      'branch_name' => $user->branch?->name,
      'stats' => $stats,
      'orders' => collect($sales->items())->map(fn (Sale $sale) => $this->queue->saleRow($sale, $locks[$sale->id] ?? null))->values(),
      'payment_methods' => collect($business?->enabledPaymentMethods() ?? [])
        ->map(fn ($m) => [
          'key' => $m['key'],
          'label' => $m['label'],
          'type' => $m['type'] ?? 'immediate',
          'requires_reference' => ! empty($m['requires_reference']),
          'provider_accounts' => $m['provider_accounts'] ?? [],
        ])->values(),
      'refresh_seconds' => 10,
      'meta' => [
        'current_page' => $sales->currentPage(),
        'last_page' => $sales->lastPage(),
        'per_page' => $sales->perPage(),
        'total' => $sales->total(),
      ],
    ]);
  }

  /**
   * Money the cashier collected during his open shift (sidebar "My Collections").
   */
  public function collections(Request $request): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments'])) {
      return $deny;
    }

    $limit = min(50, max(1, (int) $request->get('recent', 10)));

    return $this->success(
      $this->queue->shiftCollections($request->user(), $this->apiBusinessId(), $limit)
    );
  }

  /**
   * Owner/manager live view: cashiers with an open shift, what they collected, which order they are on.
   */
  public function onDuty(Request $request): JsonResponse
  {
    if (! $request->user()->seesBusinessWideData()) {
      return $this->forbidden('Only owners and managers can view cashiers on duty.');
    }

    $branchId = (int) $request->get('branch_id', 0) ?: null;
    $request->merge(['branch_id' => $branchId]);
    $businessId = $this->apiBusinessId();
    $user = $request->user();

    return $this->success([
      'branch_id' => $branchId,
      'cashiers' => $this->queue->cashiersOnDuty($businessId, $branchId),
      'queue' => $this->queue->stats($this->queue->queueQuery($user, $businessId, $request, 'unpaid')),
      'latest_order_id' => $this->queue->latestOrderId($user, $businessId, $request),
      'refresh_seconds' => 10,
    ]);
  }

  public function performance(Request $request, CashierPerformanceService $performance): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['view_reports'])) {
      return $deny;
    }

    $request->validate([
      'from' => 'nullable|date',
      'to' => 'nullable|date',
      'branch_id' => 'nullable|integer',
      'cashiers_only' => 'nullable|boolean',
    ]);

    $from = $request->get('from', now()->startOfMonth()->toDateString());
    $to = $request->get('to', now()->toDateString());

    return $this->success($performance->report(
      $this->apiBusiness(),
      $from,
      $to,
      (int) $request->get('branch_id', 0) ?: null,
      $request->boolean('cashiers_only')
    ));
  }

  /**
   * Hold an order while the payment screen is open so a second cashier cannot collect on it at the same time.
   * Call again every ~30 s as a heartbeat; the lock expires after 90 s without one.
   */
  public function lock(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments', 'collect_invoice_payments', 'process_sales'])) {
      return $deny;
    }

    if (! $this->canCollectOnSale($sale)) {
      return $this->forbidden('You can only collect payments on orders in your branch.');
    }

    if ($reason = $request->user()->paymentCollectionBlockedReason()) {
      return $this->error($reason, 403, ['code' => 'PAYMENT_COLLECTION_DISABLED']);
    }

    if ($holder = CashierPaymentGuard::acquire($sale, $request->user())) {
      return $this->error(
        $holder['name'].' is already collecting payment for order '.$sale->reference_no.'.',
        409,
        ['code' => 'SALE_LOCKED', 'locked_by' => $holder['name']]
      );
    }

    return $this->success([
      'sale_id' => $sale->id,
      'locked' => true,
      'ttl_seconds' => CashierPaymentGuard::LOCK_SECONDS,
      'heartbeat_seconds' => 30,
    ], 'Order locked for payment');
  }

  public function unlock(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments', 'collect_invoice_payments', 'process_sales'])) {
      return $deny;
    }

    if ((int) $sale->business_id === $this->apiBusinessId()) {
      CashierPaymentGuard::release($sale, $request->user());
    }

    return $this->success(['sale_id' => $sale->id, 'locked' => false], 'Order released');
  }

  /**
   * Receipt text + share links to send the customer right after a payment.
   */
  public function receipt(Request $request, Sale $sale): JsonResponse
  {
    if ($deny = $this->authorizeApiAny(['collect_payments', 'collect_invoice_payments', 'process_sales'])) {
      return $deny;
    }

    if (! $this->canCollectOnSale($sale)) {
      return $this->forbidden();
    }

    return $this->success($this->queue->receiptShare($sale));
  }
}
