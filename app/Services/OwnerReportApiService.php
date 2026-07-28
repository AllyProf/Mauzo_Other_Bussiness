<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerReportApiService
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function listMasterSheet(User $user, int $businessId, ?int $branchFilterId, Request $request): array
    {
        return $this->withWebContext($user, $businessId, $branchFilterId, function () use ($user, $businessId, $request) {
            $business = Business::findOrFail($businessId);

            $query = DayClosing::where('business_id', $businessId)
                ->where('status', 'verified')
                ->with(['user', 'verifier', 'expenses']);

            $this->scopeDayClosings($query, $user);

            if ($request->filled('start_date')) {
                $query->whereDate('closing_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('closing_date', '<=', $request->end_date);
            }

            $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
            $closings = $query->orderByDesc('closing_date')
                ->orderByDesc('verified_at')
                ->orderByDesc('id')
                ->paginate($perPage);

            $ledgers = $closings->getCollection()->map(
                fn (DayClosing $closing) => $this->reportService->buildMasterSheetRow($business, $closing)
            );

            $businessTypes = $this->reportService->businessTypesForMasterSheet($business);
            $multiBusiness = count($businessTypes) > 1;
            $ledgers = $this->reportService->expandMasterSheetLedgersByBusinessType($ledgers, $businessTypes);

            $activeBusinessType = $request->get('business_type');
            if ($activeBusinessType) {
                $ledgers = $ledgers->filter(function ($ledger) use ($activeBusinessType) {
                    if ($ledger['is_placeholder'] ?? false) {
                        return false;
                    }

                    return ($ledger['business_type_key'] ?? null) === $activeBusinessType;
                })->values();
            }

            if ($closings->currentPage() === 1 && ! $request->filled('start_date') && ! $request->filled('end_date')) {
                foreach ($this->reportService->buildOpenDayRows($business) as $openingDayRow) {
                    $ledgers = $ledgers->prepend($openingDayRow);
                }
            }

            $pendingClosings = DayClosing::where('business_id', $businessId)
                ->where('status', 'submitted')
                ->with('user');
            $this->scopeDayClosings($pendingClosings, $user);
            $pendingClosings = $pendingClosings->latest('closing_date')->get();

            return [
                'ledgers' => $ledgers->map(fn (array $ledger) => $this->formatLedger($ledger))->values()->all(),
                'pending_handovers' => $pendingClosings->map(fn (DayClosing $closing) => [
                    'id' => $closing->id,
                    'closing_date' => $closing->closing_date->toDateString(),
                    'submitted_at' => $closing->submitted_at?->toIso8601String(),
                    'staff' => ['id' => $closing->user?->id, 'name' => $closing->user?->name],
                    'payments_received' => (float) ($closing->payments_received ?? 0),
                    'handover_scope' => $closing->handover_scope,
                    'review_api_path' => '/day-closing/review?date='.$closing->closing_date->toDateString().'&handover_id='.$closing->id,
                ])->values()->all(),
                'business_types' => $businessTypes,
                'multi_business' => $multiBusiness,
                'filters' => [
                    'start_date' => $request->get('start_date'),
                    'end_date' => $request->get('end_date'),
                    'business_type' => $activeBusinessType,
                    'highlight_date' => $request->get('highlight_date'),
                ],
                'meta' => [
                    'current_page' => $closings->currentPage(),
                    'last_page' => $closings->lastPage(),
                    'per_page' => $closings->perPage(),
                    'total' => $closings->total(),
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function showDay(User $user, int $businessId, ?int $branchFilterId, string $date): array
    {
        $parsedDate = Carbon::parse($date)->toDateString();

        $list = $this->listMasterSheet($user, $businessId, $branchFilterId, new Request([
            'start_date' => $parsedDate,
            'end_date' => $parsedDate,
            'per_page' => 50,
        ]));

        $ledgers = collect($list['ledgers'])->filter(
            fn (array $ledger) => ($ledger['ledger_date'] ?? null) === $parsedDate
        )->values();

        return [
            'date' => $parsedDate,
            'ledgers' => $ledgers->all(),
            'pending_handovers' => collect($list['pending_handovers'])
                ->filter(fn (array $row) => ($row['closing_date'] ?? null) === $parsedDate)
                ->values()
                ->all(),
            'business_types' => $list['business_types'],
            'multi_business' => $list['multi_business'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeExpense(User $user, int $businessId, ?int $branchFilterId, string $date, array $payload): array
    {
        if ($user->role !== 'owner' && $user->role !== 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Only the business owner can record restock expenses.',
            ]);
        }

        return $this->withWebContext($user, $businessId, $branchFilterId, function () use ($user, $businessId, $date, $payload) {
            $business = Business::findOrFail($businessId);
            $parsedDate = Carbon::parse($date)->toDateString();

            $validated = validator($payload, [
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'category' => 'nullable|in:restock,payment,salary,operational,other',
                'fund_source' => 'nullable|in:circulation,profit',
            ])->validate();

            $report = OwnerDailyReport::where('business_id', $businessId)
                ->whereDate('report_date', $parsedDate)
                ->first();

            if ($report && $report->status === 'finalized') {
                throw ValidationException::withMessages([
                    'date' => 'This day is finalized. Cannot add expenses.',
                ]);
            }

            DB::beginTransaction();
            try {
                $dayClosing = DayClosing::where('business_id', $businessId)
                    ->whereDate('closing_date', $parsedDate)
                    ->first();

                $expense = BusinessOwnerExpense::create([
                    'business_id' => $businessId,
                    'owner_daily_report_id' => $report?->id,
                    'expense_date' => $parsedDate,
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                    'category' => $validated['category'] ?? 'restock',
                    'fund_source' => $validated['fund_source'] ?? ($business->expense_deduct_from ?? 'circulation'),
                    'recorded_by' => $user->id,
                ]);

                $this->reportService->syncReport($business, $parsedDate, $dayClosing);
                DB::commit();

                return [
                    'expense' => $this->formatExpense($expense),
                    'day' => $this->showDay($user, $businessId, $branchFilterId, $parsedDate),
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function destroyExpense(User $user, int $businessId, ?int $branchFilterId, string $date, BusinessOwnerExpense $expense): array
    {
        if ($user->role !== 'owner' && $user->role !== 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Only the business owner can delete expenses.',
            ]);
        }

        if ($expense->business_id != $businessId) {
            throw ValidationException::withMessages([
                'expense' => 'Expense not found for this business.',
            ]);
        }

        $parsedDate = Carbon::parse($date)->toDateString();

        if ($expense->report && $expense->report->status === 'finalized') {
            throw ValidationException::withMessages([
                'date' => 'Cannot delete expense from a finalized report.',
            ]);
        }

        return $this->withWebContext($user, $businessId, $branchFilterId, function () use ($user, $businessId, $branchFilterId, $expense, $parsedDate) {
            $business = Business::findOrFail($businessId);
            $expense->delete();

            $dayClosing = DayClosing::where('business_id', $businessId)
                ->whereDate('closing_date', $parsedDate)
                ->first();
            $this->reportService->syncReport($business, $parsedDate, $dayClosing);

            return [
                'deleted_expense_id' => $expense->id,
                'day' => $this->showDay($user, $businessId, $branchFilterId, $parsedDate),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finalizeDay(User $user, int $businessId, ?int $branchFilterId, string $date, array $payload): array
    {
        if ($user->role !== 'owner' && $user->role !== 'super_admin') {
            throw ValidationException::withMessages([
                'role' => 'Only the business owner can finalize the daily report.',
            ]);
        }

        return $this->withWebContext($user, $businessId, $branchFilterId, function () use ($user, $businessId, $branchFilterId, $date, $payload) {
            $business = Business::findOrFail($businessId);
            $parsedDate = Carbon::parse($date)->toDateString();

            $validated = validator($payload, [
                'owner_notes' => 'nullable|string|max:2000',
            ])->validate();

            $dayClosing = DayClosing::where('business_id', $businessId)
                ->whereDate('closing_date', $parsedDate)
                ->first();

            if (! $dayClosing) {
                throw ValidationException::withMessages([
                    'date' => 'No reconciliation found for this date.',
                ]);
            }

            $report = $this->reportService->syncReport($business, $parsedDate, $dayClosing);

            if ($report->status === 'finalized') {
                throw ValidationException::withMessages([
                    'date' => 'This report is already finalized.',
                ]);
            }

            DB::beginTransaction();
            try {
                if ($dayClosing->status === 'submitted') {
                    $dayClosing->update([
                        'status' => 'verified',
                        'verified_by' => $user->id,
                        'verified_at' => now(),
                    ]);
                }

                $report->update([
                    'status' => 'finalized',
                    'finalized_by' => $user->id,
                    'finalized_at' => now(),
                    'owner_notes' => $validated['owner_notes'] ?? null,
                ]);

                $business->update([
                    'circulation_balance' => $report->closing_circulation,
                ]);

                DB::commit();

                return [
                    'report' => $this->formatReport($report->fresh()),
                    'day' => $this->showDay($user, $businessId, $branchFilterId, $parsedDate),
                ];
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withWebContext(User $user, int $businessId, ?int $branchFilterId, callable $callback)
    {
        if ($user->role === 'owner') {
            app(ActiveBusinessService::class)->setActiveBusiness($businessId);
            if ($user->seesBusinessWideData()) {
                app(ActiveBranchService::class)->setActiveBranch($branchFilterId);
            }
        }

        return $callback();
    }

    private function scopeDayClosings(Builder $query, User $user): void
    {
        if ($user->seesBusinessWideData() && $user->role === 'owner') {
            $query->where(function ($scoped) use ($user) {
                active_branch_service()->scopeRecordsByBranchUsers($scoped);
                $scoped->orWhere(function ($ownerQuery) use ($user) {
                    $ownerQuery->where('user_id', $user->id)->whereNull('shift_id');
                });
            });

            return;
        }

        active_branch_service()->scopeRecordsByBranchUsers($query);
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    private function formatLedger(array $ledger): array
    {
        /** @var DayClosing|null $closing */
        $closing = $ledger['closing'] ?? null;
        /** @var OwnerDailyReport|null $report */
        $report = $ledger['report'] ?? null;

        $detailClosingId = $ledger['detail_closing_id'] ?? $closing?->id;
        if (is_string($detailClosingId) && str_contains($detailClosingId, '-')) {
            $detailClosingId = (int) explode('-', (string) $detailClosingId, 2)[0];
        }

        $ledgerDate = $ledger['ledger_date'] ?? null;
        $handoverId = $detailClosingId ? (int) $detailClosingId : null;

        return [
            'id' => (string) ($ledger['id'] ?? ''),
            'handover_id' => $closing?->id,
            'detail_handover_id' => $handoverId,
            'report_id' => $ledger['report_id'] ?? $report?->id,
            'ledger_date' => $ledgerDate,
            'is_placeholder' => (bool) ($ledger['is_placeholder'] ?? false),
            'is_business_type_row' => (bool) ($ledger['is_business_type_row'] ?? false),
            'business_type_key' => $ledger['business_type_key'] ?? null,
            'business_type_label' => $ledger['business_type_label'] ?? null,
            'show_rollover_columns' => (bool) ($ledger['show_rollover_columns'] ?? true),
            'handover_label' => $ledger['handover_label'] ?? $ledger['submitted_by'] ?? null,
            'submitted_by' => $ledger['submitted_by'] ?? null,
            'shift_id' => $ledger['shift_id'] ?? null,
            'handover_scope' => $closing?->handover_scope,
            'business_status' => $ledger['business_status'] ?? null,
            'status_color' => $ledger['status_color'] ?? null,
            'is_finalized' => (bool) ($ledger['is_finalized'] ?? false),
            'is_manager_received' => (bool) ($ledger['is_manager_received'] ?? false),
            'is_last_handover_of_day' => (bool) ($ledger['is_last_handover_of_day'] ?? false),
            'has_open_day_activity' => (bool) ($ledger['has_open_day_activity'] ?? false),
            'has_open_shift' => (bool) ($ledger['has_open_shift'] ?? false),
            'opening_cash' => array_key_exists('opening_cash', $ledger) && $ledger['opening_cash'] !== null
                ? (float) $ledger['opening_cash']
                : null,
            'opening_profit' => (float) ($ledger['opening_profit'] ?? 0),
            'total_cash_received' => (float) ($ledger['total_cash_received'] ?? 0),
            'total_digital_received' => (float) ($ledger['total_digital_received'] ?? 0),
            'sub_total' => (float) ($ledger['sub_total'] ?? 0),
            'total_assets' => (float) ($ledger['total_assets'] ?? 0),
            'combined_expenses' => (float) ($ledger['combined_expenses'] ?? 0),
            'profit_generated' => (float) ($ledger['profit_generated'] ?? 0),
            'daily_net_profit' => (float) ($ledger['daily_net_profit'] ?? $ledger['net_available_profit'] ?? 0),
            'net_available_profit' => (float) ($ledger['net_available_profit'] ?? 0),
            'circulation_refill' => (float) ($ledger['circulation_refill'] ?? 0),
            'money_in_circulation' => array_key_exists('money_in_circulation', $ledger) && $ledger['money_in_circulation'] !== null
                ? (float) $ledger['money_in_circulation']
                : null,
            'profit_rollover' => array_key_exists('profit_rollover', $ledger) && $ledger['profit_rollover'] !== null
                ? (float) $ledger['profit_rollover']
                : null,
            'carried_forward' => (float) ($ledger['carried_forward'] ?? 0),
            'outstanding_debt' => (float) ($ledger['outstanding_debt'] ?? 0),
            'gross_sales' => (float) ($ledger['gross_sales'] ?? 0),
            'cost_of_goods' => (float) ($ledger['cost_of_goods'] ?? 0),
            'staff_profit_recoveries' => (float) ($ledger['staff_profit_recoveries'] ?? 0),
            'staff_circulation_recoveries' => (float) ($ledger['staff_circulation_recoveries'] ?? 0),
            'money_short_recoveries' => (float) ($ledger['money_short_recoveries'] ?? 0),
            'money_short_profit_recoveries' => (float) ($ledger['money_short_profit_recoveries'] ?? 0),
            'money_short_circulation_recoveries' => (float) ($ledger['money_short_circulation_recoveries'] ?? 0),
            'expense_deduct_from' => $ledger['expense_deduct_from'] ?? null,
            'expense_list' => $this->formatExpenseList($ledger['expense_list'] ?? []),
            'platform_breakdown' => $this->formatPlatformBreakdown($ledger['platform_breakdown'] ?? []),
            'business_type_breakdown' => collect($ledger['business_type_breakdown'] ?? [])->map(fn (array $row) => [
                'key' => $row['key'] ?? null,
                'label' => $row['label'] ?? null,
                'collected' => (float) ($row['collected'] ?? 0),
                'credit' => (float) ($row['credit'] ?? 0),
                'gross_sales' => (float) ($row['gross_sales'] ?? 0),
                'cost_of_goods' => (float) ($row['cost_of_goods'] ?? 0),
                'profit_generated' => (float) ($row['profit_generated'] ?? 0),
                'circulation_generated' => (float) ($row['circulation_generated'] ?? 0),
            ])->values()->all(),
            'owner_notes' => $report?->owner_notes,
            'finalized_at' => $report?->finalized_at?->toIso8601String(),
            'review_api_path' => $handoverId ? '/day-closing/'.$handoverId : null,
            'day_review_api_path' => $ledgerDate
                ? '/day-closing/review?date='.$ledgerDate.($handoverId ? '&handover_id='.$handoverId : '')
                : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function formatExpenseList(Collection|array $items): array
    {
        return collect($items)->map(fn (array $ex) => [
            'description' => $ex['description'] ?? '',
            'amount' => (float) ($ex['amount'] ?? 0),
            'category' => $ex['category'] ?? null,
            'fund_source' => $ex['fund_source'] ?? 'circulation',
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<int, array<string, mixed>>
     */
    private function formatPlatformBreakdown(array $breakdown): array
    {
        return collect($breakdown)->map(function ($platform, $key) {
            $amount = is_array($platform) ? ($platform['amount'] ?? 0) : $platform;

            return [
                'key' => (string) $key,
                'label' => is_array($platform) ? ($platform['label'] ?? ucwords(str_replace('_', ' ', (string) $key))) : ucwords(str_replace('_', ' ', (string) $key)),
                'amount' => (float) $amount,
            ];
        })->filter(fn (array $row) => $row['amount'] != 0)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExpense(BusinessOwnerExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
            'category' => $expense->category,
            'category_label' => $expense->categoryLabel(),
            'fund_source' => $expense->fund_source ?? 'circulation',
            'expense_date' => $expense->expense_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReport(OwnerDailyReport $report): array
    {
        return [
            'id' => $report->id,
            'report_date' => $report->report_date?->toDateString(),
            'status' => $report->status,
            'closing_circulation' => (float) $report->closing_circulation,
            'closing_profit' => (float) $report->closing_profit,
            'owner_notes' => $report->owner_notes,
            'finalized_at' => $report->finalized_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listMeta(User $user, int $businessId, ?int $branchFilterId): array
    {
        $branchName = $branchFilterId
            ? (Branch::find($branchFilterId)?->name)
            : null;

        return [
            'branch_filter_id' => $branchFilterId,
            'active_branch_name' => $branchName,
            'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
            'expense_categories' => collect(BusinessOwnerExpense::CATEGORIES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values()->all(),
            'fund_sources' => collect(BusinessOwnerExpense::FUND_SOURCES)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values()->all(),
        ];
    }
}
