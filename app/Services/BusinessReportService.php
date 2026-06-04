<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\DayClosingExpense;
use App\Models\Item;
use App\Models\OwnerDailyReport;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessReportService
{
    public function __construct(
        private OwnerDailyReportService $dailyReportService,
    ) {
    }

    public function parseDateRange(Request $request): array
    {
        $to = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->startOfDay()
            : now()->startOfDay();
        $from = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : $to->copy()->subDays(29);

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'from_c' => $from,
            'to_c' => $to,
        ];
    }

    public function resolveBusinessTypeFilter(Request $request, Business $business, ?array $businessTypes = null): ?string
    {
        $requested = $request->query('business_type');
        if (! $requested || $requested === 'all') {
            return null;
        }

        $allowed = collect($businessTypes ?? $business->posBusinessTypesMeta())->pluck('key')->push('other')->unique()->all();

        return in_array($requested, $allowed, true) ? $requested : null;
    }

    public function circulationProfitReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        if ($businessTypeKey) {
            return $this->circulationProfitReportForBusinessType($business, $from, $to, $businessTypeKey);
        }

        $reports = OwnerDailyReport::where('business_id', $business->id)
            ->whereBetween('report_date', [$from, $to])
            ->orderBy('report_date')
            ->get()
            ->keyBy(fn ($r) => $r->report_date->toDateString());

        $labels = [];
        $circulationSeries = [];
        $profitSeries = [];
        $grossProfitSeries = [];
        $netProfitSeries = [];
        $rows = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $labels[] = $day->format('d M');
            $report = $reports->get($date);
            $built = $this->dailyReportService->buildDayEndTotals($business, $date);

            $circulation = (float) $built['closing_circulation'];
            $profit = (float) $built['closing_profit'];
            $grossProfit = (float) $built['gross_profit'];
            $netProfit = (float) $built['net_profit'];
            $hasVerifiedHandovers = (int) ($built['verified_handover_count'] ?? 0) > 0;

            $circulationSeries[] = round($circulation, 2);
            $profitSeries[] = round($profit, 2);
            $grossProfitSeries[] = round($grossProfit, 2);
            $netProfitSeries[] = round($netProfit, 2);
            $rows[] = [
                'date' => $date,
                'date_label' => $day->format('d M, Y'),
                'opening_circulation' => $report ? (float) $report->opening_circulation : (float) $built['opening_circulation'],
                'closing_circulation' => $circulation,
                'opening_profit' => $report ? (float) $report->opening_profit : (float) $built['opening_profit'],
                'closing_profit' => $profit,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'status' => $report?->status === 'finalized'
                    ? 'finalized'
                    : ($hasVerifiedHandovers ? 'draft' : 'computed'),
            ];
        }

        $currentTotals = $this->resolveLatestCirculationProfitTotals($business, $from, $to);

        return [
            'labels' => $labels,
            'circulation' => $circulationSeries,
            'profit' => $profitSeries,
            'gross_profit' => $grossProfitSeries,
            'net_profit' => $netProfitSeries,
            'rows' => array_reverse($rows),
            'summary' => [
                'current_circulation' => (float) $currentTotals['closing_circulation'],
                'current_profit' => (float) $currentTotals['closing_profit'],
                'peak_circulation' => max($circulationSeries ?: [0]),
                'peak_profit' => max($profitSeries ?: [0]),
                'avg_daily_gross_profit' => $this->averageDailyMetric($grossProfitSeries),
                'avg_daily_net_profit' => $this->averageDailyMetric($netProfitSeries),
            ],
        ];
    }

    private function resolveLatestCirculationProfitTotals(Business $business, string $from, string $to): array
    {
        $cursor = Carbon::parse($to)->startOfDay();
        $start = Carbon::parse($from)->startOfDay();

        while ($cursor->gte($start)) {
            $totals = $this->dailyReportService->buildDayEndTotals($business, $cursor->toDateString());
            $hasActivity = (int) ($totals['verified_handover_count'] ?? 0) > 0
                || (float) $totals['closing_circulation'] > 0
                || (float) $totals['closing_profit'] > 0
                || (float) $totals['gross_profit'] > 0;

            if ($hasActivity) {
                return $totals;
            }

            $cursor->subDay();
        }

        return $this->dailyReportService->buildDayEndTotals($business, $to);
    }

    public function dailySalesReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        if ($businessTypeKey) {
            return $this->dailySalesReportForBusinessType($business, $from, $to, $businessTypeKey);
        }

        $sales = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->selectRaw('DATE(sale_date) as day')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as collected')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $grossSeries = [];
        $collectedSeries = [];
        $ordersSeries = [];
        $rows = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $row = $sales->get($date);
            $labels[] = $day->format('d M');
            $gross = (float) ($row->gross ?? 0);
            $collected = (float) ($row->collected ?? 0);
            $orders = (int) ($row->orders ?? 0);
            $grossSeries[] = round($gross, 2);
            $collectedSeries[] = round($collected, 2);
            $ordersSeries[] = $orders;
            $rows[] = [
                'date' => $date,
                'date_label' => $day->format('d M, Y'),
                'orders' => $orders,
                'gross' => $gross,
                'collected' => $collected,
                'outstanding' => max(0, $gross - $collected),
            ];
        }

        $totals = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled');

        return [
            'labels' => $labels,
            'gross' => $grossSeries,
            'collected' => $collectedSeries,
            'orders' => $ordersSeries,
            'rows' => array_reverse($rows),
            'summary' => [
                'total_orders' => (int) (clone $totals)->count(),
                'gross_sales' => (float) (clone $totals)->sum('total_amount'),
                'collected' => (float) (clone $totals)->sum('amount_paid'),
                'avg_order_value' => (float) (clone $totals)->avg('total_amount'),
            ],
        ];
    }

    public function expensesReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        $staffExpenses = DayClosingExpense::query()
            ->whereHas('dayClosing', function ($q) use ($business, $from, $to) {
                $q->where('business_id', $business->id)
                    ->whereBetween('closing_date', [$from, $to]);
                $this->scopeBranchUsers($q);
            })
            ->with('dayClosing')
            ->get();

        $ownerQuery = BusinessOwnerExpense::where('business_id', $business->id)
            ->whereBetween('expense_date', [$from, $to]);
        $this->scopeBranchUsers($ownerQuery, 'recorded_by');
        $ownerExpenses = $ownerQuery->get();

        $byDate = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $byDate[$day->toDateString()] = ['staff' => 0, 'owner' => 0];
        }

        foreach ($staffExpenses as $expense) {
            $date = $expense->dayClosing->closing_date->toDateString();
            if (isset($byDate[$date])) {
                $byDate[$date]['staff'] += (float) $expense->amount;
            }
        }

        foreach ($ownerExpenses as $expense) {
            $date = $expense->expense_date->toDateString();
            if (isset($byDate[$date])) {
                $byDate[$date]['owner'] += (float) $expense->amount;
            }
        }

        $labels = [];
        $staffSeries = [];
        $ownerSeries = [];
        $rows = [];

        foreach ($byDate as $date => $amounts) {
            $labels[] = Carbon::parse($date)->format('d M');
            $staffSeries[] = round($amounts['staff'], 2);
            $ownerSeries[] = round($amounts['owner'], 2);
            $rows[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date)->format('d M, Y'),
                'staff' => $amounts['staff'],
                'owner' => $amounts['owner'],
                'total' => $amounts['staff'] + $amounts['owner'],
            ];
        }

        $ownerByCategory = $ownerExpenses->groupBy('category')->map(function ($group, $key) {
            return [
                'key' => $key,
                'label' => BusinessOwnerExpense::CATEGORIES[$key] ?? ucfirst($key),
                'amount' => (float) $group->sum('amount'),
            ];
        })->sortByDesc('amount')->values();

        $ownerByFund = [
            'circulation' => (float) $ownerExpenses->where('fund_source', 'circulation')->sum('amount'),
            'profit' => (float) $ownerExpenses->where('fund_source', 'profit')->sum('amount'),
        ];

        return [
            'labels' => $labels,
            'staff' => $staffSeries,
            'owner' => $ownerSeries,
            'rows' => array_reverse($rows),
            'owner_by_category' => $ownerByCategory,
            'owner_by_fund' => $ownerByFund,
            'summary' => [
                'staff_total' => (float) $staffExpenses->sum('amount'),
                'owner_total' => (float) $ownerExpenses->sum('amount'),
                'grand_total' => (float) $staffExpenses->sum('amount') + (float) $ownerExpenses->sum('amount'),
            ],
            'recent_staff' => $staffExpenses->sortByDesc('id')->take(10)->values(),
            'recent_owner' => $ownerExpenses->sortByDesc('expense_date')->take(10)->values(),
            'business_type_filtered' => (bool) $businessTypeKey,
            'business_type_note' => $businessTypeKey
                ? 'Expenses are recorded business-wide and are not split by department.'
                : null,
        ];
    }

    public function profitReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        $labels = [];
        $grossSalesSeries = [];
        $grossProfitSeries = [];
        $netProfitSeries = [];
        $cogsSeries = [];
        $rows = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();

            if ($businessTypeKey) {
                $profit = $this->calculateProfitForDate($business, $date, $businessTypeKey);
                $netProfit = null;
            } else {
                $profit = $this->dailyReportService->calculateProfit($business->id, $date);
                $built = $this->dailyReportService->buildReportData($business, $date);
                $netProfit = $built['net_profit'];
            }

            $labels[] = $day->format('d M');
            $grossSalesSeries[] = round($profit['gross_sales'], 2);
            $grossProfitSeries[] = round($profit['gross_profit'], 2);
            $netProfitSeries[] = $netProfit !== null ? round($netProfit, 2) : null;
            $cogsSeries[] = round($profit['cost_of_goods'], 2);
            $rows[] = [
                'date' => $date,
                'date_label' => $day->format('d M, Y'),
                'gross_sales' => $profit['gross_sales'],
                'cost_of_goods' => $profit['cost_of_goods'],
                'gross_profit' => $profit['gross_profit'],
                'net_profit' => $netProfit,
                'margin' => $profit['gross_sales'] > 0
                    ? round(($profit['gross_profit'] / $profit['gross_sales']) * 100, 1)
                    : 0,
            ];
        }

        $totalGrossSales = array_sum(array_column($rows, 'gross_sales'));
        $totalGrossProfit = array_sum(array_column($rows, 'gross_profit'));
        $totalNetProfit = $businessTypeKey
            ? null
            : array_sum(array_filter(array_column($rows, 'net_profit'), fn ($v) => $v !== null));

        return [
            'labels' => $labels,
            'gross_sales' => $grossSalesSeries,
            'gross_profit' => $grossProfitSeries,
            'net_profit' => $netProfitSeries,
            'cogs' => $cogsSeries,
            'rows' => array_reverse($rows),
            'summary' => [
                'gross_sales' => $totalGrossSales,
                'gross_profit' => $totalGrossProfit,
                'net_profit' => $totalNetProfit,
                'avg_margin' => $totalGrossSales > 0
                    ? round(($totalGrossProfit / $totalGrossSales) * 100, 1)
                    : 0,
            ],
            'business_type_filtered' => (bool) $businessTypeKey,
            'business_type_note' => $businessTypeKey
                ? 'Net profit and expenses are business-wide; only sales and gross profit are split by department.'
                : null,
        ];
    }

    public function salesAnalyticsReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        if ($businessTypeKey) {
            return $this->salesAnalyticsReportForBusinessType($business, $from, $to, $businessTypeKey);
        }

        $sales = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->with('user')
            ->get();

        $payments = SalePayment::whereHas('sale', function ($q) use ($business, $from, $to) {
            $q->where('business_id', $business->id)
                ->whereBetween('sale_date', [$from, $to])
                ->where('payment_status', '!=', 'cancelled');
            $this->scopeBranchUsers($q);
        })->get();

        $byMethod = $payments->groupBy('payment_method')->map(function ($group, $method) use ($business) {
            return [
                'method' => $method,
                'label' => $business->paymentMethodLabel($method),
                'amount' => (float) $group->sum('amount'),
            ];
        })->sortByDesc('amount')->values();

        $byStaff = $sales->groupBy('user_id')->map(function ($group) {
            $user = $group->first()->user;

            return [
                'name' => $user?->name ?? 'Unknown',
                'orders' => $group->count(),
                'gross' => (float) $group->sum('total_amount'),
                'collected' => (float) $group->sum('amount_paid'),
            ];
        })->sortByDesc('gross')->values();

        $bySource = $sales->groupBy('sale_source')->map(function ($group, $source) {
            return [
                'source' => $source ?: 'pos',
                'label' => ucfirst($source ?: 'pos'),
                'orders' => $group->count(),
                'amount' => (float) $group->sum('total_amount'),
            ];
        })->sortByDesc('amount')->values();

        $daily = $this->dailySalesReport($business, $from, $to);

        return [
            'labels' => $daily['labels'],
            'orders_trend' => $daily['orders'],
            'gross_trend' => $daily['gross'],
            'by_method' => $byMethod,
            'by_staff' => $byStaff,
            'by_source' => $bySource,
            'summary' => $daily['summary'] + [
                'unique_staff' => $byStaff->count(),
                'top_payment_method' => $byMethod->first()['label'] ?? '—',
            ],
        ];
    }

    public function productsReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        $lines = $this->saleItemsQuery($business, $from, $to, $businessTypeKey)
            ->with('item.category')
            ->get();

        $byProduct = $lines->groupBy('item_id')->map(function ($group) {
            $item = $group->first()->item;
            $qty = (float) $group->sum('quantity');
            $revenue = (float) $group->sum('subtotal');
            $cost = (float) $group->sum(fn ($line) => (float) ($line->cost_price ?? 0) * (float) $line->quantity);

            return [
                'item_id' => $item?->id,
                'name' => $item?->name ?? 'Unknown Item',
                'category' => $item?->category?->name ?? 'Uncategorized',
                'qty' => $qty,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $revenue - $cost,
            ];
        })->sortByDesc('revenue')->values();

        $byCategory = $byProduct->groupBy('category')->map(function ($group, $category) {
            return [
                'category' => $category,
                'qty' => (float) $group->sum('qty'),
                'revenue' => (float) $group->sum('revenue'),
                'profit' => (float) $group->sum('profit'),
            ];
        })->sortByDesc('revenue')->values();

        $topProducts = $byProduct->take(10);

        return [
            'top_products' => $topProducts,
            'products' => $byProduct,
            'by_category' => $byCategory,
            'category_labels' => $byCategory->pluck('category')->all(),
            'category_revenue' => $byCategory->pluck('revenue')->map(fn ($v) => round($v, 2))->all(),
            'product_labels' => $topProducts->pluck('name')->map(fn ($n) => \Illuminate\Support\Str::limit($n, 20))->all(),
            'product_revenue' => $topProducts->pluck('revenue')->map(fn ($v) => round($v, 2))->all(),
            'summary' => [
                'products_sold' => $byProduct->count(),
                'units_sold' => (float) $byProduct->sum('qty'),
                'total_revenue' => (float) $byProduct->sum('revenue'),
                'total_profit' => (float) $byProduct->sum('profit'),
            ],
            'business_type_filtered' => (bool) $businessTypeKey,
        ];
    }

    public function debtsReport(Business $business, string $from, string $to, ?string $businessTypeKey = null): array
    {
        if ($businessTypeKey) {
            return $this->debtsReportForBusinessType($business, $from, $to, $businessTypeKey);
        }

        $today = now()->toDateString();

        $outstanding = $this->scopedSales($business->id)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->with('customer')
            ->get();

        $aging = [
            'current' => ['label' => 'Not yet due', 'amount' => 0, 'count' => 0],
            '1_30' => ['label' => '1–30 days overdue', 'amount' => 0, 'count' => 0],
            '31_60' => ['label' => '31–60 days overdue', 'amount' => 0, 'count' => 0],
            '61_90' => ['label' => '61–90 days overdue', 'amount' => 0, 'count' => 0],
            '90_plus' => ['label' => '90+ days overdue', 'amount' => 0, 'count' => 0],
            'no_due_date' => ['label' => 'No due date', 'amount' => 0, 'count' => 0],
        ];

        foreach ($outstanding as $sale) {
            $balance = (float) $sale->total_amount - (float) $sale->amount_paid;
            $bucket = 'no_due_date';

            if ($sale->due_date) {
                $daysOverdue = Carbon::parse($sale->due_date)->diffInDays($today, false);
                if ($daysOverdue <= 0) {
                    $bucket = 'current';
                } elseif ($daysOverdue <= 30) {
                    $bucket = '1_30';
                } elseif ($daysOverdue <= 60) {
                    $bucket = '31_60';
                } elseif ($daysOverdue <= 90) {
                    $bucket = '61_90';
                } else {
                    $bucket = '90_plus';
                }
            }

            $aging[$bucket]['amount'] += $balance;
            $aging[$bucket]['count']++;
        }

        $collectedInPeriod = SalePayment::whereHas('sale', function ($q) use ($business, $from, $to) {
            $q->where('business_id', $business->id);
            $this->scopeBranchUsers($q);
        })
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->whereHas('sale', fn ($q) => $q->whereIn('payment_status', ['partial', 'debt', 'paid']))
            ->sum('amount');

        $newDebtInPeriod = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->whereIn('payment_status', ['debt', 'partial', 'pending'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->get()
            ->sum(fn ($s) => (float) $s->total_amount - (float) $s->amount_paid);

        $customerSummaries = $outstanding
            ->groupBy(fn ($s) => $s->customer_name ?: ($s->customer?->name ?? 'Walk-in'))
            ->map(function ($group, $name) {
                return [
                    'name' => $name,
                    'orders' => $group->count(),
                    'balance' => (float) $group->sum(fn ($s) => (float) $s->total_amount - (float) $s->amount_paid),
                ];
            })
            ->sortByDesc('balance')
            ->values();

        $agingValues = collect($aging)->pluck('amount')->map(fn ($v) => round($v, 2))->all();
        $agingLabels = collect($aging)->pluck('label')->all();

        return [
            'aging' => $aging,
            'aging_labels' => $agingLabels,
            'aging_values' => $agingValues,
            'top_debtors' => $customerSummaries->take(10)->values(),
            'customer_summaries' => $customerSummaries,
            'recent_debts' => $outstanding->sortByDesc('sale_date')->take(15)->values(),
            'summary' => [
                'total_outstanding' => (float) $outstanding->sum(fn ($s) => (float) $s->total_amount - (float) $s->amount_paid),
                'open_accounts' => $outstanding->count(),
                'overdue_count' => $outstanding->filter(fn ($s) => $s->due_date && $s->due_date < $today)->count(),
                'collected_in_period' => (float) $collectedInPeriod,
                'new_debt_in_period' => (float) $newDebtInPeriod,
            ],
        ];
    }

    private function scopedSales(int $businessId): Builder
    {
        $query = Sale::where('business_id', $businessId);

        $this->scopeBranchUsers($query);
        $this->scopeBranchSales($query);

        return $query;
    }

    private function scopeBranchSales(Builder $query): Builder
    {
        $branchId = active_branch_id();
        if (! $branchId || ! active_branch_service()->canSwitch()) {
            return $query;
        }

        return $query->whereHas('items.item.category', function ($categoryQuery) use ($branchId) {
            $categoryQuery->where('branch_id', $branchId);
        });
    }

    private function scopeBranchUsers(Builder $query, string $column = 'user_id'): Builder
    {
        return active_branch_service()->scopeRecordsByBranchUsers($query, $column);
    }

    private function saleItemsQuery(Business $business, string $from, string $to, ?string $businessTypeKey = null): Builder
    {
        $query = SaleItem::query()
            ->whereHas('sale', function ($q) use ($business, $from, $to) {
                $q->where('business_id', $business->id)
                    ->whereBetween('sale_date', [$from, $to])
                    ->where('payment_status', '!=', 'cancelled');
                $this->scopeBranchUsers($q);
                $this->scopeBranchSales($q);
            });

        return $this->applyBusinessTypeToSaleItemQuery($query, $businessTypeKey);
    }

    private function applyBusinessTypeToSaleItemQuery(Builder $query, ?string $businessTypeKey): Builder
    {
        if (! $businessTypeKey) {
            return $query;
        }

        return $query->whereHas('item.category', function ($q) use ($businessTypeKey) {
            if ($businessTypeKey === 'other') {
                $q->where(function ($q) {
                    $q->whereNull('source_business_type_key')
                        ->orWhere('source_business_type_key', 'other')
                        ->orWhere('source_business_type_key', '');
                });
            } else {
                $q->where('source_business_type_key', $businessTypeKey);
            }
        });
    }

    private function calculateProfitForDate(Business $business, string $date, string $businessTypeKey): array
    {
        $lines = $this->saleItemsQuery($business, $date, $date, $businessTypeKey)->get();

        $grossSales = (float) $lines->sum('subtotal');
        $costOfGoods = (float) $lines->sum(fn ($line) => (float) ($line->cost_price ?? 0) * (float) $line->quantity);

        return [
            'gross_sales' => $grossSales,
            'cost_of_goods' => $costOfGoods,
            'gross_profit' => $grossSales - $costOfGoods,
        ];
    }

    private function proportionalLineShare(SaleItem $line): float
    {
        $saleTotal = (float) $line->sale->total_amount;

        return $saleTotal > 0 ? (float) $line->subtotal / $saleTotal : 0;
    }

    private function circulationProfitReportForBusinessType(Business $business, string $from, string $to, string $businessTypeKey): array
    {
        $labels = [];
        $profitSeries = [];
        $rows = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $profit = $this->calculateProfitForDate($business, $date, $businessTypeKey);
            $labels[] = $day->format('d M');
            $profitSeries[] = round($profit['gross_profit'], 2);
            $rows[] = [
                'date' => $date,
                'date_label' => $day->format('d M, Y'),
                'opening_circulation' => null,
                'closing_circulation' => null,
                'opening_profit' => null,
                'closing_profit' => $profit['gross_profit'],
                'gross_profit' => $profit['gross_profit'],
                'net_profit' => null,
                'status' => 'computed',
            ];
        }

        return [
            'labels' => $labels,
            'circulation' => [],
            'profit' => $profitSeries,
            'gross_profit' => $profitSeries,
            'net_profit' => array_fill(0, count($profitSeries), 0),
            'rows' => array_reverse($rows),
            'summary' => [
                'current_circulation' => null,
                'current_profit' => end($profitSeries) ?: 0,
                'peak_circulation' => null,
                'peak_profit' => max($profitSeries ?: [0]),
                'avg_daily_gross_profit' => $this->averageDailyMetric($profitSeries),
                'avg_daily_net_profit' => 0,
            ],
            'business_type_filtered' => true,
            'business_type_note' => 'Circulation is tracked business-wide; this view shows gross profit from sales in this department only.',
        ];
    }

    private function averageDailyMetric(array $series): float
    {
        if ($series === []) {
            return 0;
        }

        $active = array_values(array_filter($series, fn ($value) => abs((float) $value) > 0.001));

        if ($active === []) {
            return 0;
        }

        return array_sum($active) / count($active);
    }

    private function dailySalesReportForBusinessType(Business $business, string $from, string $to, string $businessTypeKey): array
    {
        $lines = $this->saleItemsQuery($business, $from, $to, $businessTypeKey)
            ->with('sale')
            ->get();

        $byDay = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $byDay[$day->toDateString()] = ['gross' => 0.0, 'collected' => 0.0, 'sale_ids' => []];
        }

        foreach ($lines as $line) {
            $date = $line->sale->sale_date->toDateString();
            if (! isset($byDay[$date])) {
                continue;
            }

            $share = $this->proportionalLineShare($line);
            $byDay[$date]['gross'] += (float) $line->subtotal;
            $byDay[$date]['collected'] += (float) $line->sale->amount_paid * $share;
            $byDay[$date]['sale_ids'][$line->sale_id] = true;
        }

        $labels = [];
        $grossSeries = [];
        $collectedSeries = [];
        $ordersSeries = [];
        $rows = [];
        $totalOrders = 0;
        $totalGross = 0.0;
        $totalCollected = 0.0;

        foreach ($byDay as $date => $amounts) {
            $labels[] = Carbon::parse($date)->format('d M');
            $orders = count($amounts['sale_ids']);
            $gross = $amounts['gross'];
            $collected = $amounts['collected'];
            $grossSeries[] = round($gross, 2);
            $collectedSeries[] = round($collected, 2);
            $ordersSeries[] = $orders;
            $totalOrders += $orders;
            $totalGross += $gross;
            $totalCollected += $collected;
            $rows[] = [
                'date' => $date,
                'date_label' => Carbon::parse($date)->format('d M, Y'),
                'orders' => $orders,
                'gross' => $gross,
                'collected' => $collected,
                'outstanding' => max(0, $gross - $collected),
            ];
        }

        return [
            'labels' => $labels,
            'gross' => $grossSeries,
            'collected' => $collectedSeries,
            'orders' => $ordersSeries,
            'rows' => array_reverse($rows),
            'summary' => [
                'total_orders' => $totalOrders,
                'gross_sales' => $totalGross,
                'collected' => $totalCollected,
                'avg_order_value' => $totalOrders > 0 ? $totalGross / $totalOrders : 0,
            ],
            'business_type_filtered' => true,
        ];
    }

    private function salesAnalyticsReportForBusinessType(Business $business, string $from, string $to, string $businessTypeKey): array
    {
        $lines = $this->saleItemsQuery($business, $from, $to, $businessTypeKey)
            ->with(['sale.user', 'sale'])
            ->get();

        $saleTotals = [];
        foreach ($lines as $line) {
            $saleId = $line->sale_id;
            if (! isset($saleTotals[$saleId])) {
                $saleTotals[$saleId] = [
                    'sale' => $line->sale,
                    'gross' => 0.0,
                    'collected' => 0.0,
                ];
            }
            $share = $this->proportionalLineShare($line);
            $saleTotals[$saleId]['gross'] += (float) $line->subtotal;
            $saleTotals[$saleId]['collected'] += (float) $line->sale->amount_paid * $share;
        }

        $byStaff = collect($saleTotals)->groupBy(fn ($row) => $row['sale']->user_id)->map(function ($group) {
            $user = $group->first()['sale']->user;

            return [
                'name' => $user?->name ?? 'Unknown',
                'orders' => $group->count(),
                'gross' => (float) $group->sum('gross'),
                'collected' => (float) $group->sum('collected'),
            ];
        })->sortByDesc('gross')->values();

        $bySource = collect($saleTotals)->groupBy(fn ($row) => $row['sale']->sale_source ?: 'pos')->map(function ($group, $source) {
            return [
                'source' => $source ?: 'pos',
                'label' => ucfirst($source ?: 'pos'),
                'orders' => $group->count(),
                'amount' => (float) $group->sum('gross'),
            ];
        })->sortByDesc('amount')->values();

        $saleIds = array_keys($saleTotals);
        $payments = SalePayment::whereIn('sale_id', $saleIds ?: [-1])
            ->with('sale')
            ->get();

        $byMethod = $payments->groupBy('payment_method')->map(function ($group, $method) use ($business, $saleTotals) {
            $amount = (float) $group->sum(function ($payment) use ($saleTotals) {
                $saleId = $payment->sale_id;
                $saleGross = (float) ($saleTotals[$saleId]['gross'] ?? 0);
                $saleTotal = (float) ($payment->sale->total_amount ?? 0);

                return $saleTotal > 0 ? (float) $payment->amount * ($saleGross / $saleTotal) : 0;
            });

            return [
                'method' => $method,
                'label' => $business->paymentMethodLabel($method),
                'amount' => $amount,
            ];
        })->sortByDesc('amount')->values();

        $daily = $this->dailySalesReportForBusinessType($business, $from, $to, $businessTypeKey);

        return [
            'labels' => $daily['labels'],
            'orders_trend' => $daily['orders'],
            'gross_trend' => $daily['gross'],
            'by_method' => $byMethod,
            'by_staff' => $byStaff,
            'by_source' => $bySource,
            'summary' => $daily['summary'] + [
                'unique_staff' => $byStaff->count(),
                'top_payment_method' => $byMethod->first()['label'] ?? '—',
            ],
            'business_type_filtered' => true,
        ];
    }

    private function debtsReportForBusinessType(Business $business, string $from, string $to, string $businessTypeKey): array
    {
        $today = now()->toDateString();

        $outstandingSales = $this->scopedSales($business->id)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->with(['customer', 'items.item.category'])
            ->get();

        $outstanding = $outstandingSales->map(function ($sale) use ($businessTypeKey) {
            $filteredGross = (float) $sale->items
                ->filter(fn ($line) => $this->saleItemMatchesBusinessType($line, $businessTypeKey))
                ->sum('subtotal');

            if ($filteredGross <= 0) {
                return null;
            }

            $saleTotal = (float) $sale->total_amount;
            $balance = $saleTotal > 0
                ? ((float) $sale->total_amount - (float) $sale->amount_paid) * ($filteredGross / $saleTotal)
                : 0;

            $sale->filtered_balance = $balance;
            $sale->filtered_gross = $filteredGross;

            return $sale;
        })->filter()->values();

        $aging = [
            'current' => ['label' => 'Not yet due', 'amount' => 0, 'count' => 0],
            '1_30' => ['label' => '1–30 days overdue', 'amount' => 0, 'count' => 0],
            '31_60' => ['label' => '31–60 days overdue', 'amount' => 0, 'count' => 0],
            '61_90' => ['label' => '61–90 days overdue', 'amount' => 0, 'count' => 0],
            '90_plus' => ['label' => '90+ days overdue', 'amount' => 0, 'count' => 0],
            'no_due_date' => ['label' => 'No due date', 'amount' => 0, 'count' => 0],
        ];

        foreach ($outstanding as $sale) {
            $balance = (float) $sale->filtered_balance;
            $bucket = 'no_due_date';

            if ($sale->due_date) {
                $daysOverdue = Carbon::parse($sale->due_date)->diffInDays($today, false);
                if ($daysOverdue <= 0) {
                    $bucket = 'current';
                } elseif ($daysOverdue <= 30) {
                    $bucket = '1_30';
                } elseif ($daysOverdue <= 60) {
                    $bucket = '31_60';
                } elseif ($daysOverdue <= 90) {
                    $bucket = '61_90';
                } else {
                    $bucket = '90_plus';
                }
            }

            $aging[$bucket]['amount'] += $balance;
            $aging[$bucket]['count']++;
        }

        $periodLines = $this->saleItemsQuery($business, $from, $to, $businessTypeKey)
            ->with('sale')
            ->get();

        $collectedInPeriod = (float) SalePayment::whereHas('sale', function ($q) use ($business) {
            $q->where('business_id', $business->id);
            $this->scopeBranchUsers($q);
        })
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->with(['sale.items.item.category'])
            ->get()
            ->sum(function ($payment) use ($businessTypeKey) {
                $sale = $payment->sale;
                $filteredGross = (float) $sale->items
                    ->filter(fn ($line) => $this->saleItemMatchesBusinessType($line, $businessTypeKey))
                    ->sum('subtotal');
                $total = (float) $sale->total_amount;

                return $total > 0 ? (float) $payment->amount * ($filteredGross / $total) : 0;
            });

        $newDebtInPeriod = (float) $periodLines
            ->filter(fn ($line) => in_array($line->sale->payment_status, ['debt', 'partial', 'pending'], true)
                && (float) $line->sale->total_amount > (float) $line->sale->amount_paid)
            ->sum(function ($line) {
                $outstanding = (float) $line->sale->total_amount - (float) $line->sale->amount_paid;

                return $outstanding * $this->proportionalLineShare($line);
            });

        $customerSummaries = $outstanding
            ->groupBy(fn ($s) => $s->customer_name ?: ($s->customer?->name ?? 'Walk-in'))
            ->map(function ($group, $name) {
                return [
                    'name' => $name,
                    'orders' => $group->count(),
                    'balance' => (float) $group->sum('filtered_balance'),
                ];
            })
            ->sortByDesc('balance')
            ->values();

        $agingValues = collect($aging)->pluck('amount')->map(fn ($v) => round($v, 2))->all();
        $agingLabels = collect($aging)->pluck('label')->all();

        return [
            'aging' => $aging,
            'aging_labels' => $agingLabels,
            'aging_values' => $agingValues,
            'top_debtors' => $customerSummaries->take(10)->values(),
            'customer_summaries' => $customerSummaries,
            'recent_debts' => $outstanding->sortByDesc('sale_date')->take(15)->values(),
            'summary' => [
                'total_outstanding' => (float) $outstanding->sum('filtered_balance'),
                'open_accounts' => $outstanding->count(),
                'overdue_count' => $outstanding->filter(fn ($s) => $s->due_date && $s->due_date < $today)->count(),
                'collected_in_period' => $collectedInPeriod,
                'new_debt_in_period' => $newDebtInPeriod,
            ],
            'business_type_filtered' => true,
            'business_type_note' => 'Outstanding balances are allocated proportionally by line items in this department.',
        ];
    }

    private function saleItemMatchesBusinessType(SaleItem $line, string $businessTypeKey): bool
    {
        $key = $line->item?->category?->source_business_type_key ?: 'other';

        return $key === $businessTypeKey;
    }
}
