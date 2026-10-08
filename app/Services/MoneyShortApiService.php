<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DayClosing;
use App\Models\MoneyShortSettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MoneyShortApiService
{
    public function __construct(private MoneyShortSettlementService $settlementService)
    {
    }

    /**
     * @param  callable(Builder): void  $scopeClosingsForBranch
     * @return array<string, mixed>
     */
    public function index(
        User $user,
        Business $business,
        array $filter,
        Request $request,
        callable $scopeClosingsForBranch
    ): array {
        $businessId = (int) $business->id;
        $statusFilter = $request->get('status', 'all');
        $activeBusinessType = $filter['activeBusinessType'] ?? null;
        $businessTypes = $filter['businessTypes'] ?? [];

        $query = DayClosing::where('business_id', $businessId)
            ->where('money_short', '>', 0)
            ->where('status', 'verified')
            ->with(['user:id,name', 'shift', 'verifier:id,name', 'settlements.recorder:id,name', 'settlements.voider:id,name']);

        $scopeClosingsForBranch($query);

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
            return $this->enrichClosing($closing, $business, $activeBusinessType);
        });

        if ($statusFilter === 'outstanding') {
            $shorts = $allShorts->filter(fn (array $row) => ($row['balance_due'] ?? 0) > 0)->values();
        } elseif ($statusFilter === 'settled') {
            $shorts = $allShorts->filter(fn (array $row) => ($row['balance_due'] ?? 0) <= 0)->values();
        } else {
            $shorts = $allShorts->values();
        }

        $stats = [
            'total_records' => $allShorts->count(),
            'outstanding_count' => $allShorts->filter(fn (array $row) => ($row['balance_due'] ?? 0) > 0)->count(),
            'outstanding_total' => (float) $allShorts->sum(fn (array $row) => $row['balance_due'] ?? 0),
            'settled_count' => $allShorts->filter(fn (array $row) => ($row['balance_due'] ?? 0) <= 0)->count(),
            'total_short' => (float) $allShorts->sum(fn (array $row) => $row['display_short'] ?? 0),
        ];

        $historyQuery = MoneyShortSettlement::where('business_id', $businessId)
            ->with(['dayClosing.shift', 'staff:id,name', 'recorder:id,name', 'voider:id,name'])
            ->whereHas('dayClosing', function ($q) use ($activeBusinessType, $businessId, $scopeClosingsForBranch) {
                $q->where('status', 'verified')->where('money_short', '>', 0);
                $scopeClosingsForBranch($q);

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

        $settlementHistory = $historyQuery->orderByDesc('created_at')->get()
            ->map(fn (MoneyShortSettlement $s) => $this->formatSettlement($s))
            ->values()
            ->all();

        $paymentMethods = $business->enabledPaymentMethods();

        return [
            'title' => 'Money Shorts',
            'web_path' => '/money-shorts',
            'stats' => $stats,
            'status_filter' => in_array($statusFilter, ['all', 'outstanding', 'settled'], true) ? $statusFilter : 'all',
            'shorts' => $shorts->all(),
            'settlement_history' => $settlementHistory,
            'payment_methods' => collect($paymentMethods)->map(fn (array $method) => [
                'key' => $method['key'],
                'label' => $method['label'] ?? $method['key'],
            ])->values()->all(),
            'filters' => [
                'branch_id' => $filter['branchFilterId'] ?? null,
                'branch_name' => $filter['activeBranchName'] ?? null,
                'viewing_all_branches' => (bool) ($filter['viewingAllBranches'] ?? false),
                'business_types' => array_values($businessTypes),
                'multi_business' => (bool) ($filter['multiBusiness'] ?? false),
                'active_business_type' => $activeBusinessType,
                'active_business_label' => $filter['activeBusinessLabel'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordPayment(User $owner, Business $business, DayClosing $dayClosing, array $input): array
    {
        $this->assertCanManageShort($dayClosing, (int) $business->id);

        $balance = $this->settlementService->shortBalance($dayClosing);
        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'day_closing' => 'This money short is already settled.',
            ]);
        }

        $allowedMethods = collect($business->enabledPaymentMethods())->pluck('key')->all();

        $validated = validator($input, [
            'amount' => 'required|numeric|min:0.01|max:'.$balance,
            'settlement_date' => 'required|date',
            'payment_method' => ['required', 'string', Rule::in($allowedMethods)],
            'payment_provider' => 'nullable|string|max:255',
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ])->validate();

        $settlement = $this->settlementService->recordCashPayment($dayClosing, $owner, $validated);

        return $this->actionResult($dayClosing->fresh(['user', 'shift', 'settlements.recorder', 'settlements.voider']), $business, $settlement);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSalaryDeduction(User $owner, Business $business, DayClosing $dayClosing, array $input): array
    {
        $this->assertCanManageShort($dayClosing, (int) $business->id);

        $balance = $this->settlementService->shortBalance($dayClosing);
        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'day_closing' => 'This money short is already settled.',
            ]);
        }

        $validated = validator($input, [
            'amount' => 'nullable|numeric|min:0.01|max:'.$balance,
            'settlement_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ])->validate();

        $settlement = $this->settlementService->recordSalaryDeduction($dayClosing, $owner, [
            'amount' => $validated['amount'] ?? null,
            'settlement_date' => $validated['settlement_date'] ?? now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->actionResult($dayClosing->fresh(['user', 'shift', 'settlements.recorder', 'settlements.voider']), $business, $settlement);
    }

    /**
     * @return array<string, mixed>
     */
    public function undoSettlement(User $owner, Business $business, MoneyShortSettlement $settlement): array
    {
        if ((int) $settlement->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['settlement' => 'Settlement not found.']);
        }

        if ($settlement->isVoided()) {
            throw ValidationException::withMessages(['settlement' => 'This settlement has already been undone.']);
        }

        $closing = $settlement->dayClosing;
        if (! $closing || $closing->status !== 'verified' || ! $closing->hasMoneyShort()) {
            throw ValidationException::withMessages(['settlement' => 'Settlement not found.']);
        }

        $result = $this->settlementService->undoSettlement($settlement, $owner);

        return [
            'undone' => [
                'settlement_id' => $settlement->id,
                'amount' => (float) $result['amount'],
                'type_label' => $result['type_label'],
                'settlement_date' => $result['settlement_date'],
                'was_cash_payment' => (bool) $result['was_cash_payment'],
            ],
            'new_balance' => (float) $result['new_balance'],
            'short' => $this->enrichClosing($closing->fresh(['user', 'shift', 'settlements.recorder', 'settlements.voider']), $business, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionResult(DayClosing $closing, Business $business, MoneyShortSettlement $settlement): array
    {
        return [
            'settlement' => $this->formatSettlement($settlement->fresh(['staff', 'recorder', 'voider', 'dayClosing.shift'])),
            'short' => $this->enrichClosing($closing, $business, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichClosing(DayClosing $closing, Business $business, ?string $activeBusinessType): array
    {
        $closing->loadMissing(['user', 'shift', 'verifier', 'settlements.recorder', 'settlements.voider']);

        $shortBalance = $this->settlementService->shortBalance($closing);
        $settlementStatus = $this->settlementService->settlementStatus($closing);
        $shortSplit = $this->settlementService->computeShortSplit($closing);
        $typeKeys = $this->settlementService->closingBusinessTypeKeys($closing);

        $displayShort = (float) $closing->money_short;
        $displayPaid = (float) $closing->settlements->reject(fn ($s) => $s->isVoided())->sum('amount');
        $fullHandoverShort = (float) $closing->money_short;

        if ($activeBusinessType) {
            $allocation = $this->settlementService->allocateShortToBusinessType($closing, $activeBusinessType);
            $ratio = $allocation['ratio'];

            $displayShort = $allocation['allocated_short'];
            $displayPaid = $allocation['allocated_settled'];
            $shortBalance = $allocation['allocated_balance'];
            $shortSplit = [
                'profit_short' => round(($shortSplit['profit_short'] ?? 0) * $ratio, 2),
                'circulation_short' => round(($shortSplit['circulation_short'] ?? 0) * $ratio, 2),
            ];

            if ($allocation['allocated_short'] <= 0) {
                $settlementStatus = 'none';
            } elseif ($allocation['allocated_balance'] <= 0) {
                $lastType = $closing->settlements()->active()->latest('id')->value('settlement_type');
                $settlementStatus = $lastType === MoneyShortSettlement::TYPE_SALARY_DEDUCTION
                    ? 'salary_deduction'
                    : 'paid';
            } elseif ($allocation['allocated_settled'] > 0) {
                $settlementStatus = 'partial';
            } else {
                $settlementStatus = 'pending';
            }
        }

        $activeSettlements = $closing->settlements->reject(fn ($s) => $s->isVoided());

        return [
            'id' => $closing->id,
            'day_closing_id' => $closing->id,
            'verified_at' => $closing->verified_at?->toIso8601String(),
            'verified_at_label' => $closing->verified_at?->format('M d, Y'),
            'staff' => [
                'id' => $closing->user?->id,
                'name' => $closing->user?->name,
            ],
            'verifier' => $closing->verifier?->name,
            'shift_id' => $closing->shift_id,
            'shift_label' => $closing->shift ? '#'.$closing->shift->id : 'Owner direct',
            'closing_date' => $closing->closing_date->toDateString(),
            'closing_date_label' => $closing->closing_date->format('M d, Y'),
            'business_types' => collect($typeKeys)
                ->map(fn ($key) => $business->businessTypeLabel($key))
                ->values()
                ->all(),
            'money_short' => $fullHandoverShort,
            'display_short' => $displayShort,
            'amount_paid' => $displayPaid,
            'balance_due' => max(0, round($shortBalance, 2)),
            'settlement_status' => $settlementStatus,
            'short_split' => $shortSplit,
            'shortage_note' => $closing->shortage_note,
            'can_record_payment' => $shortBalance > 0,
            'can_record_salary_deduction' => $shortBalance > 0,
            'settlements' => $activeSettlements->map(fn (MoneyShortSettlement $s) => $this->formatSettlement($s))->values()->all(),
            'detail_url' => '/day-closing/'.$closing->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettlement(MoneyShortSettlement $settlement): array
    {
        $settlement->loadMissing(['staff', 'recorder', 'voider', 'dayClosing.shift']);

        return [
            'id' => $settlement->id,
            'day_closing_id' => $settlement->day_closing_id,
            'settlement_type' => $settlement->settlement_type,
            'type_label' => $settlement->typeLabel(),
            'amount' => (float) $settlement->amount,
            'settlement_date' => $settlement->settlement_date->toDateString(),
            'payment_method' => $settlement->payment_method,
            'payment_provider' => $settlement->payment_provider,
            'transaction_reference' => $settlement->transaction_reference,
            'notes' => $settlement->notes,
            'staff' => [
                'id' => $settlement->staff?->id,
                'name' => $settlement->staff?->name,
            ],
            'shift_label' => $settlement->dayClosing?->shift
                ? '#'.$settlement->dayClosing->shift->id
                : 'Owner direct',
            'recorded_by' => $settlement->recorder?->name,
            'created_at' => $settlement->created_at?->toIso8601String(),
            'voided' => $settlement->isVoided(),
            'voided_at' => $settlement->voided_at?->toIso8601String(),
            'voided_by' => $settlement->voider?->name,
            'can_undo' => ! $settlement->isVoided(),
        ];
    }

    private function assertCanManageShort(DayClosing $dayClosing, int $businessId): void
    {
        if ((int) $dayClosing->business_id !== $businessId) {
            throw ValidationException::withMessages(['day_closing' => 'Handover not found.']);
        }

        if ($dayClosing->status !== 'verified' || ! $dayClosing->hasMoneyShort()) {
            throw ValidationException::withMessages(['day_closing' => 'Handover not found.']);
        }
    }
}
