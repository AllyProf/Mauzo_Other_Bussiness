<?php

namespace App\Http\Controllers;

use App\Models\DayClosing;
use App\Models\DayClosingExpense;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\BusinessStaffMailService;
use App\Services\BusinessStaffSmsService;
use App\Services\BusinessTypeBreakdownService;
use App\Services\OwnerDailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DayClosingController extends Controller
{
    public function __construct(
        private OwnerDailyReportService $reportService,
        private BusinessTypeBreakdownService $businessTypeBreakdown,
        private BusinessStaffSmsService $staffSms,
        private BusinessStaffMailService $staffMail,
    )
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAny(['submit_day_closing', 'verify_day_closing', 'view_reports', 'process_sales']);

        $businessId = $this->currentBusinessId();
        $isBossReview = $this->isBossReviewMode();
        $canSubmitHandover = ! $isBossReview;
        $shift = null;
        $dayHandovers = collect();
        $awaitingHandoverShifts = collect();

        if ($this->requiresShiftHandover()) {
            $context = $this->resolveStaffShiftContext($request, $businessId);
            $shift = $context['shift'];
            $canSubmitHandover = $context['canSubmitHandover'];

            if (! $shift) {
                $date = $request->get('date', now()->toDateString());
                $submitted = DayClosing::where('business_id', $businessId)
                    ->where('user_id', Auth::id())
                    ->whereNotNull('shift_id')
                    ->whereDate('closing_date', $date)
                    ->latest('submitted_at')
                    ->first();

                if ($submitted) {
                    return redirect()->route('day-closing.show', $submitted->id);
                }
            }
        }

        $date = $shift
            ? ($shift->isOpen()
                ? $shift->opened_at->toDateString()
                : ($shift->closed_at?->toDateString() ?? now()->toDateString()))
            : ($request->get('date', now()->toDateString()));

        if ($shift && ! $shift->isOpen()) {
            $existingClosing = DayClosing::where('shift_id', $shift->id)
                ->with(['expenses', 'user', 'verifier', 'shift'])
                ->first();
        } elseif (! $shift) {
            $existingClosing = DayClosing::where('business_id', $businessId)
                ->whereDate('closing_date', $date)
                ->whereNull('shift_id')
                ->with(['expenses', 'user', 'verifier', 'shift'])
                ->first();
        } else {
            $existingClosing = null;
        }

        if ($existingClosing && ! $isBossReview) {
            if ($this->actsAsBusinessWideViewer()
                || Auth::user()->can('verify_day_closing')
                || $existingClosing->user_id === Auth::id()) {
                return redirect()->route('day-closing.show', $existingClosing->id);
            }

            return redirect()->route('sales.index')
                ->with('info', 'Daily reconciliation for this date was already submitted.');
        }

        if ($shift?->isOpen()) {
            if ($canSubmitHandover) {
                $this->attachOrphanSalesToShift($shift, $date);
            }
            $shift->refreshTotals();
        }

        if ($isBossReview) {
            $dayHandoversQuery = DayClosing::where('business_id', $businessId)
                ->whereDate('closing_date', $date)
                ->with(['user', 'shift', 'verifier', 'expenses']);

            $dayHandoversQuery->where(function ($query) {
                $this->scopeDayClosingsForActiveBranch($query);
            });

            $dayHandovers = $this->sortHandoversForBossReview(
                $dayHandoversQuery->get()
            );

            $awaitingHandoverShiftsQuery = Shift::where('business_id', $businessId)
                ->whereDoesntHave('dayClosing')
                ->where(function ($query) use ($date) {
                    $query->whereDate('opened_at', '<=', $date)
                        ->where(function ($inner) use ($date) {
                            $inner->whereNull('closed_at')
                                ->orWhereDate('closed_at', '>=', $date);
                        });
                })
                ->with('user');

            $this->scopeToActiveBranchUsers($awaitingHandoverShiftsQuery);

            $awaitingHandoverShifts = $awaitingHandoverShiftsQuery->latest('opened_at')->get();
        }

        $handoverCards = $isBossReview
            ? $this->buildHandoverCardsForBossReview($dayHandovers)
            : collect();

        $pendingVerificationHandovers = collect();
        $pendingFromOtherDays = collect();

        if ($isBossReview) {
            $pendingVerificationQuery = DayClosing::where('business_id', $businessId)
                ->where('status', 'submitted')
                ->with(['user', 'shift']);

            $this->scopeToActiveBranchUsers($pendingVerificationQuery);

            $pendingVerificationHandovers = $this->sortHandoversForBossReview(
                $pendingVerificationQuery->get()
            );

            $pendingFromOtherDays = $pendingVerificationHandovers->filter(
                fn (DayClosing $closing) => $closing->closing_date->toDateString() !== $date
            )->values();

            $pendingOnSelectedDate = $pendingVerificationHandovers->filter(
                fn (DayClosing $closing) => $closing->closing_date->toDateString() === $date
            )->values();
        } else {
            $pendingOnSelectedDate = collect();
        }

        $collectorUserId = $shift?->user_id;

        $excludeOpenShiftIds = $isBossReview
            ? $awaitingHandoverShifts
                ->filter(fn (Shift $s) => $s->isOpen())
                ->pluck('id')
                ->all()
            : [];

        $summary = $this->buildDaySummary(
            $businessId,
            $date,
            $shift,
            excludeShiftIds: $excludeOpenShiftIds !== [] ? $excludeOpenShiftIds : null
        );
        $currentShiftId = $shift?->id;
        $shiftPaymentWindow = $this->resolveShiftPaymentWindow($shift);
        $staffRows = $this->buildStaffReconciliation($businessId, $date, $summary['sales'], $collectorUserId, $currentShiftId, $shiftPaymentWindow);
        $debtCollections = $this->buildDebtCollections($businessId, $date, $summary['sales'], $collectorUserId, $currentShiftId, $shiftPaymentWindow);
        $platformBreakdown = $this->buildPlatformBreakdown($businessId, $date, $shift?->id, $collectorUserId, $summary['sales'], $shiftPaymentWindow);
        $allDaySales = $this->resolveAllHandoverSalesViewData(
            $summary['sales'],
            $debtCollections,
            $businessId,
            $date,
            $currentShiftId,
            $shiftPaymentWindow,
            $collectorUserId
        );
        $displayDate = Carbon::parse($date)->format('l, F d, Y');

        $business = $this->currentBusiness();
        $branchFilterId = $this->dayClosingBranchFilterId();
        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;
        $businessTypeBreakdown = $this->businessTypeBreakdown->buildFromSales(
            $summary['sales'],
            $businessTypes,
            $business,
            $this->allocateDebtCollectionsByBusinessType($businessId, $date, $summary['sales'], $currentShiftId, $shiftPaymentWindow)
        );
        $businessTypeTotals = $this->businessTypeBreakdown->summarize($businessTypeBreakdown);
        $expenseDeductFrom = $business->expense_deduct_from ?? 'circulation';

        $ownerDirectClosing = null;
        $ownerDirectSummary = null;
        $ownerDirectExpectedHandover = 0;
        $canPostOwnerDirectSales = false;

        if ($isBossReview) {
            $ownerDirectClosing = DayClosing::where('business_id', $businessId)
                ->whereDate('closing_date', $date)
                ->whereNull('shift_id')
                ->where('user_id', Auth::id())
                ->first();

            $ownerDirectSummary = $this->buildOwnerDirectSummary($businessId, $date);
            $canPostOwnerDirectSales = ! $ownerDirectClosing
                && ($ownerDirectSummary['sales_count'] ?? 0) > 0;

            if ($canPostOwnerDirectSales) {
                $ownerDirectPlatformBreakdown = $this->buildPlatformBreakdown(
                    $businessId,
                    $date,
                    null,
                    Auth::id(),
                    $ownerDirectSummary['sales']
                );
                $ownerDirectExpectedHandover = collect($ownerDirectPlatformBreakdown)->sum('amount');
            }
        }

        $ownerDirectCloseCard = null;

        if ($ownerDirectClosing) {
            $staffRows = $this->markOwnerStaffRowPosted($staffRows, $ownerDirectClosing);

            $awaitingHandoverShifts = $awaitingHandoverShifts
                ->reject(fn (Shift $shift) => (int) $shift->user_id === (int) $ownerDirectClosing->user_id);

            if ($isBossReview) {
                $ownerDirectClosing->loadMissing(['user', 'verifier', 'business']);
                $ownerDirectCloseCard = [
                    'dayClosing' => $ownerDirectClosing,
                    'financeData' => $this->reportService->buildReportData(
                        $ownerDirectClosing->business,
                        $date,
                        $ownerDirectClosing
                    ),
                    'handoverSummary' => $this->buildHandoverSummary($ownerDirectClosing, ['total' => 0, 'count' => 0]),
                ];
            }
        }

        $serviceMenuContext = $request->routeIs('services.handover');

        return view('day-closing.index', compact(
            'date',
            'summary',
            'staffRows',
            'platformBreakdown',
            'allDaySales',
            'displayDate',
            'shift',
            'canSubmitHandover',
            'isBossReview',
            'dayHandovers',
            'awaitingHandoverShifts',
            'handoverCards',
            'pendingVerificationHandovers',
            'pendingFromOtherDays',
            'pendingOnSelectedDate',
            'debtCollections',
            'businessTypes',
            'multiBusiness',
            'businessTypeBreakdown',
            'businessTypeTotals',
            'expenseDeductFrom',
            'ownerDirectClosing',
            'ownerDirectSummary',
            'ownerDirectExpectedHandover',
            'ownerDirectCloseCard',
            'canPostOwnerDirectSales',
            'serviceMenuContext',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['submit_day_closing', 'verify_day_closing', 'view_reports', 'process_sales']);

        if ($this->isBossReviewMode()) {
            return redirect()->route('day-closing.index', ['date' => $request->get('closing_date', now()->toDateString())])
                ->with('error', 'Review and verify staff handovers here — business owners do not submit their own handover.');
        }

        $request->validate([
            'closing_date' => 'required|date',
            'report_notes' => 'nullable|string|max:2000',
            'cash_amount' => 'nullable|numeric|min:0',
            'mobile_money_amount' => 'nullable|numeric|min:0',
            'bank_amount' => 'nullable|numeric|min:0',
            'platform_amounts' => 'nullable|array',
            'platform_amounts.*' => 'nullable|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.description' => 'required_with:expenses|string|max:255',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0.01',
            'expenses.*.payment_method' => 'nullable|string|max:50',
        ]);

        $businessId = $this->currentBusinessId();
        $date = $request->closing_date;
        $shift = null;

        if ($this->requiresShiftHandover()) {
            $request->validate(['shift_id' => 'required|exists:shifts,id']);

            $shift = Shift::where('id', $request->shift_id)
                ->where('business_id', $businessId)
                ->where('user_id', Auth::id())
                ->whereDoesntHave('dayClosing')
                ->whereIn('status', ['open', 'closed'])
                ->first();

            if (! $shift) {
                return redirect()->route('shifts.index')
                    ->with('error', 'Invalid or already submitted shift handover.');
            }
        } elseif (DayClosing::where('business_id', $businessId)->whereDate('closing_date', $date)->whereNull('shift_id')->exists()) {
            return redirect()->back()->with('error', 'This day has already been closed.');
        }

        if ($shift && DayClosing::where('shift_id', $shift->id)->exists()) {
            return redirect()->back()->with('error', 'Handover for this shift was already submitted.');
        }

        if ($shift) {
            $this->attachOrphanSalesToShift($shift, $date);
        }

        $summary = $this->buildDaySummary($businessId, $date, $shift, shiftOnly: (bool) $shift);
        $expenses = collect($request->expenses ?? [])->filter(fn ($e) => ! empty($e['description']) && ($e['amount'] ?? 0) > 0);
        $totalExpenses = $expenses->sum('amount');
        $shiftPaymentWindow = $this->resolveShiftPaymentWindow($shift);
        $debtCollections = $shift
            ? $this->buildDebtCollections($businessId, $date, $summary['sales'], $shift->user_id, $shift->id, $shiftPaymentWindow)
            : ['total' => 0, 'count' => 0, 'items' => []];
        $allDaySalesSnapshot = $shift
            ? $this->resolveAllHandoverSalesViewData(
                $summary['sales'],
                $debtCollections,
                $businessId,
                $date,
                $shift->id,
                $shiftPaymentWindow,
                $shift->user_id
            )
            : [];

        $platformBreakdown = $this->buildPlatformBreakdown(
            $businessId,
            $date,
            $shift?->id,
            $shift?->user_id,
            $summary['sales'],
            $shiftPaymentWindow
        );

        $paymentBreakdown = collect($platformBreakdown)->mapWithKeys(fn ($item, $key) => [$key => (float) $item['amount']])->all();
        $paymentBreakdown = $this->applyExpensesToBreakdown($paymentBreakdown, $expenses, $platformBreakdown);

        $cashReceived = (float) ($paymentBreakdown['cash'] ?? 0);
        $mobileReceived = collect($paymentBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'mobile_money')->sum();
        $bankReceived = collect($paymentBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'bank')->sum();

        $declaredTotal = array_sum($paymentBreakdown);
        $netAmount = $declaredTotal;

        DB::beginTransaction();

        try {
            if ($shift?->isOpen()) {
                $shift->refreshTotals();
                Shift::whereKey($shift->id)->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);
                $shift->refresh();
            }

            $date = $shift?->closed_at?->toDateString() ?? $date;
            $summary = $this->buildDaySummary($businessId, $date, $shift, shiftOnly: (bool) $shift);
            $handoverSnapshot = $shift ? [
                'debt_collections' => $debtCollections,
                'all_day_sales' => $allDaySalesSnapshot,
                'shift_window' => $shiftPaymentWindow ? [
                    'start' => $shiftPaymentWindow['start']->toIso8601String(),
                    'end' => $shiftPaymentWindow['end']->toIso8601String(),
                ] : null,
            ] : null;

            $closing = DayClosing::create([
                'business_id' => $businessId,
                'user_id' => Auth::id(),
                'shift_id' => $shift?->id,
                'closing_date' => $date,
                'status' => 'submitted',
                'sales_count' => $summary['sales_count'],
                'gross_sales' => $summary['gross_sales'],
                'amount_collected' => $summary['amount_collected'],
                'outstanding_sales' => $summary['outstanding_sales'],
                'payments_received' => $declaredTotal,
                'cash_received' => $cashReceived,
                'mobile_received' => $mobileReceived,
                'bank_received' => $bankReceived,
                'payment_breakdown' => $paymentBreakdown,
                'handover_snapshot' => $handoverSnapshot,
                'cancelled_sales' => $summary['cancelled_sales'],
                'total_expenses' => $totalExpenses,
                'net_amount' => $netAmount,
                'expected_handover' => $netAmount,
                'report_notes' => $request->report_notes,
                'submitted_at' => now(),
            ]);

            foreach ($expenses as $expense) {
                DayClosingExpense::create([
                    'day_closing_id' => $closing->id,
                    'description' => $expense['description'],
                    'amount' => $expense['amount'],
                    'payment_method' => $expense['payment_method'] ?? 'cash',
                ]);
            }

            DB::commit();

            $request->session()->forget('handover_shift_id');

            $closing->load(['business', 'user']);
            try {
                $this->staffSms->notifyHandoverSubmitted(
                    $closing->business ?? $this->currentBusiness(),
                    Auth::user(),
                    $closing
                );
                $this->staffMail->notifyHandoverSubmitted(
                    $closing->business ?? $this->currentBusiness(),
                    Auth::user(),
                    $closing
                );
            } catch (\Throwable) {
                // Non-blocking
            }

            return redirect()->route('day-closing.show', $closing->id)
                ->with('success', $shift
                    ? 'Handover submitted and your shift is now closed.'
                    : 'Daily reconciliation submitted to your boss successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to submit reconciliation: ' . $e->getMessage())->withInput();
        }
    }

    public function verify(Request $request, DayClosing $dayClosing)
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can verify staff handovers.');
        }

        if ($dayClosing->business_id != Auth::user()->business_id) {
            abort(403);
        }

        if ($dayClosing->status === 'verified') {
            return redirect()->back()->with('error', 'This reconciliation is already verified.');
        }

        $nextPending = $this->nextPendingHandoverToVerify($dayClosing->business_id);
        if ($nextPending && $nextPending->id !== $dayClosing->id) {
            $label = $nextPending->shift
                ? 'Shift #'.$nextPending->shift->id
                : ($nextPending->user->name ?? 'the oldest handover');

            return redirect()->to($this->bossReconciliationUrl($nextPending))
                ->with('error', "Verify {$label} first — oldest pending handover comes before this one.");
        }

        $expected = (float) ($dayClosing->net_amount ?: collect($dayClosing->payment_breakdown ?? [])->sum());
        $actual = (float) $request->input('actual_received', $expected);
        $moneyShort = max(0, round($expected - $actual, 2));

        $request->validate([
            'actual_received' => 'required|numeric|min:0',
            'shortage_note' => [
                Rule::requiredIf($moneyShort > 0),
                'nullable',
                'string',
                'max:1000',
            ],
            'dispute_reason' => 'nullable|string|max:1000',
        ]);

        if ($request->filled('dispute_reason')) {
            $dayClosing->update([
                'status' => 'disputed',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'dispute_reason' => $request->dispute_reason,
                'expected_handover' => $expected,
                'actual_received' => $actual,
                'money_short' => $moneyShort,
                'shortage_note' => $moneyShort > 0 ? $request->shortage_note : null,
            ]);

            return redirect()->to($this->bossReconciliationUrl($dayClosing))
                ->with('success', 'Reconciliation marked as disputed.');
        }

        $dayClosing->update([
            'status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'dispute_reason' => null,
            'expected_handover' => $expected,
            'actual_received' => $actual,
            'money_short' => $moneyShort,
            'shortage_note' => $moneyShort > 0 ? $request->shortage_note : null,
        ]);

        $dayClosing->load(['expenses', 'user', 'business']);
        $this->reportService->syncReport(
            $dayClosing->business,
            $dayClosing->closing_date->toDateString(),
            $dayClosing
        );

        $finalizedReport = $this->reportService->tryFinalizeDayIfReady(
            $dayClosing->business,
            $dayClosing->closing_date->toDateString(),
            $dayClosing,
            (int) Auth::id()
        );

        try {
            $this->staffSms->notifyStaffHandoverVerified(
                $dayClosing->business,
                Auth::user(),
                $dayClosing->fresh(['user'])
            );
            $this->staffMail->notifyStaffHandoverVerified(
                $dayClosing->business,
                Auth::user(),
                $dayClosing->fresh(['user'])
            );
        } catch (\Throwable) {
            // Non-blocking
        }

        $redirect = redirect()->to($this->bossReconciliationUrl($dayClosing));

        if ($moneyShort > 0) {
            return $redirect->with(
                'success',
                ($finalizedReport
                    ? 'Handover verified with a money short of '.money($moneyShort).'. Day finalized and posted to the Master Sheet.'
                    : 'Handover verified with a money short of '.money($moneyShort).'. Posted to the Master Sheet.')
            )->with('info', 'View all money shorts on the Money Shorts page.');
        }

        return $redirect->with(
            'success',
            $finalizedReport
                ? 'Handover verified and day finalized. Debt, profit, and circulation are posted to the Master Sheet.'
                : 'Handover verified. Debt, profit, and circulation are posted to the Master Sheet.'
        );
    }

    public function postOwnerDirectSales(Request $request)
    {
        if (! $this->isBossReviewMode() || Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can post direct POS sales to the Master Sheet.');
        }

        $request->validate([
            'closing_date' => 'required|date',
        ]);

        $businessId = $this->currentBusinessId();
        $date = $request->closing_date;

        if (DayClosing::where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->whereNull('shift_id')
            ->where('user_id', Auth::id())
            ->exists()) {
            return redirect()->route('day-closing.index', ['date' => $date])
                ->with('error', 'Your direct POS sales for this date are already posted.');
        }

        $summary = $this->buildOwnerDirectSummary($businessId, $date);

        if (($summary['sales_count'] ?? 0) === 0) {
            return redirect()->route('day-closing.index', ['date' => $date])
                ->with('error', 'No direct POS sales found for this date.');
        }

        $platformBreakdown = $this->buildPlatformBreakdown(
            $businessId,
            $date,
            null,
            Auth::id(),
            $summary['sales']
        );

        $paymentBreakdown = collect($platformBreakdown)->mapWithKeys(fn ($item, $key) => [$key => (float) $item['amount']])->all();
        $cashReceived = (float) ($paymentBreakdown['cash'] ?? 0);
        $mobileReceived = collect($paymentBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'mobile_money')->sum();
        $bankReceived = collect($platformBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'bank')->sum();
        $expected = array_sum($paymentBreakdown);
        $actual = (float) $request->input('actual_received', $expected);
        $moneyShort = max(0, round($expected - $actual, 2));

        $request->validate([
            'report_notes' => 'nullable|string|max:2000',
            'actual_received' => 'required|numeric|min:0',
            'shortage_note' => [
                Rule::requiredIf($moneyShort > 0),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $ownerShiftIds = collect($summary['sales'])
            ->pluck('shift_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::beginTransaction();

        try {
            $this->closeOwnerShiftsForDirectPost($businessId, $ownerShiftIds);

            $closing = DayClosing::create([
                'business_id' => $businessId,
                'user_id' => Auth::id(),
                'shift_id' => null,
                'closing_date' => $date,
                'status' => 'verified',
                'sales_count' => $summary['sales_count'],
                'gross_sales' => $summary['gross_sales'],
                'amount_collected' => $summary['amount_collected'],
                'outstanding_sales' => $summary['outstanding_sales'],
                'payments_received' => $expected,
                'cash_received' => $cashReceived,
                'mobile_received' => $mobileReceived,
                'bank_received' => $bankReceived,
                'payment_breakdown' => $paymentBreakdown,
                'cancelled_sales' => $summary['cancelled_sales'],
                'total_expenses' => 0,
                'net_amount' => $expected,
                'expected_handover' => $expected,
                'actual_received' => $actual,
                'money_short' => $moneyShort,
                'shortage_note' => $moneyShort > 0 ? $request->shortage_note : null,
                'report_notes' => $request->report_notes,
                'submitted_at' => now(),
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            $closing->load(['expenses', 'user', 'business']);
            $this->reportService->syncReport($closing->business, $date, $closing);
            $finalizedReport = $this->reportService->tryFinalizeDayIfReady(
                $closing->business,
                $date,
                $closing,
                (int) Auth::id()
            );

            DB::commit();

            $redirect = redirect()->to(route('day-closing.index', ['date' => $date]).'#owner-day-close');

            if ($moneyShort > 0) {
                return $redirect
                    ->with('success', ($finalizedReport
                        ? 'Your direct POS sales are posted with a money short of '.money($moneyShort).'. Day finalized on the Master Sheet.'
                        : 'Your direct POS sales are posted with a money short of '.money($moneyShort).'.'))
                    ->with('info', 'View all money shorts on the Money Shorts page.');
            }

            return $redirect
                ->with('success', $finalizedReport
                    ? 'Your direct POS sales are posted and the day is finalized on the Master Sheet.'
                    : 'Your direct POS sales are posted to the Master Sheet.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('day-closing.index', ['date' => $date])
                ->with('error', 'Failed to post sales: '.$e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $this->authorizeAny(['view_closing_history', 'view_reports', 'verify_day_closing']);

        $filter = $this->branchBusinessFilterContext($request);
        extract($filter);
        $settlementService = app(\App\Services\MoneyShortSettlementService::class);

        $closingsQuery = DayClosing::where('business_id', Auth::user()->business_id)
            ->with(['user', 'verifier', 'shift']);

        $this->scopeDayClosingsForActiveBranch($closingsQuery);

        if ($activeBusinessType) {
            $settlementService->scopeClosingsForBusinessType($closingsQuery, Auth::user()->business_id, $activeBusinessType);
        }

        $closings = $closingsQuery->latest('closing_date')->paginate(20)->withQueryString();

        $closings->getCollection()->transform(function (DayClosing $closing) use ($settlementService, $business) {
            $closing->business_type_keys = $settlementService->closingBusinessTypeKeys($closing);
            $closing->business_type_labels = collect($closing->business_type_keys)
                ->map(fn ($key) => $business->businessTypeLabel($key))
                ->values()
                ->all();

            return $closing;
        });

        return view('day-closing.history', compact('closings') + $filter);
    }

    public function show(DayClosing $dayClosing)
    {
        if ($dayClosing->business_id != Auth::user()->business_id) {
            abort(403);
        }

        if (! $this->actsAsBusinessWideViewer() && ! Auth::user()->can('verify_day_closing')) {
            if (! Auth::user()->can('submit_day_closing') || $dayClosing->user_id !== Auth::id()) {
                abort(403, 'You can only view reconciliations you submitted.');
            }
        }

        $this->authorizeAny([
            'view_reports',
            'verify_day_closing',
            'submit_day_closing',
            'view_closing_history',
        ]);

        if ($this->isBossReviewMode()) {
            return redirect()->to($this->bossReconciliationUrl($dayClosing));
        }

        $card = $this->buildHandoverCardData($dayClosing);

        return view('day-closing.show', $card);
    }

    private function bossReconciliationUrl(DayClosing $dayClosing): string
    {
        return route('day-closing.index', [
            'date' => $dayClosing->closing_date->toDateString(),
        ]).'#handover-'.$dayClosing->id;
    }

    private function sortHandoversForBossReview($closings)
    {
        return collect($closings)->sort(function (DayClosing $a, DayClosing $b) {
            $statusOrder = fn (DayClosing $closing) => match ($closing->status) {
                'submitted' => 0,
                'disputed' => 1,
                default => 2,
            };

            if ($statusOrder($a) !== $statusOrder($b)) {
                return $statusOrder($a) <=> $statusOrder($b);
            }

            $submittedA = $a->submitted_at?->timestamp ?? PHP_INT_MAX;
            $submittedB = $b->submitted_at?->timestamp ?? PHP_INT_MAX;

            if ($submittedA !== $submittedB) {
                return $submittedA <=> $submittedB;
            }

            return $a->id <=> $b->id;
        })->values();
    }

    private function nextPendingHandoverToVerify(int $businessId): ?DayClosing
    {
        $query = DayClosing::where('business_id', $businessId)
            ->where('status', 'submitted')
            ->with(['shift', 'user']);

        $this->scopeDayClosingsForActiveBranch($query);

        return $this->sortHandoversForBossReview($query->get())->first();
    }

    private function buildHandoverCardsForBossReview($dayHandovers): \Illuminate\Support\Collection
    {
        $sorted = $dayHandovers
            ->filter(fn (DayClosing $closing) => ! $this->isOwnerDirectClosing($closing))
            ->values();

        $pendingCount = $sorted->where('status', 'submitted')->count();
        $businessId = (int) ($sorted->first()?->business_id ?? current_business_id());
        $nextPendingId = $this->nextPendingHandoverToVerify($businessId)?->id;
        $queuePosition = 0;

        return $sorted->map(function (DayClosing $closing) use ($pendingCount, $nextPendingId, &$queuePosition) {
            $card = $this->buildHandoverCardData($closing);
            $card['canVerifyNow'] = $closing->status !== 'submitted' || $closing->id === $nextPendingId;

            if ($closing->status === 'submitted') {
                $queuePosition++;
                $card['verifyQueuePosition'] = $queuePosition;
                $card['verifyQueueTotal'] = $pendingCount;
            }

            return $card;
        });
    }

    private function buildHandoverCardData(DayClosing $dayClosing): array
    {
        $dayClosing->loadMissing(['expenses', 'user', 'verifier', 'business', 'shift']);

        $date = $dayClosing->closing_date->toDateString();
        $shift = $dayClosing->shift;
        $summary = $this->buildDaySummary(
            $dayClosing->business_id,
            $date,
            $shift,
            shiftOnly: (bool) $dayClosing->shift_id
        );
        $collectorUserId = $shift?->user_id;
        $shiftPaymentWindow = $this->resolveShiftPaymentWindow($shift);

        $staffRows = $this->buildStaffReconciliation($dayClosing->business_id, $date, $summary['sales'], $collectorUserId, $dayClosing->shift_id, $shiftPaymentWindow);
        $debtCollections = $this->buildDebtCollections($dayClosing->business_id, $date, $summary['sales'], $collectorUserId, $dayClosing->shift_id, $shiftPaymentWindow);
        $platformBreakdown = $this->buildPlatformBreakdown(
            $dayClosing->business_id,
            $date,
            $dayClosing->shift_id,
            $collectorUserId,
            $summary['sales'],
            $shiftPaymentWindow
        );
        $allDaySales = $this->resolveAllHandoverSalesViewData(
            $summary['sales'],
            $debtCollections,
            $dayClosing->business_id,
            $date,
            $dayClosing->shift_id,
            $shiftPaymentWindow,
            $collectorUserId
        );

        if ($snapshot = $dayClosing->handover_snapshot) {
            if (($snapshot['debt_collections']['count'] ?? 0) > 0) {
                $debtCollections = $snapshot['debt_collections'];
            }
            if (($snapshot['all_day_sales'] ?? []) !== []) {
                $allDaySales = $snapshot['all_day_sales'];
            }
        } elseif ($shiftPaymentWindow && $dayClosing->shift_id && ($debtCollections['count'] ?? 0) === 0) {
            $recomputedDebt = $this->buildDebtCollections(
                $dayClosing->business_id,
                $date,
                $summary['sales'],
                $collectorUserId,
                $dayClosing->shift_id,
                $shiftPaymentWindow
            );
            if (($recomputedDebt['count'] ?? 0) > 0) {
                $debtCollections = $recomputedDebt;
                $allDaySales = $this->resolveAllHandoverSalesViewData(
                    $summary['sales'],
                    $debtCollections,
                    $dayClosing->business_id,
                    $date,
                    $dayClosing->shift_id,
                    $shiftPaymentWindow,
                    $collectorUserId
                );
            }
        }

        $financeData = [];
        $canViewBossFinancials = $this->canViewBossFinancials($dayClosing);
        $canVerifyHandover = $this->isBusinessOwner();

        if ($canViewBossFinancials) {
            $financeData = $dayClosing->shift_id
                ? $this->reportService->buildShiftHandoverReviewData($dayClosing)
                : $this->reportService->buildReportData(
                    $dayClosing->business,
                    $date,
                    $dayClosing
                );

            if (! $dayClosing->shift_id) {
                $this->reportService->syncReport(
                    $dayClosing->business,
                    $date,
                    $dayClosing
                );
            }
        }

        $handoverSummary = $this->buildHandoverSummary($dayClosing, $debtCollections);
        $shiftStats = $this->resolveShiftHandoverStats($dayClosing, $summary['sales'], $debtCollections);

        if ($canViewBossFinancials && $dayClosing->shift_id) {
            $financeData['outstanding_debt'] = $shiftStats['unpaid'];
        }

        return compact(
            'dayClosing',
            'staffRows',
            'debtCollections',
            'platformBreakdown',
            'allDaySales',
            'financeData',
            'canViewBossFinancials',
            'canVerifyHandover',
            'handoverSummary',
            'shiftStats',
        );
    }

    private function resolveShiftHandoverStats(DayClosing $closing, $sales, array $debtCollections = []): array
    {
        $active = collect($sales)->where('payment_status', '!=', 'cancelled');
        $computedOrders = $active->count();
        $computedGross = (float) $active->sum('total_amount');
        $computedCollected = (float) $active->sum('amount_paid');
        $computedUnpaid = max(0, $computedGross - $computedCollected);
        $debtTotal = (float) ($debtCollections['total'] ?? 0);
        $debtCount = (int) ($debtCollections['count'] ?? 0);

        if ($computedOrders > 0) {
            return [
                'orders' => $computedOrders,
                'gross' => $computedGross,
                'collected' => $computedCollected,
                'unpaid' => $computedUnpaid,
                'handover' => (float) $closing->net_amount,
                'prior_shift_orders' => $debtCount,
                'prior_shift_collected' => $debtTotal,
            ];
        }

        $storedGross = (float) $closing->gross_sales;
        $storedCollected = (float) $closing->amount_collected;

        return [
            'orders' => (int) $closing->sales_count,
            'gross' => $storedGross,
            'collected' => $storedCollected,
            'unpaid' => max((float) $closing->outstanding_sales, max(0, $storedGross - $storedCollected)),
            'handover' => (float) $closing->net_amount,
            'prior_shift_orders' => $debtCount,
            'prior_shift_collected' => $debtTotal,
        ];
    }

    private function buildHandoverSummary(DayClosing $dayClosing, array $debtCollections): array
    {
        $finalHandover = (float) ($dayClosing->net_amount ?: collect($dayClosing->payment_breakdown ?? [])->sum());
        $expenses = (float) $dayClosing->total_expenses;

        return [
            'gross_collected' => $finalHandover + $expenses,
            'expenses' => $expenses,
            'final_handover' => $finalHandover,
            'debt_collected' => (float) ($debtCollections['total'] ?? 0),
        ];
    }

    private function isBusinessOwner(): bool
    {
        return Auth::user()->role === 'owner';
    }

    private function isOwnerDirectClosing(DayClosing $closing): bool
    {
        $closing->loadMissing('user');

        return $closing->shift_id === null && ($closing->user->role ?? '') === 'owner';
    }

    private function markOwnerStaffRowPosted(array $staffRows, DayClosing $ownerDirectClosing): array
    {
        return collect($staffRows)->map(function (array $row) use ($ownerDirectClosing) {
            if (($row['staff']->id ?? null) === (int) $ownerDirectClosing->user_id) {
                $row['status'] = 'posted';
            }

            return $row;
        })->all();
    }

    private function dayClosingBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            return (int) Auth::user()->branch_id;
        }

        return active_branch_id();
    }

    private function scopeDayClosingSales($query, int $businessId)
    {
        $query->where('business_id', $businessId);

        if ($this->actsAsBusinessWideViewer()) {
            if ($branchFilterId = $this->dayClosingBranchFilterId()) {
                $query->whereHas('items.item.category', function ($categoryQuery) use ($branchFilterId) {
                    $categoryQuery->where('branch_id', $branchFilterId);
                });
            }

            return $query;
        }

        return $query->where('user_id', Auth::id());
    }

    private function isBossReviewMode(): bool
    {
        return Auth::user()->can('view_boss_financial_review') && ! $this->requiresShiftHandover();
    }

    private function canViewBossFinancials(?DayClosing $dayClosing = null): bool
    {
        if (! Auth::user()->can('view_boss_financial_review')) {
            return false;
        }

        if ($dayClosing && $dayClosing->user_id === Auth::id() && Auth::user()->role !== 'owner') {
            return false;
        }

        return true;
    }

    private function requiresShiftHandover(): bool
    {
        if ($this->actsAsBusinessWideViewer()) {
            return false;
        }

        return Auth::user()->can('submit_day_closing') || Auth::user()->can('process_sales');
    }

    private function resolveStaffShiftContext(Request $request, int $businessId): array
    {
        $shiftId = $request->filled('shift')
            ? $request->integer('shift')
            : (($sessionShiftId = $request->session()->get('handover_shift_id')) ? (int) $sessionShiftId : null);

        $pendingShiftQuery = fn () => Shift::where('business_id', $businessId)
            ->where('user_id', Auth::id())
            ->whereDoesntHave('dayClosing');

        if ($shiftId) {
            $shift = $pendingShiftQuery()->where('id', $shiftId)->first();

            if ($shift) {
                return ['shift' => $shift, 'canSubmitHandover' => true];
            }
        }

        $openShift = Shift::openForUser(Auth::id(), $businessId);
        if ($openShift) {
            return ['shift' => $openShift, 'canSubmitHandover' => true];
        }

        $closedHandover = Shift::latestClosedAwaitingHandover(Auth::id(), $businessId);
        if ($closedHandover) {
            return ['shift' => $closedHandover, 'canSubmitHandover' => true];
        }

        return ['shift' => null, 'canSubmitHandover' => false];
    }

    private function buildOwnerDirectSummary(int $businessId, string $date): array
    {
        $ownerId = Auth::id();

        if (DayClosing::where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->whereNull('shift_id')
            ->where('user_id', $ownerId)
            ->exists()) {
            return [
                'sales_count' => 0,
                'gross_sales' => 0,
                'amount_collected' => 0,
                'outstanding_sales' => 0,
                'cancelled_sales' => 0,
                'sales' => collect(),
            ];
        }

        $shiftIdsWithClosing = DayClosing::where('business_id', $businessId)
            ->where('user_id', $ownerId)
            ->whereNotNull('shift_id')
            ->pluck('shift_id');

        $daySalesQuery = Sale::where('business_id', $businessId)
            ->whereDate('sale_date', $date)
            ->where('user_id', $ownerId);

        if ($shiftIdsWithClosing->isNotEmpty()) {
            $daySalesQuery->where(function ($query) use ($shiftIdsWithClosing) {
                $query->whereNull('shift_id')
                    ->orWhereNotIn('shift_id', $shiftIdsWithClosing);
            });
        }

        $this->scopeDayClosingSales($daySalesQuery, $businessId);

        $daySales = $daySalesQuery
            ->with(['user', 'payments', 'items.item.category', 'items.item.packagings'])
            ->orderBy('created_at')
            ->get();

        $activeSales = $daySales->where('payment_status', '!=', 'cancelled');
        $grossSales = $activeSales->sum('total_amount');
        $amountCollected = $activeSales->sum('amount_paid');

        return [
            'sales_count' => $activeSales->count(),
            'gross_sales' => $grossSales,
            'amount_collected' => $amountCollected,
            'outstanding_sales' => max(0, $grossSales - $amountCollected),
            'cancelled_sales' => $daySales->where('payment_status', 'cancelled')->count(),
            'sales' => $daySales,
        ];
    }

    private function closeOwnerShiftsForDirectPost(int $businessId, array $shiftIds): void
    {
        if ($shiftIds === []) {
            return;
        }

        Shift::where('business_id', $businessId)
            ->where('user_id', Auth::id())
            ->whereIn('id', $shiftIds)
            ->whereDoesntHave('dayClosing')
            ->each(function (Shift $shift) {
                if ($shift->isOpen()) {
                    $shift->refreshTotals();
                }

                if ($shift->status !== 'closed') {
                    $shift->update([
                        'status' => 'closed',
                        'closed_at' => $shift->closed_at ?? now(),
                    ]);
                }
            });
    }

    private function allocateDebtCollectionsByBusinessType(int $businessId, string $date, $daySales, ?int $currentShiftId = null, ?array $shiftPaymentWindow = null): array
    {
        $payments = $this->queryDebtCollectionPayments($businessId, $date, null, $currentShiftId, $shiftPaymentWindow);

        return $this->businessTypeBreakdown->allocateDebtFromPayments($payments);
    }

    private function resolveShiftPaymentWindow(?Shift $shift): ?array
    {
        if (! $shift?->opened_at) {
            return null;
        }

        $start = $shift->opened_at->copy();
        $end = ($shift->closed_at ?? now())->copy();

        if ($start->gt($end)) {
            $start = $shift->created_at?->copy() ?? $end->copy();
        }

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function scopePaymentsToShiftWindow($query, ?array $shiftPaymentWindow): void
    {
        if (! $shiftPaymentWindow) {
            return;
        }

        $query->where('created_at', '>=', $shiftPaymentWindow['start'])
            ->where('created_at', '<=', $shiftPaymentWindow['end']);
    }

    private function attachOrphanSalesToShift(Shift $shift, string $date): void
    {
        $query = Sale::where('business_id', $shift->business_id)
            ->whereNull('shift_id')
            ->where('user_id', $shift->user_id)
            ->whereDate('sale_date', $date)
            ->where('payment_status', '!=', 'cancelled');

        $this->scopeDayClosingSales($query, $shift->business_id);

        $query->update(['shift_id' => $shift->id]);
    }

    private function applyShiftHandoverSalesScope($query, Shift $shift, string $date): void
    {
        $query->where(function ($outer) use ($shift, $date) {
            $outer->where('shift_id', $shift->id)
                ->orWhere(function ($inner) use ($shift, $date) {
                    $inner->whereNull('shift_id')
                        ->where('user_id', $shift->user_id)
                        ->whereDate('sale_date', $date);
                });
        });
    }

    private function applyExcludeShiftsScope($query, ?array $excludeShiftIds): void
    {
        if ($excludeShiftIds === null || $excludeShiftIds === []) {
            return;
        }

        $excludeShiftIds = array_values(array_unique(array_map('intval', $excludeShiftIds)));

        $query->where(function ($q) use ($excludeShiftIds) {
            $q->whereNull('shift_id')
                ->orWhereNotIn('shift_id', $excludeShiftIds);
        });
    }

    private function buildDaySummary(
        int $businessId,
        string $date,
        Shift|int|null $shift = null,
        bool $shiftOnly = false,
        ?array $excludeShiftIds = null
    ): array {
        $shiftModel = $shift instanceof Shift ? $shift : (is_int($shift) ? Shift::find($shift) : null);
        $shiftId = $shiftModel?->id ?? (is_int($shift) ? $shift : null);
        $includeOrphanSales = $shift instanceof Shift && ! $shiftOnly;

        $daySalesQuery = Sale::where('business_id', $businessId);

        if ($shiftOnly && $shiftId) {
            $daySalesQuery->where('shift_id', $shiftId);
        } else {
            $daySalesQuery->whereDate('sale_date', $date);

            if ($shiftId && $includeOrphanSales && $shiftModel) {
                $this->applyShiftHandoverSalesScope($daySalesQuery, $shiftModel, $date);
            } elseif ($shiftId) {
                $daySalesQuery->where('shift_id', $shiftId);
            }
        }

        $this->scopeDayClosingSales($daySalesQuery, $businessId);
        if (! $shiftOnly) {
            $this->applyExcludeShiftsScope($daySalesQuery, $excludeShiftIds);
        }

        $daySales = $daySalesQuery
            ->with(['user', 'payments', 'items.item.category', 'items.item.packagings'])
            ->orderBy('created_at')
            ->get();

        $activeSales = $daySales->where('payment_status', '!=', 'cancelled');
        $cancelledSales = $daySales->where('payment_status', 'cancelled')->count();

        $grossSales = $activeSales->sum('total_amount');
        $amountCollected = $activeSales->sum('amount_paid');

        $paymentsQuery = SalePayment::whereHas('sale', function ($q) use ($businessId, $shiftId, $includeOrphanSales, $shiftModel, $date, $shiftOnly, $excludeShiftIds) {
            $q->where('business_id', $businessId);
            if ($shiftOnly && $shiftId) {
                $q->where('shift_id', $shiftId);
            } elseif ($shiftId && $includeOrphanSales && $shiftModel) {
                $this->applyShiftHandoverSalesScope($q, $shiftModel, $date);
            } elseif ($shiftId) {
                $q->where('shift_id', $shiftId);
            }
            $this->scopeDayClosingSales($q, $businessId);
            if (! $shiftOnly) {
                $this->applyExcludeShiftsScope($q, $excludeShiftIds);
            }
        });

        if (! $shiftOnly) {
            $paymentsQuery->whereDate('created_at', $date);
        }

        $paymentsReceived = (clone $paymentsQuery)->sum('amount');
        $cashReceived = (clone $paymentsQuery)->where('payment_method', 'cash')->sum('amount');
        $mobileReceived = (clone $paymentsQuery)->where('payment_method', 'mobile_money')->sum('amount');
        $bankReceived = (clone $paymentsQuery)->where('payment_method', 'bank')->sum('amount');

        return [
            'sales_count' => $activeSales->count(),
            'gross_sales' => $grossSales,
            'amount_collected' => $amountCollected,
            'outstanding_sales' => max(0, $grossSales - $amountCollected),
            'payments_received' => $paymentsReceived,
            'cash_received' => $cashReceived,
            'mobile_received' => $mobileReceived,
            'bank_received' => $bankReceived,
            'cancelled_sales' => $cancelledSales,
            'sales' => $daySales,
        ];
    }

    private function buildStaffReconciliation(int $businessId, string $date, $daySales, ?int $collectorUserId = null, ?int $currentShiftId = null, ?array $shiftPaymentWindow = null): array
    {
        $activeSales = $daySales->where('payment_status', '!=', 'cancelled');
        $shiftSaleIds = $activeSales->pluck('id')->all();

        $allDayPaymentsQuery = SalePayment::whereHas('sale', fn ($q) => $q->where('business_id', $businessId))
            ->with(['sale']);

        if ($shiftPaymentWindow) {
            $this->scopePaymentsToShiftWindow($allDayPaymentsQuery, $shiftPaymentWindow);
        } else {
            $allDayPaymentsQuery->whereDate('created_at', $date);
        }

        if ($collectorUserId) {
            $allDayPaymentsQuery->where('user_id', $collectorUserId);
        }

        $allDayPayments = $allDayPaymentsQuery->get();

        if ($collectorUserId) {
            $staffIds = collect([$collectorUserId]);
        } else {
            $staffIds = $activeSales->pluck('user_id')->unique();
        }

        $rows = [];

        foreach ($staffIds as $staffId) {
            $staffSales = $activeSales->where('user_id', $staffId);
            $staffSaleIds = $staffSales->pluck('id')->all();
            $staff = $staffSales->first()->user ?? User::find($staffId);

            $grossSales = $staffSales->sum('total_amount');
            $collectedOnOrders = $staffSales->sum('amount_paid');
            $credit = max(0, $grossSales - $collectedOnOrders);

            $staffPayments = $allDayPayments->where('user_id', $staffId);

            if ($collectorUserId) {
                $staffPayments = $staffPayments->filter(
                    fn (SalePayment $payment) => $this->paymentBelongsToShiftHandover($payment, $staffSaleIds, $date, $currentShiftId)
                );
            }

            $debtPayments = $staffPayments->filter(fn (SalePayment $payment) => $this->isDebtCollectionPayment($payment, $date, $currentShiftId));
            $shiftPayments = $staffPayments->reject(fn (SalePayment $payment) => $this->isDebtCollectionPayment($payment, $date, $currentShiftId));

            $cashCollected = $shiftPayments->where('payment_method', 'cash')->sum('amount');
            $mobileCollected = $shiftPayments->where('payment_method', 'mobile_money')->sum('amount');
            $bankCollected = $shiftPayments->where('payment_method', 'bank')->sum('amount');
            $shiftPaymentsTotal = $shiftPayments->sum('amount');
            $debtCollected = $debtPayments->sum('amount');

            $paidCount = $staffSales->where('payment_status', 'paid')->count();
            $partialCount = $staffSales->where('payment_status', 'partial')->count();
            $debtCount = $staffSales->whereIn('payment_status', ['debt', 'pending'])->count();

            if ($debtCount > 0) {
                $status = 'pending';
            } elseif ($partialCount > 0) {
                $status = 'partial';
            } elseif ($paidCount === $staffSales->count() && $staffSales->count() > 0) {
                $status = 'paid';
            } elseif ($debtCollected > 0 && $staffSales->isEmpty()) {
                $status = 'paid';
            } else {
                $status = 'pending';
            }

            $rows[] = [
                'staff' => $staff,
                'date' => $date,
                'total_orders' => $staffSales->count(),
                'gross_sales' => $grossSales,
                'expected_amount' => $grossSales,
                'collected_on_orders' => $collectedOnOrders,
                'cash_collected' => $cashCollected,
                'mobile_collected' => $mobileCollected,
                'bank_collected' => $bankCollected,
                'shift_payments_total' => $shiftPaymentsTotal,
                'debt_collected' => $debtCollected,
                'debt_payments' => $this->mapDebtPaymentRows($debtPayments),
                'payments_recorded' => $shiftPaymentsTotal + $debtCollected,
                'credit' => $credit,
                'difference' => $shiftPaymentsTotal - $collectedOnOrders,
                'status' => $status,
                'sales' => $staffSales,
            ];
        }

        if (! $collectorUserId) {
            $staffWithDebtOnly = $allDayPayments
                ->filter(fn (SalePayment $payment) => $this->isDebtCollectionPayment($payment, $date, $currentShiftId))
                ->pluck('user_id')
                ->unique()
                ->diff($staffIds);

            foreach ($staffWithDebtOnly as $staffId) {
                $staff = User::find($staffId);
                $debtPayments = $allDayPayments
                    ->where('user_id', $staffId)
                    ->filter(fn (SalePayment $payment) => $this->isDebtCollectionPayment($payment, $date, $currentShiftId));

                $rows[] = [
                    'staff' => $staff,
                    'date' => $date,
                    'total_orders' => 0,
                    'gross_sales' => 0,
                    'expected_amount' => 0,
                    'collected_on_orders' => 0,
                    'cash_collected' => 0,
                    'mobile_collected' => 0,
                    'bank_collected' => 0,
                    'shift_payments_total' => 0,
                    'debt_collected' => $debtPayments->sum('amount'),
                    'debt_payments' => $this->mapDebtPaymentRows($debtPayments),
                    'payments_recorded' => $debtPayments->sum('amount'),
                    'credit' => 0,
                    'difference' => 0,
                    'status' => 'paid',
                    'sales' => collect(),
                ];
            }
        }

        return collect($rows)->sortBy(fn ($r) => $r['staff']->name ?? '')->values()->all();
    }

    private function buildDebtCollections(int $businessId, string $date, $daySales, ?int $collectorUserId = null, ?int $currentShiftId = null, ?array $shiftPaymentWindow = null): array
    {
        $payments = $this->queryDebtCollectionPayments($businessId, $date, $collectorUserId, $currentShiftId, $shiftPaymentWindow);

        return [
            'total' => (float) $payments->sum('amount'),
            'count' => $payments->count(),
            'items' => $this->mapDebtPaymentRows($payments),
        ];
    }

    private function paymentBelongsToShiftHandover(SalePayment $payment, array $shiftSaleIds, string $date, ?int $currentShiftId = null): bool
    {
        if (in_array($payment->sale_id, $shiftSaleIds, true)) {
            return true;
        }

        return $this->isDebtCollectionPayment($payment, $date, $currentShiftId);
    }

    private function isDebtCollectionPayment(SalePayment $payment, string $date, ?int $currentShiftId = null): bool
    {
        $payment->loadMissing(['sale.payments']);
        $sale = $payment->sale;

        if (! $sale || $sale->payment_status === 'cancelled') {
            return false;
        }

        if (Carbon::parse($payment->created_at)->toDateString() !== $date) {
            return false;
        }

        $saleDate = Carbon::parse($sale->sale_date)->toDateString();

        if ($currentShiftId && $sale->shift_id && (int) $sale->shift_id !== (int) $currentShiftId) {
            return true;
        }

        if ($saleDate < $date) {
            return true;
        }

        if ($saleDate !== $date) {
            return false;
        }

        $firstPayment = $sale->payments
            ->sortBy(fn (SalePayment $row) => [$row->created_at?->timestamp ?? 0, $row->id])
            ->first();

        return $firstPayment && (int) $firstPayment->id !== (int) $payment->id;
    }

    private function queryDebtCollectionPayments(int $businessId, string $date, ?int $collectorUserId = null, ?int $currentShiftId = null, ?array $shiftPaymentWindow = null)
    {
        $query = SalePayment::query();

        if ($shiftPaymentWindow) {
            $this->scopePaymentsToShiftWindow($query, $shiftPaymentWindow);
        } else {
            $query->whereDate('created_at', $date);
        }

        $query->whereHas('sale', function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->where('payment_status', '!=', 'cancelled');
                $this->scopeDayClosingSales($q, $businessId);
            })
            ->with(['sale.payments', 'user'])
            ->latest();

        if ($collectorUserId) {
            $query->where('user_id', $collectorUserId);
        }

        return $query->get()->filter(
            fn (SalePayment $payment) => $this->isDebtCollectionPayment($payment, $date, $currentShiftId)
        )->values();
    }

    private function mapDebtPaymentRows($payments): array
    {
        return collect($payments)->map(function (SalePayment $payment) {
            $sale = $payment->sale;

            return [
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'provider' => $payment->payment_provider,
                'reference' => $payment->transaction_reference,
                'collected_at' => $payment->created_at->format('M d, Y h:i A'),
                'collected_by' => $payment->user->name ?? '—',
                'sale_ref' => $sale->reference_no ?? '—',
                'sale_date' => $sale?->sale_date ? Carbon::parse($sale->sale_date)->format('M d, Y') : '—',
                'customer' => $sale->customer_name ?? '—',
                'customer_phone' => $sale->customer_phone ?? '',
            ];
        })->values()->all();
    }

    private function buildPlatformBreakdown(int $businessId, string $date, ?int $shiftId = null, ?int $collectorUserId = null, $daySales = null, ?array $shiftPaymentWindow = null): array
    {
        $shiftSaleIds = $daySales
            ? $daySales->where('payment_status', '!=', 'cancelled')->pluck('id')->all()
            : [];

        $paymentsQuery = SalePayment::query();

        if ($shiftPaymentWindow) {
            $this->scopePaymentsToShiftWindow($paymentsQuery, $shiftPaymentWindow);
        } else {
            $paymentsQuery->whereDate('created_at', $date);
        }

        $payments = $paymentsQuery
            ->whereHas('sale', function ($q) use ($businessId, $shiftId, $shiftSaleIds, $collectorUserId, $date) {
                $q->where('business_id', $businessId)
                    ->where('payment_status', '!=', 'cancelled');

                if ($shiftId && $collectorUserId) {
                    $q->where(function ($inner) use ($shiftId, $collectorUserId, $date) {
                        $inner->where('shift_id', $shiftId)
                            ->orWhere(function ($sameDay) use ($collectorUserId, $date) {
                                $sameDay->whereNull('shift_id')
                                    ->where('user_id', $collectorUserId)
                                    ->whereDate('sale_date', $date);
                            });
                    });
                } elseif ($shiftId) {
                    $q->where('shift_id', $shiftId);
                } elseif ($shiftSaleIds !== []) {
                    $q->whereIn('id', $shiftSaleIds);
                }

                $this->scopeDayClosingSales($q, $businessId);
            })
            ->when($collectorUserId, fn ($query) => $query->where('user_id', $collectorUserId))
            ->get();

        $breakdown = [];

        foreach ($payments as $payment) {
            $key = $this->resolvePlatformKey($payment);
            if (! isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'label' => $this->resolvePlatformLabel($payment),
                    'method' => $payment->payment_method,
                    'amount' => 0,
                ];
            }
            $breakdown[$key]['amount'] += (float) $payment->amount;
        }

        if ($shiftId && $collectorUserId) {
            $externalDebtPayments = $this->queryDebtCollectionPayments($businessId, $date, $collectorUserId, $shiftId, $shiftPaymentWindow)
                ->reject(fn (SalePayment $payment) => in_array($payment->sale_id, $shiftSaleIds, true));

            foreach ($externalDebtPayments as $payment) {
                $key = $this->resolvePlatformKey($payment);
                if (! isset($breakdown[$key])) {
                    $breakdown[$key] = [
                        'label' => $this->resolvePlatformLabel($payment),
                        'method' => $payment->payment_method,
                        'amount' => 0,
                    ];
                }
                $breakdown[$key]['amount'] += (float) $payment->amount;
            }
        }

        uasort($breakdown, function ($a, $b) {
            $order = ['cash' => 0, 'mobile_money' => 1, 'bank' => 2];
            $aOrder = $order[$a['method']] ?? 3;
            $bOrder = $order[$b['method']] ?? 3;
            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $breakdown;
    }

    private function resolveAllHandoverSalesViewData(
        $shiftSales,
        array $debtCollections,
        int $businessId,
        string $date,
        ?int $currentShiftId,
        ?array $shiftPaymentWindow,
        ?int $collectorUserId
    ): array {
        $sales = collect($shiftSales)->where('payment_status', '!=', 'cancelled')->values();

        if ($currentShiftId && $shiftPaymentWindow && ($debtCollections['count'] ?? 0) > 0) {
            $payments = $this->queryDebtCollectionPayments(
                $businessId,
                $date,
                $collectorUserId,
                $currentShiftId,
                $shiftPaymentWindow
            );
            $carriedIds = $payments->pluck('sale_id')->unique()->diff($sales->pluck('id'));

            if ($carriedIds->isNotEmpty()) {
                $carried = Sale::query()
                    ->whereIn('id', $carriedIds)
                    ->with(['user', 'payments'])
                    ->get();

                $sales = $sales->merge($carried);
            }
        }

        return $this->buildDaySalesViewData(
            $sales->sortBy('created_at')->values(),
            $currentShiftId,
            $shiftPaymentWindow
        );
    }

    private function buildDaySalesViewData($daySales, ?int $currentShiftId = null, ?array $shiftPaymentWindow = null): array
    {
        return collect($daySales)
            ->where('payment_status', '!=', 'cancelled')
            ->map(function ($sale) use ($currentShiftId, $shiftPaymentWindow) {
                $isCarriedOver = $currentShiftId
                    && $sale->shift_id
                    && (int) $sale->shift_id !== (int) $currentShiftId;

                $payments = $sale->payments;
                if ($isCarriedOver && $shiftPaymentWindow) {
                    $payments = $payments->filter(function ($payment) use ($shiftPaymentWindow) {
                        if (! $payment->created_at) {
                            return false;
                        }

                        return $payment->created_at->gte($shiftPaymentWindow['start'])
                            && $payment->created_at->lte($shiftPaymentWindow['end']);
                    });
                }

                $paymentRows = $payments->map(fn ($p) => [
                    'method' => $p->payment_method,
                    'provider' => $p->payment_provider,
                    'amount' => (float) $p->amount,
                    'reference' => $p->transaction_reference,
                    'time' => $p->created_at?->format('h:i A'),
                ])->values()->all();

                $shiftCollected = $isCarriedOver ? (float) collect($paymentRows)->sum('amount') : null;

                return [
                    'ref' => $sale->reference_no,
                    'sale_txn_ref' => $sale->transaction_reference,
                    'cashier' => $sale->user->name ?? 'Unknown',
                    'total' => (float) $sale->total_amount,
                    'paid' => (float) $sale->amount_paid,
                    'balance' => max(0, (float) $sale->total_amount - (float) $sale->amount_paid),
                    'status' => $sale->payment_status,
                    'time' => $sale->created_at->format('h:i A'),
                    'customer' => $sale->customer_name,
                    'payments' => $paymentRows,
                    'carried_over' => $isCarriedOver,
                    'origin_shift_id' => $isCarriedOver ? (int) $sale->shift_id : null,
                    'shift_collected' => $shiftCollected,
                ];
            })
            ->values()
            ->all();
    }

    private function resolvePlatformKey(SalePayment $payment): string
    {
        if ($payment->payment_method === 'cash') {
            return 'cash';
        }

        $provider = strtolower(trim($payment->payment_provider ?? ''));
        if ($provider !== '') {
            return str_replace([' ', '-', '.', '/'], '_', $provider);
        }

        return $payment->payment_method === 'bank' ? 'bank_other' : 'mobile_other';
    }

    private function resolvePlatformLabel(SalePayment $payment): string
    {
        if ($payment->payment_method === 'cash') {
            return 'Physical Cash';
        }

        if ($payment->payment_provider) {
            return $payment->payment_provider;
        }

        return $payment->payment_method === 'bank' ? 'Bank Transfer' : 'Mobile Money';
    }

    private function applyExpensesToBreakdown(array $paymentBreakdown, $expenses, array $platformBreakdown): array
    {
        foreach ($expenses as $expense) {
            $key = $expense['payment_method'] ?? 'cash';
            $amount = (float) ($expense['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            if (isset($paymentBreakdown[$key])) {
                $paymentBreakdown[$key] = max(0, (float) $paymentBreakdown[$key] - $amount);
                continue;
            }

            // Legacy/generic keys mapped to cash bucket
            if (in_array($key, ['cash', 'mobile_money', 'bank'], true)) {
                if ($key === 'cash' && isset($paymentBreakdown['cash'])) {
                    $paymentBreakdown['cash'] = max(0, (float) $paymentBreakdown['cash'] - $amount);
                } elseif ($key === 'mobile_money') {
                    foreach ($paymentBreakdown as $k => $val) {
                        if ($this->platformMethod($k, $platformBreakdown) === 'mobile_money' && $val >= $amount) {
                            $paymentBreakdown[$k] = max(0, (float) $val - $amount);
                            break;
                        }
                    }
                } elseif ($key === 'bank') {
                    foreach ($paymentBreakdown as $k => $val) {
                        if ($this->platformMethod($k, $platformBreakdown) === 'bank' && $val >= $amount) {
                            $paymentBreakdown[$k] = max(0, (float) $val - $amount);
                            break;
                        }
                    }
                }
            }
        }

        return $paymentBreakdown;
    }

    private function platformMethod(string $key, array $platformBreakdown): string
    {
        if ($key === 'cash') {
            return 'cash';
        }

        return $platformBreakdown[$key]['method'] ?? (
            str_contains($key, 'bank') || in_array($key, ['crdb', 'nmb', 'kcb', 'equity', 'nbc', 'dtb'])
                ? 'bank'
                : 'mobile_money'
        );
    }
}
