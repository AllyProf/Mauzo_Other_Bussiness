<?php

namespace App\Services;

use App\Http\Controllers\DayClosingController;
use App\Models\DayClosing;
use App\Models\DayClosingExpense;
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
    public function buildPreview(User $user, int $businessId, ?int $shiftId = null, ?string $date = null): array
    {
        $this->forBusiness($businessId);
        $date = $date ?: now()->toDateString();

        $shift = null;
        if ($shiftId) {
            $shift = Shift::query()
                ->where('id', $shiftId)
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->first();
        } elseif ($this->userRequiresShiftHandover($user)) {
            $shift = Shift::openForUser($user->id, $businessId)
                ?? Shift::latestClosedAwaitingHandover($user->id, $businessId);
        }

        if ($this->userRequiresShiftHandover($user) && ! $shift) {
            throw ValidationException::withMessages([
                'shift_id' => 'No shift available for handover. Open or close a shift first.',
            ]);
        }

        if ($shift) {
            $this->attachOrphanSalesToShift($shift, $date);
            $shift->refreshTotals();
        }

        $summary = $this->buildDaySummary($businessId, $date, $shift, shiftOnly: (bool) $shift);
        $shiftPaymentWindow = $this->resolveShiftPaymentWindow($shift);
        $collectorUserId = $shift?->user_id;
        $platformBreakdown = $this->buildPlatformBreakdown(
            $businessId,
            $date,
            $shift?->id,
            $collectorUserId,
            $summary['sales'],
            $shiftPaymentWindow
        );
        $debtCollections = $shift
            ? $this->buildDebtCollections($businessId, $date, $summary['sales'], $shift->user_id, $shift->id, $shiftPaymentWindow)
            : ['total' => 0, 'count' => 0, 'items' => []];

        $expectedHandover = (float) collect($platformBreakdown)->sum('amount');

        return [
            'closing_date' => $date,
            'shift' => $shift ? $this->shiftPayload($shift) : null,
            'summary' => [
                'sales_count' => (int) $summary['sales_count'],
                'gross_sales' => (float) $summary['gross_sales'],
                'amount_collected' => (float) $summary['amount_collected'],
                'outstanding_sales' => (float) $summary['outstanding_sales'],
                'cancelled_sales' => (int) $summary['cancelled_sales'],
            ],
            'platform_breakdown' => collect($platformBreakdown)->map(fn ($row, $key) => [
                'key' => $key,
                'label' => $row['label'],
                'method' => $row['method'],
                'amount' => (float) $row['amount'],
            ])->values()->all(),
            'expected_handover' => $expectedHandover,
            'debt_collections' => [
                'total' => (float) ($debtCollections['total'] ?? 0),
                'count' => (int) ($debtCollections['count'] ?? 0),
            ],
            'can_submit' => $shift
                ? ! DayClosing::where('shift_id', $shift->id)->exists()
                : ! DayClosing::where('business_id', $businessId)->whereDate('closing_date', $date)->whereNull('shift_id')->exists(),
        ];
    }

    public function submitHandover(User $user, int $businessId, array $data): DayClosing
    {
        $this->forBusiness($businessId);

        if ($user->role === 'owner') {
            throw ValidationException::withMessages([
                'handover' => 'Business owners verify staff handovers on mobile — they do not submit their own shift handover here.',
            ]);
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
                throw ValidationException::withMessages(['shift_id' => 'Shift is required for handover.']);
            }

            $shift = Shift::where('id', $validated['shift_id'])
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->whereDoesntHave('dayClosing')
                ->whereIn('status', ['open', 'closed'])
                ->first();

            if (! $shift) {
                throw ValidationException::withMessages(['shift_id' => 'Invalid or already submitted shift handover.']);
            }
        } elseif (DayClosing::where('business_id', $businessId)->whereDate('closing_date', $date)->whereNull('shift_id')->exists()) {
            throw ValidationException::withMessages(['closing_date' => 'This day has already been closed.']);
        }

        if ($shift && DayClosing::where('shift_id', $shift->id)->exists()) {
            throw ValidationException::withMessages(['shift_id' => 'Handover for this shift was already submitted.']);
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

        if (! empty($validated['platform_amounts']) && is_array($validated['platform_amounts'])) {
            foreach ($validated['platform_amounts'] as $key => $amount) {
                if (isset($platformBreakdown[$key])) {
                    $platformBreakdown[$key]['amount'] = (float) $amount;
                }
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
                $shift->refreshTotals();
                Shift::whereKey($shift->id)->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);
                $shift->refresh();
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

            return $closing;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
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

        $nextPending = $this->nextPendingHandoverToVerify($dayClosing->business_id);
        if ($nextPending && $nextPending->id !== $dayClosing->id) {
            throw ValidationException::withMessages([
                'verify' => 'Verify the oldest pending handover first (#'.$nextPending->id.').',
            ]);
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
        $this->reportService->tryFinalizeDayIfReady(
            $dayClosing->business,
            $dayClosing->closing_date->toDateString(),
            $dayClosing,
            (int) $owner->id
        );

        try {
            $this->staffSms->notifyStaffHandoverVerified($dayClosing->business, $owner, $dayClosing->fresh(['user']));
            $this->staffMail->notifyStaffHandoverVerified($dayClosing->business, $owner, $dayClosing->fresh(['user']));
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
            'deep_link' => [
                'web' => '/day-closing?date='.$date.($focusId ? '#handover-'.$focusId : ''),
                'api_handover' => $focusId ? '/day-closing/'.$focusId : null,
            ],
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

    protected function userRequiresShiftHandover(User $user): bool
    {
        if ($user->role === 'owner' || $user->seesBusinessWideData()) {
            return false;
        }

        return $user->can('submit_day_closing') || $user->can('process_sales');
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
