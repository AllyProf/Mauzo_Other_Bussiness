<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\CashierPaymentGuard;
use App\Services\CashierQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashierController extends Controller
{
    public function __construct(private CashierQueueService $queue)
    {
    }

    public function queue(Request $request)
    {
        $this->authorizeAny(['collect_payments']);

        $user = Auth::user();
        $businessId = $this->currentBusinessId();
        $openShift = Shift::openForUser($user->id, $businessId);

        if ($user->isPaymentCashier() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('warning', 'Open your cashier shift before collecting payments.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $tab = CashierQueueService::tab($request);
        $stats = $this->queue->stats($this->queue->queueQuery($user, $businessId, $request, 'unpaid'));
        $paidCount = $this->queue->queueQuery($user, $businessId, $request, 'paid')->count();

        $sales = $this->queue->queueQuery($user, $businessId, $request, $tab)
            ->with(['user:id,name', 'items.item', 'items.service', 'payments.user:id,name'])
            ->latest($tab === 'paid' ? 'updated_at' : 'id')
            ->paginate(20)
            ->withQueryString();

        $locks = CashierPaymentGuard::lockHolders($sales->getCollection()->pluck('id')->all());
        $collections = $this->queue->shiftCollections($user, $businessId);
        $latestId = $this->queue->latestOrderId($user, $businessId, $request);

        $isOverseer = $user->seesBusinessWideData();
        $viewerBranchId = $isOverseer ? CashierQueueService::viewerBranchId($request) : null;
        $onDuty = $isOverseer ? $this->queue->cashiersOnDuty($businessId, $viewerBranchId) : [];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view('cashier.partials.queue-table', compact('sales', 'tab', 'locks'))->render(),
                'pagination' => $sales->links('pagination::bootstrap-4')->render(),
                'stats' => $stats,
                'paid_count' => $paidCount,
                'collections_total' => money($collections['total']),
                'latest_id' => $latestId,
                'on_duty_html' => $isOverseer ? view('cashier.partials.on-duty', ['onDuty' => $onDuty])->render() : null,
            ]);
        }

        $business = $this->requireCurrentBusiness();
        $paymentMethods = $business->enabledPaymentMethods();
        $customers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $receipt = null;
        if ($receiptSaleId = session('receipt_sale_id')) {
            $receiptSale = Sale::where('business_id', $businessId)->find($receiptSaleId);
            $receipt = $receiptSale ? $this->queue->receiptShare($receiptSale) : null;
        }

        return view('cashier.queue', [
            'sales' => $sales,
            'tab' => $tab,
            'paidCount' => $paidCount,
            'stats' => $stats,
            'collections' => $collections,
            'openShift' => $openShift,
            'paymentMethods' => $paymentMethods,
            'customers' => $customers,
            'search' => (string) $request->get('q', ''),
            'status' => (string) $request->get('status', ''),
            'branchName' => $isOverseer
                ? ($viewerBranchId ? Branch::find($viewerBranchId)?->name : null)
                : $user->branch?->name,
            'locks' => $locks,
            'latestId' => $latestId,
            'isOverseer' => $isOverseer,
            'onDuty' => $onDuty,
            'branches' => $isOverseer ? Branch::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']) : collect(),
            'viewerBranchId' => $viewerBranchId,
            'receipt' => $receipt,
        ]);
    }

    public function lock(Sale $sale): JsonResponse
    {
        $this->authorizeAny(['collect_payments', 'collect_invoice_payments', 'process_sales']);
        $this->ensureCanCollectOnSale($sale);

        if ($reason = Auth::user()->paymentCollectionBlockedReason()) {
            return response()->json(['locked' => false, 'message' => $reason], 403);
        }

        if ($holder = CashierPaymentGuard::acquire($sale, Auth::user())) {
            return response()->json([
                'locked' => false,
                'locked_by' => $holder['name'],
                'message' => $holder['name'].' is already collecting payment for order '.$sale->reference_no.'.',
            ], 409);
        }

        return response()->json(['locked' => true, 'ttl_seconds' => CashierPaymentGuard::LOCK_SECONDS]);
    }

    public function unlock(Sale $sale): JsonResponse
    {
        $this->authorizeAny(['collect_payments', 'collect_invoice_payments', 'process_sales']);

        if ((int) $sale->business_id === (int) $this->currentBusinessId()) {
            CashierPaymentGuard::release($sale, Auth::user());
        }

        return response()->json(['released' => true]);
    }
}
