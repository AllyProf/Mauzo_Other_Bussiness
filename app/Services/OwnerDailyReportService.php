<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\OwnerDailyReport;
use App\Models\Sale;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OwnerDailyReportService
{
    private function branchService(): ActiveBranchService
    {
        return active_branch_service();
    }

    private function isBranchScoped(): bool
    {
        return $this->branchService()->activeBranchId() !== null;
    }

    private function scopeBranchUsers(Builder $query, string $column = 'user_id'): Builder
    {
        return $this->branchService()->scopeRecordsByBranchUsers($query, $column);
    }

    private function scopeBranchDayClosings(Builder $query): Builder
    {
        return $this->scopeBranchUsers($query, 'user_id');
    }

    public function getOpeningCirculation(Business $business, string $date): float
    {
        if ($this->isBranchScoped()) {
            return $this->getBranchOpeningCirculation($business, $date);
        }

        $previousFinalized = OwnerDailyReport::where('business_id', $business->id)
            ->where('status', 'finalized')
            ->whereDate('report_date', '<', $date)
            ->orderByDesc('report_date')
            ->first();

        if ($previousFinalized) {
            return (float) $previousFinalized->closing_circulation;
        }

        $previousVerified = DayClosing::where('business_id', $business->id)
            ->where('status', 'verified')
            ->whereDate('closing_date', '<', $date)
            ->orderByDesc('closing_date')
            ->first();

        if ($previousVerified) {
            $report = $this->syncReport(
                $business,
                $previousVerified->closing_date->toDateString(),
                $previousVerified
            );

            return (float) $report->closing_circulation;
        }

        return (float) $business->circulation_balance;
    }

    public function getOpeningProfit(Business $business, string $date): float
    {
        if ($this->isBranchScoped()) {
            return $this->getBranchOpeningProfit($business, $date);
        }

        $previousFinalized = OwnerDailyReport::where('business_id', $business->id)
            ->where('status', 'finalized')
            ->whereDate('report_date', '<', $date)
            ->orderByDesc('report_date')
            ->first();

        if ($previousFinalized) {
            return (float) $previousFinalized->closing_profit;
        }

        $previousVerified = DayClosing::where('business_id', $business->id)
            ->where('status', 'verified')
            ->whereDate('closing_date', '<', $date)
            ->orderByDesc('closing_date')
            ->first();

        if ($previousVerified) {
            $report = $this->syncReport(
                $business,
                $previousVerified->closing_date->toDateString(),
                $previousVerified
            );

            return (float) $report->closing_profit;
        }

        return 0;
    }

    public function buildPlatformBreakdown(int $businessId, string $date): array
    {
        $paymentsQuery = SalePayment::whereHas('sale', function ($q) use ($businessId) {
            $q->where('business_id', $businessId);
            $this->scopeBranchUsers($q);
        })->whereDate('created_at', $date);

        $payments = $paymentsQuery->get();

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

        return $breakdown;
    }

    public function calculateProfit(int $businessId, string $date, ?int $shiftId = null): array
    {
        $salesQuery = Sale::where('business_id', $businessId)
            ->whereDate('sale_date', $date)
            ->where('payment_status', '!=', 'cancelled');

        if ($shiftId) {
            $salesQuery->where('shift_id', $shiftId);
        }

        $this->scopeBranchUsers($salesQuery);

        $sales = $salesQuery->with(['items.item.packagings'])->get();

        $grossSales = $sales->sum('total_amount');
        $costOfGoods = 0;

        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $unitCost = (float) ($line->cost_price ?? optional($line->item?->packagings?->first())->cost_price ?? 0);
                $costOfGoods += $unitCost * (float) $line->quantity;
            }
        }

        return [
            'gross_sales' => (float) $grossSales,
            'cost_of_goods' => $costOfGoods,
            'gross_profit' => (float) $grossSales - $costOfGoods,
        ];
    }

    public function buildReportData(Business $business, string $date, ?DayClosing $dayClosing = null, bool $includeOwnerExpenses = true): array
    {
        if (! $dayClosing) {
            $dayClosingQuery = DayClosing::where('business_id', $business->id)
                ->whereDate('closing_date', $date);
            $this->scopeBranchDayClosings($dayClosingQuery);
            $dayClosing = $dayClosingQuery->first();
        }

        $shiftId = $dayClosing?->shift_id;
        $profit = $this->calculateProfit($business->id, $date, $shiftId);
        $platformBreakdown = $dayClosing?->payment_breakdown
            ? $this->normalizeStoredBreakdown($dayClosing->payment_breakdown, $business->id, $date)
            : $this->buildPlatformBreakdown($business->id, $date);

        $netHandover = $dayClosing
            ? $this->resolveNetHandover($dayClosing)
            : (float) collect($platformBreakdown)->sum('amount');

        $totalCollected = $netHandover;

        $staffExpenses = $dayClosing ? (float) $dayClosing->total_expenses : 0;
        $outstandingDebt = $dayClosing
            ? (float) $dayClosing->outstanding_sales
            : max(0, $profit['gross_sales'] - (float) $this->branchScopedSalesQuery($business->id, $date)->sum('amount_paid'));

        $ownerExpenseRows = $includeOwnerExpenses
            ? $this->branchScopedOwnerExpenses($business->id, $date)->get()
            : collect();

        $ownerExpenses = (float) $ownerExpenseRows->sum('amount');
        $ownerCirculationExpenses = (float) $ownerExpenseRows
            ->where('fund_source', 'circulation')
            ->sum('amount');
        $ownerProfitExpenses = (float) $ownerExpenseRows
            ->where('fund_source', 'profit')
            ->sum('amount');

        if ($dayClosing) {
            $openings = $this->resolveHandoverOpeningBalances($business, $dayClosing);
            $openingCirculation = $openings['opening_circulation'];
            $openingProfit = $openings['opening_profit'];
        } else {
            $openingCirculation = $this->getOpeningCirculation($business, $date);
            $openingProfit = $this->getOpeningProfit($business, $date);
        }

        $deductFrom = $business->expense_deduct_from ?? 'circulation';
        $totalExpenses = $staffExpenses + $ownerExpenses;

        if ($deductFrom === 'circulation') {
            $netProfit = $profit['gross_profit'] - $ownerProfitExpenses;
            $circulationFromCollections = max(0, $netHandover - $profit['gross_profit']);
            if ($dayClosing) {
                // Net handover already reflects staff expenses deducted at submission.
                // Profit portion of collections rolls to profit, not circulation capital.
                $closingCirculation = $openingCirculation + $circulationFromCollections - $ownerCirculationExpenses;
            } else {
                $closingCirculation = $openingCirculation + max(0, $totalCollected - $staffExpenses - $profit['gross_profit']) - $ownerCirculationExpenses;
            }
        } else {
            $netProfit = $profit['gross_profit'] - $staffExpenses - $ownerProfitExpenses;
            if ($dayClosing) {
                // Staff expenses hit profit only; circulation carries gross collections forward
                $closingCirculation = $openingCirculation + (float) $dayClosing->amount_collected - $ownerCirculationExpenses;
            } else {
                $closingCirculation = $openingCirculation + $totalCollected - $ownerCirculationExpenses;
            }
        }

        $closingProfit = $openingProfit + $netProfit;

        return [
            'day_closing' => $dayClosing,
            'opening_circulation' => $openingCirculation,
            'opening_profit' => $openingProfit,
            'gross_sales' => $profit['gross_sales'],
            'cost_of_goods' => $profit['cost_of_goods'],
            'gross_profit' => $profit['gross_profit'],
            'total_collected' => $totalCollected,
            'payment_breakdown' => $platformBreakdown,
            'outstanding_debt' => $outstandingDebt,
            'staff_expenses' => $staffExpenses,
            'owner_expenses' => $ownerExpenses,
            'owner_circulation_expenses' => $ownerCirculationExpenses,
            'owner_profit_expenses' => $ownerProfitExpenses,
            'total_expenses' => $totalExpenses,
            'expense_deduct_from' => $deductFrom,
            'net_profit' => $netProfit,
            'closing_profit' => $closingProfit,
            'closing_circulation' => max(0, $closingCirculation),
        ];
    }

    public function buildDayEndTotals(Business $business, string $date): array
    {
        $closingsQuery = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $date)
            ->where('status', 'verified');

        $this->scopeBranchDayClosings($closingsQuery);

        $closings = $closingsQuery->orderBy('verified_at')->orderBy('id')->get();

        if ($closings->isEmpty()) {
            $data = $this->buildReportData($business, $date, null);
            $data['verified_handover_count'] = 0;

            return $data;
        }

        $lastClosing = $closings->last();
        $data = $this->buildReportData($business, $date, $lastClosing);
        $dayProfit = $this->calculateProfit($business->id, $date, null);
        $deductFrom = $business->expense_deduct_from ?? 'circulation';
        $dayNetProfit = 0.0;

        foreach ($closings as $closing) {
            $shiftProfit = $this->calculateProfit($business->id, $date, $closing->shift_id);

            if ($deductFrom === 'circulation') {
                $dayNetProfit += (float) $shiftProfit['gross_profit'];
            } else {
                $dayNetProfit += max(0, (float) $shiftProfit['gross_profit'] - (float) $closing->total_expenses);
            }
        }

        $data['gross_sales'] = (float) $dayProfit['gross_sales'];
        $data['cost_of_goods'] = (float) $dayProfit['cost_of_goods'];
        $data['gross_profit'] = (float) $dayProfit['gross_profit'];
        $data['net_profit'] = $dayNetProfit - (float) $data['owner_profit_expenses'];
        $data['verified_handover_count'] = $closings->count();
        $data['day_closing'] = $lastClosing;
        $data['staff_expenses'] = (float) $closings->sum(fn (DayClosing $closing) => (float) $closing->total_expenses);
        $data['total_expenses'] = $data['staff_expenses'] + (float) $data['owner_expenses'];

        return $data;
    }

    public function buildShiftHandoverReviewData(DayClosing $dayClosing): array
    {
        $dayClosing->loadMissing(['business', 'expenses']);
        $business = $dayClosing->business;
        $date = $dayClosing->closing_date->toDateString();
        $shiftId = $dayClosing->shift_id;

        if (! $shiftId) {
            return $this->buildReportData($business, $date, $dayClosing);
        }

        $profit = $this->calculateProfit($business->id, $date, $shiftId);
        $netHandover = $this->resolveNetHandover($dayClosing);
        $staffExpenses = (float) $dayClosing->total_expenses;
        $deductFrom = $business->expense_deduct_from ?? 'circulation';
        $grossProfit = (float) $profit['gross_profit'];

        if ($deductFrom === 'circulation') {
            $netProfit = $grossProfit;
            $shiftCirculation = max(0, $netHandover - $grossProfit);
        } else {
            $netProfit = max(0, $grossProfit - $staffExpenses);
            $shiftCirculation = max(0, $netHandover);
        }

        return [
            'outstanding_debt' => (float) $dayClosing->outstanding_sales,
            'net_handover' => $netHandover,
            'net_profit' => $netProfit,
            'gross_profit' => $grossProfit,
            'circulation_refill' => $shiftCirculation,
            'opening_circulation' => 0,
            'closing_circulation' => $shiftCirculation,
            'shift_scoped' => true,
            'expense_deduct_from' => $deductFrom,
        ];
    }

    public function syncReport(Business $business, string $date, ?DayClosing $dayClosing = null): OwnerDailyReport
    {
        $data = $this->buildDayEndTotals($business, $date);

        return OwnerDailyReport::updateOrCreate(
            [
                'business_id' => $business->id,
                'report_date' => $date,
            ],
            [
                'day_closing_id' => $data['day_closing']?->id,
                'opening_circulation' => $data['opening_circulation'],
                'gross_sales' => $data['gross_sales'],
                'cost_of_goods' => $data['cost_of_goods'],
                'gross_profit' => $data['gross_profit'],
                'total_collected' => $data['total_collected'],
                'payment_breakdown' => collect($data['payment_breakdown'])->mapWithKeys(fn ($p, $k) => [$k => $p['amount']])->all(),
                'outstanding_debt' => $data['outstanding_debt'],
                'staff_expenses' => $data['staff_expenses'],
                'owner_expenses' => $data['owner_expenses'],
                'expense_deduct_from' => $data['expense_deduct_from'],
                'net_profit' => $data['net_profit'],
                'opening_profit' => $data['opening_profit'],
                'closing_profit' => $data['closing_profit'],
                'closing_circulation' => $data['closing_circulation'],
            ]
        );
    }

    private function priorVerifiedClosingsSameDay(Business $business, DayClosing $dayClosing): \Illuminate\Support\Collection
    {
        $query = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $dayClosing->closing_date)
            ->where('status', 'verified')
            ->where('id', '!=', $dayClosing->id);

        $this->scopeBranchDayClosings($query);

        return $query->get()
            ->filter(fn (DayClosing $prior) => $this->isClosingBefore($prior, $dayClosing))
            ->sortBy(fn (DayClosing $prior) => [
                $prior->verified_at?->timestamp ?? 0,
                $prior->id,
            ])
            ->values();
    }

    private function isClosingBefore(DayClosing $prior, DayClosing $current): bool
    {
        if ($prior->verified_at && $current->verified_at) {
            if ($prior->verified_at->lt($current->verified_at)) {
                return true;
            }

            if ($prior->verified_at->eq($current->verified_at)) {
                return $prior->id < $current->id;
            }

            return false;
        }

        return $prior->id < $current->id;
    }

    private function resolveHandoverOpeningBalances(Business $business, DayClosing $dayClosing): array
    {
        $date = $dayClosing->closing_date->toDateString();
        $openingCirculation = $this->getOpeningCirculation($business, $date);
        $openingProfit = $this->getOpeningProfit($business, $date);

        foreach ($this->priorVerifiedClosingsSameDay($business, $dayClosing) as $prior) {
            $snapshot = $this->computeHandoverClosingSnapshot(
                $business,
                $prior,
                $openingCirculation,
                $openingProfit,
                false
            );
            $openingCirculation = $snapshot['closing_circulation'];
            $openingProfit = $snapshot['closing_profit'];
        }

        return [
            'opening_circulation' => $openingCirculation,
            'opening_profit' => $openingProfit,
        ];
    }

    private function computeHandoverClosingSnapshot(
        Business $business,
        DayClosing $dayClosing,
        float $openingCirculation,
        float $openingProfit,
        bool $includeOwnerExpenses = true
    ): array {
        $date = $dayClosing->closing_date->toDateString();
        $profit = $this->calculateProfit($business->id, $date, $dayClosing->shift_id);
        $netHandover = $this->resolveNetHandover($dayClosing);
        $deductFrom = $business->expense_deduct_from ?? 'circulation';

        $ownerCirculationExpenses = 0.0;
        $ownerProfitExpenses = 0.0;

        if ($includeOwnerExpenses) {
            $ownerExpenseRows = $this->branchScopedOwnerExpenses($business->id, $date)->get();
            $ownerCirculationExpenses = (float) $ownerExpenseRows
                ->where('fund_source', 'circulation')
                ->sum('amount');
            $ownerProfitExpenses = (float) $ownerExpenseRows
                ->where('fund_source', 'profit')
                ->sum('amount');
        }

        if ($deductFrom === 'circulation') {
            $netProfit = $profit['gross_profit'] - $ownerProfitExpenses;
            $circulationFromCollections = max(0, $netHandover - $profit['gross_profit']);
            $closingCirculation = $openingCirculation + $circulationFromCollections - $ownerCirculationExpenses;
        } else {
            $netProfit = $profit['gross_profit'] - (float) $dayClosing->total_expenses - $ownerProfitExpenses;
            $closingCirculation = $openingCirculation + (float) $dayClosing->amount_collected - $ownerCirculationExpenses;
        }

        return [
            'closing_circulation' => max(0, $closingCirculation),
            'closing_profit' => $openingProfit + $netProfit,
        ];
    }

    private function isLastVerifiedHandoverOfDay(Business $business, DayClosing $closing): bool
    {
        $query = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $closing->closing_date)
            ->where('status', 'verified');

        $this->scopeBranchDayClosings($query);

        $last = $query->orderByDesc('verified_at')->orderByDesc('id')->first();

        return $last !== null && (int) $last->id === (int) $closing->id;
    }

    private function resolveNetHandover(DayClosing $dayClosing): float
    {
        if (! empty($dayClosing->payment_breakdown)) {
            return (float) collect($dayClosing->payment_breakdown)->sum();
        }

        return (float) ($dayClosing->net_amount ?: $dayClosing->payments_received);
    }

    private function normalizeStoredBreakdown(array $stored, int $businessId, string $date): array
    {
        $labels = $this->buildPlatformBreakdown($businessId, $date);

        $result = [];
        foreach ($stored as $key => $amount) {
            $result[$key] = [
                'label' => $labels[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)),
                'method' => $labels[$key]['method'] ?? (str_contains($key, 'bank') || in_array($key, ['crdb', 'nmb', 'kcb']) ? 'bank' : ($key === 'cash' ? 'cash' : 'mobile_money')),
                'amount' => (float) $amount,
            ];
        }

        return $result;
    }

    private function resolvePlatformKey($payment): string
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

    private function resolvePlatformLabel($payment): string
    {
        if ($payment->payment_method === 'cash') {
            return 'Physical Cash';
        }

        if ($payment->payment_provider) {
            return $payment->payment_provider;
        }

        return $payment->payment_method === 'bank' ? 'Bank Transfer' : 'Mobile Money';
    }

    public function buildMasterSheetRow(Business $business, DayClosing $closing): array
    {
        $date = $closing->closing_date->toDateString();
        $isLastHandoverOfDay = $this->isLastVerifiedHandoverOfDay($business, $closing);
        $report = $this->isBranchScoped()
            ? OwnerDailyReport::where('business_id', $business->id)->whereDate('report_date', $date)->first()
            : $this->syncReport($business, $date, $closing);
        $data = $this->buildReportData($business, $date, $closing, $isLastHandoverOfDay);

        if ($isLastHandoverOfDay) {
            $dayEnd = $this->buildDayEndTotals($business, $date);
            $data['closing_circulation'] = $dayEnd['closing_circulation'];
            $data['closing_profit'] = $dayEnd['closing_profit'];
            $data['total_expenses'] = $dayEnd['total_expenses'];
            $data['owner_expenses'] = $dayEnd['owner_expenses'];
            $data['owner_circulation_expenses'] = $dayEnd['owner_circulation_expenses'];
            $data['owner_profit_expenses'] = $dayEnd['owner_profit_expenses'];
        }

        $cashCollected = (float) ($closing->cash_received ?? 0);
        $digitalCollected = (float) ($closing->mobile_received ?? 0) + (float) ($closing->bank_received ?? 0);
        $subTotal = (float) $data['total_collected'];
        $totalAssets = (float) $data['opening_circulation'] + $subTotal;
        $grossProfit = (float) $data['gross_profit'];
        $circulationRefill = max(0, $subTotal - $grossProfit);
        $fundSource = ($data['expense_deduct_from'] ?? 'circulation') === 'profit' ? 'profit' : 'circulation';

        $expenseList = collect();
        foreach ($closing->expenses ?? [] as $ex) {
            $expenseList->push([
                'description' => $ex->description,
                'amount' => (float) $ex->amount,
                'category' => 'Staff',
                'fund_source' => $fundSource,
            ]);
        }

        if ($isLastHandoverOfDay) {
            foreach ($this->branchScopedOwnerExpenses($business->id, $date)->get() as $ex) {
                $expenseList->push([
                    'description' => $ex->description,
                    'amount' => (float) $ex->amount,
                    'category' => $ex->categoryLabel(),
                    'fund_source' => $ex->fund_source ?? 'circulation',
                ]);
            }
        }

        $isFinalized = $report?->status === 'finalized';
        $businessStatus = match (true) {
            $closing->status === 'disputed' => 'DISPUTED',
            $isFinalized => 'CLOSED',
            $closing->status === 'verified' => 'VERIFIED',
            default => 'OPEN',
        };
        $statusColor = match ($businessStatus) {
            'CLOSED', 'VERIFIED' => '#28a745',
            'DISPUTED' => '#dc3545',
            default => '#ffc107',
        };

        return [
            'id' => $closing->id,
            'report_id' => $report?->id,
            'ledger_date' => $date,
            'opening_cash' => (float) $data['opening_circulation'],
            'total_cash_received' => $cashCollected,
            'total_digital_received' => $digitalCollected,
            'sub_total' => $subTotal,
            'total_assets' => $totalAssets,
            'combined_expenses' => (float) $data['total_expenses'],
            'profit_generated' => (float) $data['gross_profit'],
            'daily_net_profit' => (float) $data['net_profit'],
            'opening_profit' => (float) $data['opening_profit'],
            'net_available_profit' => (float) $data['net_profit'],
            'profit_rollover' => (float) $data['closing_profit'],
            'circulation_refill' => $circulationRefill,
            'money_in_circulation' => (float) $data['closing_circulation'],
            'carried_forward' => (float) $data['closing_circulation'],
            'outstanding_debt' => (float) $data['outstanding_debt'],
            'gross_sales' => (float) $data['gross_sales'],
            'cost_of_goods' => (float) $data['cost_of_goods'],
            'expense_list' => $expenseList,
            'platform_breakdown' => $data['payment_breakdown'],
            'business_status' => $businessStatus,
            'status_color' => $statusColor,
            'is_finalized' => $isFinalized,
            'is_manager_received' => $isFinalized,
            'expense_deduct_from' => $data['expense_deduct_from'],
            'submitted_by' => $closing->user->name ?? 'Staff',
            'shift_id' => $closing->shift_id,
            'handover_label' => trim(($closing->user->name ?? 'Staff').($closing->shift_id ? ' · Shift #'.$closing->shift_id : '')),
            'report' => $report,
            'closing' => $closing,
        ];
    }

    public function buildOpeningDayRow(Business $business): ?array
    {
        $latestVerifiedQuery = DayClosing::where('business_id', $business->id)
            ->where('status', 'verified');
        $this->scopeBranchDayClosings($latestVerifiedQuery);
        $latestVerified = $latestVerifiedQuery->orderByDesc('closing_date')->first();

        if (! $latestVerified) {
            return null;
        }

        $cursor = Carbon::parse($latestVerified->closing_date)->addDay();
        $today = Carbon::today();

        while ($cursor->lte($today)) {
            $dateString = $cursor->toDateString();

            $hasVerifiedQuery = DayClosing::where('business_id', $business->id)
                ->whereDate('closing_date', $dateString)
                ->where('status', 'verified');
            $this->scopeBranchDayClosings($hasVerifiedQuery);

            if (! $hasVerifiedQuery->exists()) {
                $data = $this->buildReportData($business, $dateString, null);
                $report = $this->isBranchScoped()
                    ? null
                    : $this->syncReport($business, $dateString, null);

                $platformBreakdown = $data['payment_breakdown'];
                $cashCollected = (float) collect($platformBreakdown)->where('method', 'cash')->sum('amount');
                $digitalCollected = (float) collect($platformBreakdown)->filter(fn ($p) => ($p['method'] ?? '') !== 'cash')->sum('amount');
                $subTotal = (float) $data['total_collected'];
                $grossProfit = (float) $data['gross_profit'];
                $circulationRefill = max(0, $subTotal - $grossProfit);

                $expenseList = collect();
                foreach ($this->branchScopedOwnerExpenses($business->id, $dateString)->get() as $ex) {
                    $expenseList->push([
                        'description' => $ex->description,
                        'amount' => (float) $ex->amount,
                        'category' => $ex->categoryLabel(),
                        'fund_source' => $ex->fund_source ?? 'circulation',
                    ]);
                }

                $hasActivity = $subTotal > 0
                    || (float) $data['total_expenses'] > 0
                    || $grossProfit > 0;

                return [
                    'id' => 'open-'.$dateString,
                    'report_id' => $report?->id,
                    'ledger_date' => $dateString,
                    'opening_cash' => (float) $data['opening_circulation'],
                    'total_cash_received' => $cashCollected,
                    'total_digital_received' => $digitalCollected,
                    'sub_total' => $subTotal,
                    'total_assets' => (float) $data['opening_circulation'] + $subTotal,
                    'combined_expenses' => (float) $data['total_expenses'],
                    'profit_generated' => $grossProfit,
                    'daily_net_profit' => (float) $data['net_profit'],
                    'opening_profit' => (float) $data['opening_profit'],
                    'net_available_profit' => (float) $data['net_profit'],
                    'profit_rollover' => (float) $data['closing_profit'],
                    'circulation_refill' => $circulationRefill,
                    'money_in_circulation' => (float) $data['closing_circulation'],
                    'carried_forward' => (float) $data['closing_circulation'],
                    'outstanding_debt' => (float) $data['outstanding_debt'],
                    'gross_sales' => (float) $data['gross_sales'],
                    'cost_of_goods' => (float) $data['cost_of_goods'],
                    'expense_list' => $expenseList,
                    'platform_breakdown' => $platformBreakdown,
                    'business_status' => 'OPEN',
                    'status_color' => '#ffc107',
                    'is_finalized' => false,
                    'is_manager_received' => false,
                    'is_placeholder' => true,
                    'has_open_day_activity' => $hasActivity,
                    'expense_deduct_from' => $data['expense_deduct_from'],
                    'submitted_by' => '—',
                    'report' => $report,
                    'closing' => null,
                ];
            }

            $cursor->addDay();
        }

        return null;
    }

    public function getPettyCashBalances(Business $business, ?string $date = null): array
    {
        $date = $date ?? now()->toDateString();
        $data = $this->buildDayEndTotals($business, $date);
        $report = OwnerDailyReport::where('business_id', $business->id)
            ->whereDate('report_date', $date)
            ->first();

        $dayClosingQuery = DayClosing::where('business_id', $business->id)
            ->whereDate('closing_date', $date)
            ->where('status', 'verified');
        $this->scopeBranchDayClosings($dayClosingQuery);
        $dayClosing = $dayClosingQuery->orderByDesc('verified_at')->orderByDesc('id')->first();

        return [
            'date' => $date,
            'opening_circulation' => (float) $data['opening_circulation'],
            'opening_profit' => (float) $data['opening_profit'],
            'gross_profit_today' => (float) $data['gross_profit'],
            'daily_net_profit' => (float) $data['net_profit'],
            'available_circulation' => (float) $data['closing_circulation'],
            'available_profit' => (float) $data['closing_profit'],
            'owner_circulation_spent' => (float) ($data['owner_circulation_expenses'] ?? 0),
            'owner_profit_spent' => (float) ($data['owner_profit_expenses'] ?? 0),
            'verified_handover_count' => (int) ($data['verified_handover_count'] ?? 0),
            'is_finalized' => $report?->status === 'finalized',
            'report' => $report,
            'day_closing' => $data['day_closing'] ?? $dayClosing,
        ];
    }

    private function getBranchOpeningCirculation(Business $business, string $date): float
    {
        $previousVerifiedQuery = DayClosing::where('business_id', $business->id)
            ->where('status', 'verified')
            ->whereDate('closing_date', '<', $date);
        $this->scopeBranchDayClosings($previousVerifiedQuery);
        $previousVerified = $previousVerifiedQuery->orderByDesc('closing_date')->first();

        if (! $previousVerified) {
            return 0;
        }

        $data = $this->buildDayEndTotals(
            $business,
            $previousVerified->closing_date->toDateString()
        );

        return (float) $data['closing_circulation'];
    }

    private function getBranchOpeningProfit(Business $business, string $date): float
    {
        $previousVerifiedQuery = DayClosing::where('business_id', $business->id)
            ->where('status', 'verified')
            ->whereDate('closing_date', '<', $date);
        $this->scopeBranchDayClosings($previousVerifiedQuery);
        $previousVerified = $previousVerifiedQuery->orderByDesc('closing_date')->first();

        if (! $previousVerified) {
            return 0;
        }

        $data = $this->buildDayEndTotals(
            $business,
            $previousVerified->closing_date->toDateString()
        );

        return (float) $data['closing_profit'];
    }

    private function branchScopedSalesQuery(int $businessId, string $date)
    {
        $query = Sale::where('business_id', $businessId)
            ->whereDate('sale_date', $date)
            ->where('payment_status', '!=', 'cancelled');

        return $this->scopeBranchUsers($query);
    }

    private function branchScopedOwnerExpenses(int $businessId, string $date)
    {
        return BusinessOwnerExpense::where('business_id', $businessId)
            ->whereDate('expense_date', $date);
    }
}
