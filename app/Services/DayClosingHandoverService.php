<?php

namespace App\Services;

use App\Http\Controllers\DayClosingController;
use App\Models\DayClosing;
use App\Models\DayClosingExpense;
use App\Models\OwnerDailyReport;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DayClosingHandoverService extends DayClosingController
{
    protected ?int $apiBusinessId = null;

    public function forBusiness(int $businessId): self
    {
        $this->apiBusinessId = $businessId;

        return $this;
    }

    protected function currentBusinessId(): int
    {
        return $this->apiBusinessId ?? parent::currentBusinessId();
    }

    /**
     * @return array<string, mixed>
     */
    public function useHandoverContext(?string $context): self
    {
        $this->serviceHandoverContext = $context === 'services';

        return $this;
    }

    /**
     * Mirrors the staff view of /day-closing?shift={id} (Handover to Boss form).
     *
     * @return array<string, mixed>
     */
    public function buildPreview(User $user, int $businessId, ?int $shiftId = null, ?string $date = null): array
    {
        $this->forBusiness($businessId);
        $requiresShift = $this->userRequiresShiftHandover($user);

        $shift = null;
        if ($shiftId) {
            $shift = Shift::query()
                ->where('id', $shiftId)
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->first();

            if (! $shift) {
                $this->failWithCode('SHIFT_NOT_FOUND', 'shift_id', 'Shift not found or it does not belong to you.');
            }
        } elseif ($requiresShift) {
            $shift = $this->resolvePendingShiftForUser($user, $businessId);
        }

        if ($requiresShift && ! $shift) {
            $this->failWithCode('NO_SHIFT', 'shift_id', 'No shift available for handover. Open a shift from Sales Shifts first.');
        }

        $date = $shift
            ? ($shift->opened_at?->toDateString() ?? $shift->closed_at?->toDateString() ?? now()->toDateString())
            : ($date ?: now()->toDateString());

        $existingClosing = $this->existingClosingFor($businessId, $date, $shift);

        if ($shift?->isOpen() && ! $existingClosing) {
            $this->attachOrphanSalesToShift($shift, $date);
            $shift->refreshTotals();
        }

        $summary = $this->buildDaySummary($businessId, $date, $shift);
        $shiftPaymentWindow = $this->resolveShiftPaymentWindow($shift);
        $collectorUserId = $shift?->user_id;
        $currentShiftId = $shift?->id;
        $platformBreakdown = $this->buildPlatformBreakdown(
            $businessId,
            $date,
            $currentShiftId,
            $collectorUserId,
            $summary['sales'],
            $shiftPaymentWindow
        );
        $debtCollections = $this->buildDebtCollections($businessId, $date, $summary['sales'], $collectorUserId, $currentShiftId, $shiftPaymentWindow);
        $staffRows = $this->buildStaffReconciliation($businessId, $date, $summary['sales'], $collectorUserId, $currentShiftId, $shiftPaymentWindow);
        $allDaySales = $this->resolveAllHandoverSalesViewData(
            $summary['sales'],
            $debtCollections,
            $businessId,
            $date,
            $currentShiftId,
            $shiftPaymentWindow,
            $collectorUserId
        );

        $platforms = collect($platformBreakdown);
        $expectedHandover = (float) $platforms->sum('amount');
        $totalCash = (float) ($platformBreakdown['cash']['amount'] ?? 0);
        $totalMobile = (float) $platforms->filter(fn ($p) => ($p['method'] ?? '') === 'mobile_money')->sum('amount');
        $totalBank = (float) $platforms->filter(fn ($p) => ($p['method'] ?? '') === 'bank')->sum('amount');

        $canSubmit = ! $existingClosing && $user->role !== 'owner';
        $cannotSubmitReason = null;
        if ($existingClosing) {
            $cannotSubmitReason = $shift
                ? 'Handover for this shift was already submitted.'
                : 'This day has already been closed.';
        } elseif ($user->role === 'owner') {
            $cannotSubmitReason = 'Business owners verify staff handovers — they do not submit their own handover.';
        }

        $expenseSources = $platforms->map(fn ($p, $key) => ['key' => $key, 'label' => $p['label']])->values()->all();
        if ($expenseSources === []) {
            $expenseSources = [['key' => 'cash', 'label' => 'Physical Cash']];
        }

        return [
            'context' => $this->serviceHandoverContext ? 'services' : 'retail',
            'handover_mode' => ($shift && $this->isCashierCollector($shift->user_id)) ? 'cashier' : 'sales',
            'closing_date' => $date,
            'display_date' => \Carbon\Carbon::parse($date)->format('l, F d, Y'),
            'shift' => $shift ? $this->shiftPayload($shift) + [
                'is_open' => $shift->isOpen(),
                'banner' => $shift->isOpen()
                    ? 'Shift #'.$shift->id.' open since '.$shift->opened_at?->format('M d, Y h:i A')
                    : 'Shift #'.$shift->id.' closed at '.$shift->closed_at?->format('M d, Y h:i A'),
            ] : null,
            'stats' => [
                'active_staff' => count($staffRows),
                'gross_sales' => (float) $summary['gross_sales'],
                'total_cash' => $totalCash,
                'digital_and_bank' => $totalMobile + $totalBank,
            ],
            'summary' => [
                'sales_count' => (int) $summary['sales_count'],
                'gross_sales' => (float) $summary['gross_sales'],
                'amount_collected' => (float) $summary['amount_collected'],
                'outstanding_sales' => (float) $summary['outstanding_sales'],
                'cancelled_sales' => (int) $summary['cancelled_sales'],
            ],
            'staff_reconciliation' => collect($staffRows)->map(fn (array $row) => [
                'staff' => [
                    'id' => $row['staff']?->id,
                    'name' => $row['staff']->name ?? 'Unknown',
                    'email' => $row['staff']->email ?? '',
                ],
                'orders' => (int) $row['total_orders'],
                'gross_sales' => (float) $row['gross_sales'],
                'cash' => (float) $row['cash_collected'],
                'mobile' => (float) $row['mobile_collected'],
                'bank' => (float) $row['bank_collected'],
                'debt_paid' => (float) ($row['debt_collected'] ?? 0),
                'expected' => (float) $row['expected_amount'],
                'collected' => (float) $row['collected_on_orders'],
                'collected_by_others' => (float) ($row['collected_by_others'] ?? 0),
                'is_cashier' => (bool) ($row['is_cashier'] ?? false),
                'credit' => (float) $row['credit'],
                'difference' => round((float) $row['difference'], 2),
                'status' => $row['status'],
            ])->values()->all(),
            'debt_collections' => [
                'total' => (float) ($debtCollections['total'] ?? 0),
                'count' => (int) ($debtCollections['count'] ?? 0),
                'items' => array_values($debtCollections['items'] ?? []),
            ],
            'sales' => $allDaySales,
            'sales_count_all' => count($allDaySales),
            'platform_breakdown' => $platforms->map(fn ($row, $key) => [
                'key' => $key,
                'label' => $row['label'],
                'method' => $row['method'],
                'amount' => (float) $row['amount'],
            ])->values()->all(),
            'handover_summary' => [
                'total_cash' => $totalCash,
                'mobile_money' => $totalMobile,
                'bank' => $totalBank,
                'gross_collections' => $expectedHandover,
            ],
            'expected_handover' => $expectedHandover,
            'expense_sources' => $expenseSources,
            'platform_amounts_locked' => true,
            'can_submit' => $canSubmit,
            'cannot_submit_reason' => $cannotSubmitReason,
            'existing_handover' => $existingClosing ? [
                'id' => $existingClosing->id,
                'status' => $existingClosing->status,
                'submitted_at' => $existingClosing->submitted_at?->toIso8601String(),
                'api_detail' => '/api/v1/day-closing/'.$existingClosing->id,
            ] : null,
            'submit' => [
                'label' => ($shift && $shift->isOpen()) ? 'Submit Handover & Close Shift' : 'Submit Handover to Boss',
                'closes_shift' => (bool) ($shift?->isOpen()),
                'payload' => array_filter([
                    'closing_date' => $date,
                    'shift_id' => $shift?->id,
                    'handover_context' => $this->serviceHandoverContext ? 'services' : null,
                ], fn ($v) => $v !== null),
            ],
        ];
    }

    protected function resolvePendingShiftForUser(User $user, int $businessId): ?Shift
    {
        $openShift = Shift::openForUser($user->id, $businessId);
        if ($openShift) {
            $closed = DayClosing::where('shift_id', $openShift->id);
            $this->applyOwnerDirectClosingScope($closed);
            if (! $closed->exists()) {
                return $openShift;
            }
        }

        return Shift::where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('status', 'closed')
            ->whereDoesntHave('dayClosing', fn ($q) => $this->applyOwnerDirectClosingScope($q))
            ->latest('closed_at')
            ->first();
    }

    protected function existingClosingFor(int $businessId, string $date, ?Shift $shift): ?DayClosing
    {
        $query = $shift
            ? DayClosing::where('shift_id', $shift->id)
            : DayClosing::where('business_id', $businessId)->whereDate('closing_date', $date)->whereNull('shift_id');

        $this->applyOwnerDirectClosingScope($query);

        return $query->first();
    }

    /**
     * @return never
     */
    protected function failWithCode(string $code, string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message, 'code' => $code]);
    }

    public function submitHandover(User $user, int $businessId, array $data): DayClosing
    {
        $this->forBusiness($businessId);

        if ($user->role === 'owner') {
            $this->failWithCode('OWNER_CANNOT_SUBMIT', 'handover', 'Business owners verify staff handovers on mobile — they do not submit their own shift handover here.');
        }

        $validated = validator($data, [
            'closing_date' => 'required|date',
            'shift_id' => 'nullable|exists:shifts,id',
            'report_notes' => 'nullable|string|max:2000',
            'platform_amounts' => 'nullable|array',
            'platform_amounts.*' => 'nullable|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.description' => 'required_with:expenses|string|max:255',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0.01',
            'expenses.*.payment_method' => 'nullable|string|max:50',
        ])->validate();

        $date = $validated['closing_date'];
        $shift = null;

        if ($this->userRequiresShiftHandover($user)) {
            if (empty($validated['shift_id'])) {
                $this->failWithCode('SHIFT_REQUIRED', 'shift_id', 'Shift is required for handover.');
            }

            $shift = Shift::where('id', $validated['shift_id'])
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->whereIn('status', ['open', 'closed'])
                ->first();

            if (! $shift) {
                $this->failWithCode('SHIFT_NOT_FOUND', 'shift_id', 'Shift not found or it does not belong to you.');
            }

            if ($this->existingClosingFor($businessId, $date, $shift)) {
                $this->failWithCode('ALREADY_SUBMITTED', 'shift_id', 'Handover for this shift was already submitted.');
            }

            $date = $shift->opened_at?->toDateString() ?? $shift->closed_at?->toDateString() ?? $date;
        } elseif ($this->existingClosingFor($businessId, $date, null)) {
            $this->failWithCode('DAY_ALREADY_CLOSED', 'closing_date', 'This day has already been closed.');
        }

        if ($shift) {
            $this->attachOrphanSalesToShift($shift, $date);
        }

        $summary = $this->buildDaySummary($businessId, $date, $shift, shiftOnly: (bool) $shift);
        $expenses = collect($validated['expenses'] ?? [])->filter(fn ($e) => ! empty($e['description']) && ($e['amount'] ?? 0) > 0);
        $totalExpenses = (float) $expenses->sum('amount');
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

        $allowedSources = $platformBreakdown !== [] ? array_keys($platformBreakdown) : ['cash'];
        foreach ($expenses->values() as $index => $expense) {
            $source = $expense['payment_method'] ?? 'cash';
            if (! in_array($source, $allowedSources, true)) {
                throw ValidationException::withMessages([
                    "expenses.{$index}.payment_method" => 'Paid-from must be one of: '.implode(', ', $allowedSources).'.',
                    'code' => 'INVALID_EXPENSE_SOURCE',
                ]);
            }
        }

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
                $this->markShiftClosed($shift, now());
            } elseif ($user->requiresOpenShift()) {
                $openShift = Shift::openForUser($user->id, $businessId);
                if ($openShift) {
                    $this->markShiftClosed($openShift, now());
                    $shift = $openShift;
                }
            }

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
                'user_id' => $user->id,
                'shift_id' => $shift?->id,
                'handover_scope' => $this->ownerDirectHandoverScope(),
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
                'report_notes' => $validated['report_notes'] ?? null,
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

            $closing->load(['business', 'user', 'shift', 'expenses']);

            try {
                $biz = $closing->business;
                $this->staffSms->notifyHandoverSubmitted($biz, $user, $closing);
                $this->staffMail->notifyHandoverSubmitted($biz, $user, $closing);
                $this->salesReportEmail->sendOnShiftClose($biz, $user, $closing);
            } catch (\Throwable) {
                // non-blocking
            }

            try {
                app(InAppNotificationService::class)->notifyHandoverSubmitted($closing);
            } catch (\Throwable) {
                // non-blocking
            }

            return $closing;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Submitted staff handover ids in the order the owner must verify them.
     *
     * @return array<int, int>
     */
    public function verifyQueueIds(int $businessId): array
    {
        $query = DayClosing::where('business_id', $businessId)
            ->where('status', 'submitted')
            ->with(['shift', 'user']);

        $this->scopeDayClosingsForActiveBranch($query);

        return $this->sortHandoversForBossReview($query->get())
            ->filter(fn (DayClosing $closing) => ! $this->isOwnerDirectClosing($closing))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function verifyHandover(User $owner, DayClosing $dayClosing, array $data): DayClosing
    {
        if ($owner->role !== 'owner') {
            throw ValidationException::withMessages(['verify' => 'Only the business owner can verify staff handovers.']);
        }

        if ((int) $dayClosing->business_id !== (int) $this->forBusiness($dayClosing->business_id)->apiBusinessId) {
            throw ValidationException::withMessages(['verify' => 'Handover not found for this business.']);
        }

        if ($dayClosing->status === 'verified') {
            throw ValidationException::withMessages(['verify' => 'This reconciliation is already verified.']);
        }

        $isOwnerDirect = $this->isOwnerDirectClosing($dayClosing);

        if (! $isOwnerDirect) {
            $nextPending = $this->nextPendingHandoverToVerify($dayClosing->business_id);
            if ($nextPending && $nextPending->id !== $dayClosing->id) {
                throw ValidationException::withMessages([
                    'verify' => 'Verify the oldest pending handover first (#'.$nextPending->id.').',
                ]);
            }
        }

        $expected = (float) ($dayClosing->net_amount ?: collect($dayClosing->payment_breakdown ?? [])->sum());
        $actual = (float) ($data['actual_received'] ?? $expected);
        $moneyShort = max(0, round($expected - $actual, 2));

        validator($data, [
            'actual_received' => 'required|numeric|min:0',
            'shortage_note' => [Rule::requiredIf($moneyShort > 0), 'nullable', 'string', 'max:1000'],
            'dispute_reason' => 'nullable|string|max:1000',
        ])->validate();

        if (! empty($data['dispute_reason'])) {
            $dayClosing->update([
                'status' => 'disputed',
                'verified_by' => $owner->id,
                'verified_at' => now(),
                'dispute_reason' => $data['dispute_reason'],
                'expected_handover' => $expected,
                'actual_received' => $actual,
                'money_short' => $moneyShort,
                'shortage_note' => $moneyShort > 0 ? ($data['shortage_note'] ?? null) : null,
            ]);

            try {
                app(InAppNotificationService::class)->notifyHandoverVerified($dayClosing->fresh(['user', 'business']), rejected: true);
            } catch (\Throwable) {
                // non-blocking
            }

            return $dayClosing->fresh(['expenses', 'user', 'shift', 'verifier']);
        }

        $dayClosing->update([
            'status' => 'verified',
            'verified_by' => $owner->id,
            'verified_at' => now(),
            'dispute_reason' => null,
            'expected_handover' => $expected,
            'actual_received' => $actual,
            'money_short' => $moneyShort,
            'shortage_note' => $moneyShort > 0 ? ($data['shortage_note'] ?? null) : null,
        ]);

        $dayClosing->load(['expenses', 'user', 'business', 'shift']);
        $this->reportService->syncReport(
            $dayClosing->business,
            $dayClosing->closing_date->toDateString(),
            $dayClosing
        );
        // Do not auto-finalize — owner finalizes explicitly on Master Sheet / owner-reports.

        try {
            $this->staffSms->notifyStaffHandoverVerified($dayClosing->business, $owner, $dayClosing->fresh(['user']));
            $this->staffMail->notifyStaffHandoverVerified($dayClosing->business, $owner, $dayClosing->fresh(['user']));
        } catch (\Throwable) {
            // non-blocking
        }

        try {
            app(InAppNotificationService::class)->notifyHandoverVerified($dayClosing->fresh(['user', 'business']), rejected: false);
        } catch (\Throwable) {
            // non-blocking
        }

        return $dayClosing->fresh(['expenses', 'user', 'shift', 'verifier']);
    }

    /**
     * Owner day review — same data as web /day-closing?date=YYYY-MM-DD#handover-{id}
     *
     * @return array<string, mixed>
     */
    public function buildBossDayReview(User $owner, int $businessId, string $date, ?int $focusHandoverId = null): array
    {
        $this->forBusiness($businessId);

        if ($owner->role !== 'owner' && ! $owner->can('view_boss_financial_review')) {
            throw ValidationException::withMessages([
                'review' => 'Only owners can open the boss day-closing review.',
            ]);
        }

        Auth::setUser($owner);

        $dayHandoversQuery = DayClosing::where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->with(['user', 'shift', 'verifier', 'expenses']);

        $this->scopeDayClosingsForActiveBranch($dayHandoversQuery);

        $dayHandovers = $this->sortHandoversForBossReview($dayHandoversQuery->get());

        $awaitingHandoverShiftsQuery = Shift::where('business_id', $businessId)
            ->whereDoesntHave('dayClosing')
            ->where(function ($query) use ($date) {
                $query->whereDate('opened_at', '<=', $date)
                    ->where(function ($inner) use ($date) {
                        $inner->whereNull('closed_at')
                            ->orWhereDate('closed_at', '>=', $date);
                    });
            })
            ->with('user:id,name');

        $this->scopeToActiveBranchUsers($awaitingHandoverShiftsQuery);
        $awaitingHandoverShifts = $this->filterAwaitingShiftsForHandoverContext(
            $awaitingHandoverShiftsQuery->latest('opened_at')->get(),
            $businessId,
            $date
        );

        $pendingVerificationQuery = DayClosing::where('business_id', $businessId)
            ->whereIn('status', ['submitted', 'disputed'])
            ->with(['user:id,name', 'shift']);

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

        $cards = $this->buildHandoverCardsForBossReview($dayHandovers)
            ->map(fn (array $card) => $this->formatHandoverCard($card, $owner) + [
                'verify_queue_position' => $card['verifyQueuePosition'] ?? null,
                'verify_queue_total' => $card['verifyQueueTotal'] ?? null,
            ])
            ->values()
            ->all();

        $nextPending = $this->nextPendingHandoverToVerify($businessId);

        $focusId = $focusHandoverId;
        if (! $focusId && $pendingOnSelectedDate->isNotEmpty()) {
            $focusId = (int) $pendingOnSelectedDate->first()->id;
        }

        return [
            'date' => $date,
            'display_date' => \Carbon\Carbon::parse($date)->format('l, F d, Y'),
            'focus_handover_id' => $focusId,
            'handovers' => $cards,
            'stats' => [
                'handovers_count' => count($cards),
                'pending_on_date' => $pendingOnSelectedDate->count(),
                'verified_on_date' => collect($cards)->where('status', 'verified')->count(),
                'disputed_on_date' => collect($cards)->where('status', 'disputed')->count(),
                'awaiting_shifts' => $awaitingHandoverShifts->count(),
                'pending_other_days' => $pendingFromOtherDays->count(),
            ],
            'pending_on_date' => $pendingOnSelectedDate->map(fn (DayClosing $c) => $this->pendingRow($c))->values()->all(),
            'pending_from_other_days' => $pendingFromOtherDays->map(fn (DayClosing $c) => $this->pendingRow($c))->values()->all(),
            'awaiting_handover_shifts' => $awaitingHandoverShifts->map(fn (Shift $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'staff' => ['id' => $s->user?->id, 'name' => $s->user?->name],
            ])->values()->all(),
            'next_to_verify' => $nextPending ? $this->pendingRow($nextPending) : null,
            'owner_direct' => $this->buildOwnerDirectApiBlock($owner, $businessId, $date),
            'deep_link' => [
                'web' => '/day-closing?date='.$date.($focusId ? '#handover-'.$focusId : ''),
                'api_handover' => $focusId ? '/day-closing/'.$focusId : null,
            ],
        ];
    }

    /**
     * Owner POS sales for a date — same as web "Post to Master Sheet" card.
     *
     * @return array<string, mixed>
     */
    public function ownerDirectPreview(User $owner, int $businessId, string $date): array
    {
        $this->forBusiness($businessId);
        Auth::setUser($owner);

        if ($owner->role !== 'owner') {
            throw ValidationException::withMessages([
                'owner' => 'Only the business owner can post direct POS sales.',
            ]);
        }

        return $this->buildOwnerDirectApiBlock($owner, $businessId, $date);
    }

    /**
     * One-step Verify & Close for owner's own POS sales (verified + Master Sheet draft sync).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function postOwnerDirectSalesApi(User $owner, int $businessId, array $data): array
    {
        $this->forBusiness($businessId);
        Auth::setUser($owner);

        if ($owner->role !== 'owner') {
            throw ValidationException::withMessages([
                'owner' => 'Only the business owner can post direct POS sales.',
            ]);
        }

        $validated = validator($data, [
            'closing_date' => 'required|date',
            'report_notes' => 'nullable|string|max:2000',
            'actual_received' => 'required|numeric|min:0',
            'shortage_note' => 'nullable|string|max:1000',
            'handover_scope' => 'nullable|in:retail,service',
        ])->validate();

        $date = $validated['closing_date'];
        $scope = $validated['handover_scope'] ?? 'retail';
        $this->serviceHandoverContext = $scope === 'service';

        if ($this->ownerDirectClosingExists($businessId, $date, (int) $owner->id)) {
            throw ValidationException::withMessages([
                'closing_date' => $scope === 'service'
                    ? 'Your direct service sales for this date are already closed.'
                    : 'Your direct POS sales for this date are already closed.',
            ]);
        }

        $summary = $this->buildOwnerDirectSummary($businessId, $date);

        if (($summary['sales_count'] ?? 0) === 0) {
            throw ValidationException::withMessages([
                'closing_date' => $scope === 'service'
                    ? 'No direct service sales found for this date.'
                    : 'No direct POS sales found for this date.',
            ]);
        }

        $platformBreakdown = $this->buildPlatformBreakdown(
            $businessId,
            $date,
            null,
            (int) $owner->id,
            $summary['sales']
        );

        $paymentBreakdown = collect($platformBreakdown)->mapWithKeys(fn ($item, $key) => [$key => (float) $item['amount']])->all();
        $cashReceived = (float) ($paymentBreakdown['cash'] ?? 0);
        $mobileReceived = collect($paymentBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'mobile_money')->sum();
        $bankReceived = collect($paymentBreakdown)->filter(fn ($_, $k) => $this->platformMethod($k, $platformBreakdown) === 'bank')->sum();
        $expected = array_sum($paymentBreakdown);
        $actual = (float) $validated['actual_received'];
        $moneyShort = max(0, round($expected - $actual, 2));

        if ($moneyShort > 0 && empty($validated['shortage_note'])) {
            throw ValidationException::withMessages([
                'shortage_note' => ['Shortage note is required when actual received is less than expected.'],
            ]);
        }

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
                'user_id' => $owner->id,
                'shift_id' => null,
                'handover_scope' => $this->ownerDirectHandoverScope(),
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
                'shortage_note' => $moneyShort > 0 ? ($validated['shortage_note'] ?? null) : null,
                'report_notes' => $validated['report_notes'] ?? null,
                'submitted_at' => now(),
                'verified_by' => $owner->id,
                'verified_at' => now(),
            ]);

            $closing->load(['expenses', 'user', 'business']);

            if (! $this->serviceHandoverContext) {
                $this->reportService->syncReport($closing->business, $date, $closing);
                // Do not auto-finalize — owner finalizes explicitly on Master Sheet / owner-reports.
            }

            DB::commit();

            try {
                $this->salesReportEmail->sendOnShiftClose(
                    $closing->business,
                    $owner,
                    $closing
                );
            } catch (\Throwable) {
                // non-blocking
            }

            return [
                'handover' => $this->handoverDetail($closing->fresh(['user', 'shift', 'verifier', 'expenses']), $owner),
                'is_owner_direct' => true,
                'day_finalized' => false,
                'awaiting_verify' => false,
                'money_short' => $moneyShort,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOwnerDirectApiBlock(User $owner, int $businessId, string $date): array
    {
        if ($owner->role !== 'owner') {
            return [
                'available' => false,
                'can_post' => false,
                'already_posted' => false,
                'posted_closing_id' => null,
                'summary' => null,
                'expected_handover' => 0,
                'platform_breakdown' => [],
            ];
        }

        $postedQuery = DayClosing::where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->whereNull('shift_id')
            ->where('user_id', $owner->id);
        $this->applyOwnerDirectClosingScope($postedQuery);
        $posted = $postedQuery->first();

        $summary = $this->buildOwnerDirectSummary($businessId, $date);
        $canPost = ! $posted && ($summary['sales_count'] ?? 0) > 0;
        $platformBreakdown = [];
        $expected = 0.0;

        if ($canPost || ($summary['sales_count'] ?? 0) > 0) {
            $platformBreakdown = collect($this->buildPlatformBreakdown(
                $businessId,
                $date,
                null,
                (int) $owner->id,
                $summary['sales']
            ))->map(fn ($row, $key) => [
                'key' => $key,
                'label' => $row['label'] ?? $key,
                'method' => $row['method'] ?? null,
                'amount' => (float) ($row['amount'] ?? 0),
            ])->values()->all();
            $expected = collect($platformBreakdown)->sum('amount');
        }

        return [
            'available' => true,
            'can_post' => $canPost,
            'already_posted' => (bool) $posted,
            'awaiting_verify' => false,
            'status' => $posted?->status,
            'posted_closing_id' => $posted?->id,
            'summary' => [
                'sales_count' => (int) ($summary['sales_count'] ?? 0),
                'gross_sales' => (float) ($summary['gross_sales'] ?? 0),
                'amount_collected' => (float) ($summary['amount_collected'] ?? 0),
                'outstanding_sales' => (float) ($summary['outstanding_sales'] ?? 0),
                'cancelled_sales' => (int) ($summary['cancelled_sales'] ?? 0),
            ],
            'expected_handover' => (float) $expected,
            'platform_breakdown' => $platformBreakdown,
            'hint' => $canPost
                ? 'You sold on POS today. One step: Verify & Close to post to the Master Sheet.'
                : ($posted
                    ? 'Your direct POS sales for this date are already closed & posted.'
                    : 'No unposted owner POS sales for this date.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingRow(DayClosing $c): array
    {
        return [
            'id' => $c->id,
            'status' => $c->status,
            'closing_date' => $c->closing_date->toDateString(),
            'submitted_at' => $c->submitted_at?->toIso8601String(),
            'staff' => ['id' => $c->user?->id, 'name' => $c->user?->name],
            'shift_id' => $c->shift_id,
            'net_amount' => (float) $c->net_amount,
            'is_owner_direct' => $this->isOwnerDirectClosing($c),
            'review_url' => '/day-closing?date='.$c->closing_date->toDateString().'#handover-'.$c->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handoverDetail(DayClosing $dayClosing, User $viewer): array
    {
        $this->forBusiness($dayClosing->business_id);
        Auth::setUser($viewer);
        $card = $this->buildHandoverCardData($dayClosing);

        return $this->formatHandoverCard($card, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shiftPayload(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'status' => $shift->status,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'sales_count' => (int) $shift->sales_count,
            'gross_sales' => (float) $shift->gross_sales,
            'amount_collected' => (float) $shift->amount_collected,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    protected function formatHandoverCard(array $card, User $viewer): array
    {
        /** @var DayClosing $closing */
        $closing = $card['dayClosing'];
        $platformBreakdown = collect($card['platformBreakdown'] ?? [])->map(fn ($row, $key) => [
            'key' => $key,
            'label' => $row['label'],
            'method' => $row['method'],
            'amount' => (float) $row['amount'],
        ])->values();

        return [
            'id' => $closing->id,
            'status' => $closing->status,
            'closing_date' => $closing->closing_date->toDateString(),
            'submitted_at' => $closing->submitted_at?->toIso8601String(),
            'verified_at' => $closing->verified_at?->toIso8601String(),
            'day_finalized' => OwnerDailyReport::where('business_id', $closing->business_id)
                ->whereDate('report_date', $closing->closing_date)
                ->where('status', 'finalized')
                ->exists(),
            'staff' => [
                'id' => $closing->user?->id,
                'name' => $closing->user?->name,
            ],
            'shift' => $closing->shift ? $this->shiftPayload($closing->shift) : null,
            'summary' => [
                'sales_count' => (int) $closing->sales_count,
                'gross_sales' => (float) $closing->gross_sales,
                'amount_collected' => (float) $closing->amount_collected,
                'outstanding_sales' => (float) $closing->outstanding_sales,
                'total_expenses' => (float) $closing->total_expenses,
                'net_amount' => (float) $closing->net_amount,
                'expected_handover' => (float) $closing->expectedHandoverAmount(),
                'actual_received' => $closing->actual_received !== null ? (float) $closing->actual_received : null,
                'money_short' => (float) ($closing->money_short ?? 0),
            ],
            'platform_breakdown' => $platformBreakdown,
            'expenses' => $closing->expenses->map(fn ($e) => [
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'payment_method' => $e->payment_method,
            ])->values(),
            'handover_summary' => $card['handoverSummary'] ?? null,
            'shift_stats' => $card['shiftStats'] ?? null,
            'debt_collections' => [
                'total' => (float) ($card['debtCollections']['total'] ?? 0),
                'count' => (int) ($card['debtCollections']['count'] ?? 0),
            ],
            'can_verify' => $viewer->role === 'owner' && $closing->status === 'submitted',
            'can_verify_now' => (bool) ($card['canVerifyNow'] ?? false),
            'finance' => ($card['canViewBossFinancials'] ?? false) ? ($card['financeData'] ?? null) : null,
            'dispute_reason' => $closing->dispute_reason,
            'shortage_note' => $closing->shortage_note,
            'report_notes' => $closing->report_notes,
        ];
    }
}
