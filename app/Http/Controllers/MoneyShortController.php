<?php

namespace App\Http\Controllers;

use App\Models\DayClosing;
use App\Models\MoneyShortSettlement;
use App\Services\MoneyShortSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MoneyShortController extends Controller
{
    public function __construct(private MoneyShortSettlementService $settlementService)
    {
    }

    public function index(Request $request)
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can view money shorts.');
        }

        $this->authorizeAny(['verify_day_closing', 'view_reports']);

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $businessId = $business->id;
        $statusFilter = $request->get('status', 'all');
        $branchFilterId = active_branch_id();
        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;
        $activeBusinessType = $request->get('business_type');
        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();

        if ($activeBusinessType && ! in_array($activeBusinessType, $typeKeys, true)) {
            $activeBusinessType = null;
        }

        $activeBusinessLabel = $activeBusinessType
            ? collect($businessTypes)->firstWhere('key', $activeBusinessType)['label'] ?? $business->businessTypeLabel($activeBusinessType)
            : null;

        $query = DayClosing::where('business_id', $businessId)
            ->where('money_short', '>', 0)
            ->where('status', 'verified')
            ->with(['user', 'shift', 'verifier', 'settlements.recorder', 'settlements.voider', 'business']);

        $this->scopeToActiveBranchUsers($query);

        if ($activeBusinessType) {
            $this->settlementService->scopeClosingsForBusinessType($query, $businessId, $activeBusinessType);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhere('shortage_note', 'like', "%{$search}%");
            });
        }

        $allShorts = (clone $query)->get()->map(function (DayClosing $closing) use ($activeBusinessType, $business) {
            $closing->short_balance = $this->settlementService->shortBalance($closing);
            $closing->settlement_status = $this->settlementService->settlementStatus($closing);
            $closing->short_split = $this->settlementService->computeShortSplit($closing);
            $closing->business_type_keys = $this->settlementService->closingBusinessTypeKeys($closing);

            if ($activeBusinessType) {
                $allocation = $this->settlementService->allocateShortToBusinessType($closing, $activeBusinessType);
                $ratio = $allocation['ratio'];

                $closing->display_short = $allocation['allocated_short'];
                $closing->display_paid = $allocation['allocated_settled'];
                $closing->short_balance = $allocation['allocated_balance'];
                $closing->short_split = [
                    'profit_short' => round(($closing->short_split['profit_short'] ?? 0) * $ratio, 2),
                    'circulation_short' => round(($closing->short_split['circulation_short'] ?? 0) * $ratio, 2),
                ];

                if ($allocation['allocated_short'] <= 0) {
                    $closing->settlement_status = 'none';
                } elseif ($allocation['allocated_balance'] <= 0) {
                    $lastType = $closing->settlements()->active()->latest('id')->value('settlement_type');
                    $closing->settlement_status = $lastType === MoneyShortSettlement::TYPE_SALARY_DEDUCTION
                        ? 'salary_deduction'
                        : 'paid';
                } elseif ($allocation['allocated_settled'] > 0) {
                    $closing->settlement_status = 'partial';
                } else {
                    $closing->settlement_status = 'pending';
                }
            } else {
                $closing->display_short = (float) $closing->money_short;
                $closing->display_paid = (float) $closing->settlements->reject(fn ($s) => $s->isVoided())->sum('amount');
            }

            $closing->business_type_labels = collect($closing->business_type_keys)
                ->map(fn ($key) => $business->businessTypeLabel($key))
                ->values()
                ->all();

            return $closing;
        });

        if ($statusFilter === 'outstanding') {
            $filtered = $allShorts->filter(fn (DayClosing $closing) => $closing->short_balance > 0)->values();
        } elseif ($statusFilter === 'settled') {
            $filtered = $allShorts->filter(fn (DayClosing $closing) => $closing->short_balance <= 0)->values();
        } else {
            $filtered = $allShorts;
        }

        $stats = [
            'total_records' => $allShorts->count(),
            'outstanding_count' => $allShorts->filter(fn ($c) => $c->short_balance > 0)->count(),
            'outstanding_total' => (float) $allShorts->sum(fn ($c) => $c->short_balance),
            'settled_count' => $allShorts->filter(fn ($c) => $c->short_balance <= 0)->count(),
            'total_short' => (float) $allShorts->sum(fn ($c) => $c->display_short),
        ];

        $paymentMethods = Auth::user()->business->enabledPaymentMethods();

        $historyQuery = MoneyShortSettlement::where('business_id', $businessId)
            ->with(['dayClosing.shift', 'staff', 'recorder', 'voider'])
            ->whereHas('dayClosing', function ($q) use ($activeBusinessType, $businessId) {
                $q->where('status', 'verified')->where('money_short', '>', 0);
                $this->scopeToActiveBranchUsers($q);

                if ($activeBusinessType) {
                    $this->settlementService->scopeClosingsForBusinessType($q, $businessId, $activeBusinessType);
                }
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $historyQuery->where(function ($q) use ($search) {
                $q->whereHas('staff', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('dayClosing', fn ($closingQuery) => $closingQuery->where('shortage_note', 'like', "%{$search}%"));
            });
        }

        $settlementHistory = $historyQuery->orderByDesc('created_at')->get();

        return view('money-shorts.index', [
            'shorts' => $filtered,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'paymentMethods' => $paymentMethods,
            'settlementHistory' => $settlementHistory,
            'businessTypes' => $businessTypes,
            'multiBusiness' => $multiBusiness,
            'activeBusinessType' => $activeBusinessType,
            'activeBusinessLabel' => $activeBusinessLabel,
        ]);
    }

    public function recordPayment(Request $request, DayClosing $dayClosing)
    {
        $this->assertOwnerCanManageShort($dayClosing);

        $balance = $this->settlementService->shortBalance($dayClosing);
        if ($balance <= 0) {
            return redirect()->route('money-shorts.index', $this->indexRedirectParams($request))
                ->with('info', 'This money short is already settled.');
        }

        $allowedMethods = collect(Auth::user()->business->enabledPaymentMethods())->pluck('key')->all();

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.$balance,
            'settlement_date' => 'required|date',
            'payment_method' => ['required', 'string', Rule::in($allowedMethods)],
            'payment_provider' => 'nullable|string|max:255',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->settlementService->recordCashPayment($dayClosing, Auth::user(), [
            'amount' => $request->amount,
            'settlement_date' => $request->settlement_date,
            'payment_method' => $request->payment_method,
            'payment_provider' => $request->payment_provider,
            'transaction_reference' => $request->transaction_reference,
            'notes' => $request->notes,
        ]);

        return redirect()->route('money-shorts.index', $this->indexRedirectParams($request))
            ->with('success', 'Payment recorded and posted to the Master Sheet for '.$request->settlement_date.'.');
    }

    public function recordSalaryDeduction(Request $request, DayClosing $dayClosing)
    {
        $this->assertOwnerCanManageShort($dayClosing);

        $balance = $this->settlementService->shortBalance($dayClosing);
        if ($balance <= 0) {
            return redirect()->route('money-shorts.index', $this->indexRedirectParams($request))
                ->with('info', 'This money short is already settled.');
        }

        $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:'.$balance,
            'settlement_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->settlementService->recordSalaryDeduction($dayClosing, Auth::user(), [
            'amount' => $request->amount,
            'settlement_date' => $request->settlement_date ?? now()->toDateString(),
            'notes' => $request->notes,
        ]);

        return redirect()->route('money-shorts.index', $this->indexRedirectParams($request))
            ->with('success', 'Salary deduction recorded. This clears the staff balance without adding cash to the Master Sheet.');
    }

    public function undoSettlement(MoneyShortSettlement $settlement)
    {
        $this->assertOwnerCanManageSettlement($settlement);

        $result = $this->settlementService->undoSettlement($settlement, Auth::user());

        $message = $result['type_label'].' of '.number_format($result['amount'], 0).' undone.';
        if ($result['was_cash_payment']) {
            $message .= ' Master Sheet for '.$result['settlement_date'].' has been updated.';
        }
        if ($result['new_balance'] > 0) {
            $message .= ' Outstanding balance is now '.number_format($result['new_balance'], 0).'.';
        }

        return redirect()->route('money-shorts.index', $this->indexRedirectParams(request()))
            ->with('success', $message);
    }

    private function indexRedirectParams(Request $request): array
    {
        return array_filter([
            'status' => $request->get('status', 'all') !== 'all' ? $request->get('status') : null,
            'business_type' => $request->get('business_type'),
            'search' => $request->get('search'),
        ]);
    }

    private function assertOwnerCanManageSettlement(MoneyShortSettlement $settlement): void
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can manage money shorts.');
        }

        if ($settlement->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        if ($settlement->isVoided()) {
            abort(404);
        }

        $closing = $settlement->dayClosing;
        if (! $closing || $closing->status !== 'verified' || ! $closing->hasMoneyShort()) {
            abort(404);
        }
    }

    private function assertOwnerCanManageShort(DayClosing $dayClosing): void
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can manage money shorts.');
        }

        if ($dayClosing->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        if ($dayClosing->status !== 'verified' || ! $dayClosing->hasMoneyShort()) {
            abort(404);
        }
    }
}
