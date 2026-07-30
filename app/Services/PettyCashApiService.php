<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PettyCashApiService
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    public function index(
        User $user,
        Business $business,
        ?int $branchFilterId,
        ?string $businessTypeKey,
        ?string $date,
        array $filters = []
    ): array {
        $date = $date ?? $this->resolveDefaultDate($business, now()->toDateString());
        $businessTypes = $business->pettyCashBusinessTypesMeta($branchFilterId);

        $balances = $this->reportService->getPettyCashBalances($business, $date, $businessTypeKey ?: null);

        $nextOpenDate = $balances['is_finalized']
            ? $this->resolveDefaultDate($business, Carbon::parse($date)->addDay()->toDateString())
            : null;

        $staffQuery = User::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name');
        if ($branchFilterId) {
            $staffQuery->where('branch_id', $branchFilterId);
        }
        $staffMembers = $staffQuery->get(['id', 'name', 'role']);

        $expensesQuery = BusinessOwnerExpense::where('business_id', $business->id)
            ->with(['recorder', 'issuedTo', 'branch'])
            ->latest('expense_date')
            ->latest('id');

        if ($branchFilterId) {
            $expensesQuery->where(function ($q) use ($branchFilterId) {
                $q->where('branch_id', $branchFilterId)->orWhereNull('branch_id');
            });
        }
        if ($businessTypeKey) {
            $expensesQuery->where('business_type_key', $businessTypeKey);
        }
        if (! empty($filters['start_date'])) {
            $expensesQuery->whereDate('expense_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $expensesQuery->whereDate('expense_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['fund_source']) && in_array($filters['fund_source'], ['circulation', 'profit'], true)) {
            $expensesQuery->where('fund_source', $filters['fund_source']);
        }
        if (! empty($filters['category']) && array_key_exists($filters['category'], BusinessOwnerExpense::CATEGORIES)) {
            $expensesQuery->where('category', $filters['category']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $expenses = $expensesQuery->paginate(20, ['*'], 'page', $page);

        $defaultFundSource = $business->expense_deduct_from ?? 'circulation';

        return [
            'date' => $date,
            'date_label' => Carbon::parse($date)->format('d M, Y'),
            'balances' => $this->formatBalances($balances, $date, $nextOpenDate),
            'default_fund_source' => $defaultFundSource,
            'business_types' => $this->formatBusinessTypes($businessTypes, $businessTypeKey),
            'multi_business' => count($businessTypes) > 1,
            'active_business_type' => $businessTypeKey,
            'staff_members' => $staffMembers->map(fn (User $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'role' => $s->role,
            ])->values()->all(),
            'expenses' => collect($expenses->items())->map(fn ($e) => $this->formatExpense($e, $business))->values()->all(),
            'pagination' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
            'categories' => collect(BusinessOwnerExpense::CATEGORIES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values()->all(),
            'fund_sources' => collect(BusinessOwnerExpense::FUND_SOURCES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values()->all(),
        ];
    }

    public function balances(Business $business, string $date, ?string $businessTypeKey): array
    {
        $balances = $this->reportService->getPettyCashBalances($business, $date, $businessTypeKey);
        $nextOpenDate = $balances['is_finalized']
            ? $this->resolveDefaultDate($business, Carbon::parse($date)->addDay()->toDateString())
            : null;

        return $this->formatBalances($balances, $date, $nextOpenDate);
    }

    public function store(
        User $user,
        Business $business,
        ?int $branchFilterId,
        array $payload
    ): array {
        $businessTypes = $business->pettyCashBusinessTypesMeta($branchFilterId);
        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();

        $rules = [
            'expense_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:restock,payment,salary,operational,other',
            'fund_source' => 'required|in:circulation,profit',
            'issued_to_user_id' => 'nullable|exists:users,id',
            'business_type_key' => [
                Rule::requiredIf(count($typeKeys) > 1),
                'nullable',
                'string',
                'max:100',
                Rule::in($typeKeys),
            ],
        ];

        $validated = validator($payload, $rules)->validate();

        if (! empty($validated['issued_to_user_id'])) {
            $recipientQuery = User::where('business_id', $business->id)
                ->where('is_active', true)
                ->where('id', $validated['issued_to_user_id']);
            if ($branchFilterId) {
                $recipientQuery->where('branch_id', $branchFilterId);
            }
            if (! $recipientQuery->exists()) {
                throw ValidationException::withMessages([
                    'issued_to_user_id' => 'Selected staff member is not valid for this branch.',
                ]);
            }
        }

        $parsedDate = Carbon::parse($validated['expense_date'])->toDateString();
        $amount = (float) $validated['amount'];
        $fundSource = $validated['fund_source'];
        $businessTypeKey = $validated['business_type_key'] ?? null;

        $report = OwnerDailyReport::where('business_id', $business->id)
            ->whereDate('report_date', $parsedDate)
            ->first();

        if ($report && $report->status === 'finalized') {
            throw ValidationException::withMessages([
                'expense_date' => 'That day is finalized. Petty cash cannot be added to a finalized day.',
            ]);
        }

        $balances = $this->reportService->getPettyCashBalances($business, $parsedDate, $businessTypeKey);
        $available = $fundSource === 'profit'
            ? $balances['available_profit']
            : $balances['available_circulation'];

        if ($amount > $available) {
            $label = $fundSource === 'profit' ? 'profit' : 'circulation money';
            throw ValidationException::withMessages([
                'amount' => "Amount exceeds available {$label} on {$parsedDate} (TZS " . number_format($available, 0) . " available).",
            ]);
        }

        return DB::transaction(function () use ($business, $branchFilterId, $businessTypeKey, $parsedDate, $validated, $amount, $fundSource, $user) {
            $dayClosing = DayClosing::where('business_id', $business->id)
                ->whereDate('closing_date', $parsedDate)
                ->first();

            $report = OwnerDailyReport::where('business_id', $business->id)
                ->whereDate('report_date', $parsedDate)
                ->first();

            $expense = BusinessOwnerExpense::create([
                'business_id' => $business->id,
                'branch_id' => $branchFilterId,
                'business_type_key' => $businessTypeKey,
                'owner_daily_report_id' => $report?->id,
                'expense_date' => $parsedDate,
                'description' => $validated['description'],
                'amount' => $amount,
                'category' => $validated['category'],
                'fund_source' => $fundSource,
                'recorded_by' => $user->id,
                'issued_to_user_id' => $validated['issued_to_user_id'] ?? null,
            ]);

            $this->reportService->syncReport($business, $parsedDate, $dayClosing);

            $expense->load(['recorder', 'issuedTo', 'branch']);

            $updatedBalances = $this->reportService->getPettyCashBalances($business, $parsedDate, $businessTypeKey);

            return [
                'expense' => $this->formatExpense($expense, $business),
                'balances' => $this->formatBalances($updatedBalances, $parsedDate, null),
            ];
        });
    }

    public function destroy(
        User $user,
        Business $business,
        ?int $branchFilterId,
        BusinessOwnerExpense $expense
    ): array {
        if ((int) $expense->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'expense' => 'Expense not found for this business.',
            ]);
        }

        if ($branchFilterId && $expense->branch_id && (int) $expense->branch_id !== $branchFilterId) {
            throw ValidationException::withMessages([
                'expense' => 'This petty cash entry belongs to another branch.',
            ]);
        }

        if ($expense->report && $expense->report->status === 'finalized') {
            throw ValidationException::withMessages([
                'expense' => 'Cannot remove petty cash from a finalized day.',
            ]);
        }

        $parsedDate = $expense->expense_date->toDateString();
        $businessTypeKey = $expense->business_type_key;
        $deletedId = (int) $expense->id;

        $expense->delete();

        $dayClosing = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $parsedDate)
            ->first();
        $this->reportService->syncReport($business, $parsedDate, $dayClosing);

        $updatedBalances = $this->reportService->getPettyCashBalances($business, $parsedDate, $businessTypeKey);

        return [
            'deleted_expense_id' => $deletedId,
            'balances' => $this->formatBalances($updatedBalances, $parsedDate, null),
        ];
    }

    private function formatExpense(BusinessOwnerExpense $expense, Business $business): array
    {
        return [
            'id' => (int) $expense->id,
            'expense_date' => $expense->expense_date->toDateString(),
            'expense_date_label' => $expense->expense_date->format('d M, Y'),
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'category' => $expense->category,
            'category_label' => $expense->categoryLabel(),
            'fund_source' => $expense->fund_source ?? 'circulation',
            'fund_source_label' => $expense->fundSourceLabel(),
            'business_type_key' => $expense->business_type_key,
            'business_type_label' => $expense->business_type_key ? $expense->businessTypeLabel($business) : null,
            'branch_id' => $expense->branch_id ? (int) $expense->branch_id : null,
            'branch_name' => $expense->branch?->name,
            'issued_to' => $expense->issuedTo ? [
                'id' => $expense->issuedTo->id,
                'name' => $expense->issuedTo->name,
            ] : null,
            'recorded_by' => $expense->recorder ? [
                'id' => $expense->recorder->id,
                'name' => $expense->recorder->name,
            ] : null,
            'is_locked' => (bool) ($expense->report && $expense->report->status === 'finalized'),
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }

    private function formatBalances(array $balances, string $date, ?string $nextOpenDate): array
    {
        return [
            'date' => $date,
            'date_label' => Carbon::parse($date)->format('d M, Y'),
            'next_open_date' => $nextOpenDate,
            'next_open_date_label' => $nextOpenDate ? Carbon::parse($nextOpenDate)->format('d M, Y') : null,
            'opening_circulation' => (float) ($balances['opening_circulation'] ?? 0),
            'opening_profit' => (float) ($balances['opening_profit'] ?? 0),
            'available_circulation' => (float) ($balances['available_circulation'] ?? 0),
            'available_profit' => (float) ($balances['available_profit'] ?? 0),
            'owner_circulation_spent' => (float) ($balances['owner_circulation_spent'] ?? 0),
            'owner_profit_spent' => (float) ($balances['owner_profit_spent'] ?? 0),
            'daily_net_profit' => (float) ($balances['daily_net_profit'] ?? 0),
            'is_finalized' => (bool) ($balances['is_finalized'] ?? false),
            'business_type_key' => $balances['business_type_key'] ?? null,
            'business_type_label' => $balances['business_type_label'] ?? null,
            'scoped_to_business_type' => (bool) ($balances['scoped_to_business_type'] ?? false),
        ];
    }

    private function formatBusinessTypes(array $types, ?string $activeKey): array
    {
        return collect($types)->map(fn ($t) => [
            'key' => $t['key'],
            'label' => $t['label'],
            'icon' => $t['icon'] ?? 'fa-store',
            'is_active' => $activeKey === $t['key'],
        ])->values()->all();
    }

    private function resolveDefaultDate(Business $business, string $date): string
    {
        $cursor = Carbon::parse($date);

        for ($attempt = 0; $attempt < 366; $attempt++) {
            $candidate = $cursor->toDateString();
            $report = OwnerDailyReport::where('business_id', $business->id)
                ->whereDate('report_date', $candidate)
                ->first();

            if ($report?->status !== 'finalized') {
                return $candidate;
            }

            $cursor->addDay();
        }

        return $date;
    }
}
