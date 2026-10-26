<?php

namespace App\Services\Api;

use App\Models\Business;
use App\Models\OwnerDailyReport;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActiveBranchService;
use App\Services\ActiveBusinessService;
use App\Services\SaleItemCostService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApiDashboardTodayService
{
    /** @var array<string, array{day: float, period: float}> */
    private array $profitBlockMemory = [];

    public function __construct(
        private ActiveBusinessService $activeBusiness,
        private ActiveBranchService $activeBranch,
        private SaleItemCostService $saleItemCost,
    ) {
    }

    /**
     * Apply the same branch context as web live-sales / owner reports.
     */
    public function applyBranchContext(User $user, Business $business, ?int $branchFilterId): void
    {
        $this->activeBusiness->setActiveBusiness((int) $business->id);

        if ($user->role === 'owner' && $user->seesBusinessWideData()) {
            $this->activeBranch->setActiveBranch($branchFilterId);
        }
    }

    /**
     * Compact snapshot for mobile home / pay response.
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user, Business $business, ?int $branchFilterId, ?string $date = null): array
    {
        $this->applyBranchContext($user, $business, $branchFilterId);

        $date = $date ?? now()->toDateString();
        $profit = $this->profitBlock($business, $date, $branchFilterId, $user);

        if ($user->role === 'owner' || $user->can('view_reports')) {
            $salesQuery = $this->todaySalesQuery($business, $date, $branchFilterId);

            $saleIds = (clone $salesQuery)->select('sales.id');

            $collected = (float) SalePayment::query()
                ->whereIn('sale_id', $saleIds)
                ->whereDate('created_at', $date)
                ->sum('amount');

            $outstanding = (float) Sale::query()
                ->where('business_id', $business->id)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
                ->when($branchFilterId, fn (Builder $q) => $this->scopeSalesToBranchCatalog($q, $branchFilterId))
                ->sum(DB::raw('total_amount - amount_paid'));

            $grossSales = (float) (clone $salesQuery)->sum('total_amount');
            $collected = $collected > 0 ? $collected : (float) (clone $salesQuery)->sum('amount_paid');
            $margins = $this->liveMarginsFromSalesQuery($salesQuery);
            $todayProfit = (float) ($margins['profit'] ?? $profit['day']);
            $circulation = $this->resolveCirculation($margins['cogs'], $collected, $todayProfit);

            return [
                'date' => $date,
                'orders' => (clone $salesQuery)->count(),
                'gross_sales' => $grossSales,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'open_shifts' => Shift::query()->where('business_id', $business->id)->where('status', 'open')->count(),
                'today_revenue' => $collected,
                'today_profit' => $todayProfit,
                'profit_today' => $todayProfit,
                'gross_profit' => $todayProfit,
                'daily_net_profit' => $todayProfit,
                'cost_of_goods' => $margins['cogs'],
                'cogs' => $margins['cogs'],
                'money_in_circulation' => $circulation,
                'circulation' => $circulation,
                'today_circulation' => $circulation,
                'circulation_generated' => $circulation,
                'net_profit' => $profit,
                'summary' => ['net_profit' => $profit],
                'finance' => [
                    'daily_net_profit' => $todayProfit,
                    'money_in_circulation' => $circulation,
                    'cost_of_goods' => $margins['cogs'],
                ],
            ];
        }

        $openShift = Shift::openForUser($user->id, $business->id);
        $dayMargins = $this->liveMarginsFromSalesQuery($this->todaySalesQuery($business, $date, $branchFilterId));
        $dayCirculation = $this->resolveCirculation($dayMargins['cogs'], 0.0, $dayMargins['profit']);
        $payload = [
            'date' => $date,
            'open_shift_id' => $openShift?->id,
            'orders' => 0,
            'gross_sales' => 0.0,
            'collected' => 0.0,
            'today_revenue' => 0.0,
            'today_profit' => (float) $profit['day'],
            'profit_today' => (float) $profit['day'],
            'gross_profit' => (float) $profit['day'],
            'daily_net_profit' => (float) $profit['day'],
            'cost_of_goods' => $dayMargins['cogs'],
            'cogs' => $dayMargins['cogs'],
            'money_in_circulation' => $dayCirculation,
            'circulation' => $dayCirculation,
            'today_circulation' => $dayCirculation,
            'circulation_generated' => $dayCirculation,
            'net_profit' => $profit,
        ];

        if ($openShift) {
            $openShift->refreshTotals();
            $payload['orders'] = (int) $openShift->sales_count;
            $payload['gross_sales'] = (float) $openShift->gross_sales;
            $payload['collected'] = (float) $openShift->amount_collected;
            $payload['today_revenue'] = (float) $openShift->amount_collected;

            $shiftMargins = $this->liveMarginsFromSalesQuery(
                Sale::query()
                    ->where('business_id', $business->id)
                    ->where('shift_id', $openShift->id)
                    ->where('payment_status', '!=', 'cancelled')
            );
            $shiftProfit = $shiftMargins['profit'];
            $shiftCirculation = $this->resolveCirculation(
                $shiftMargins['cogs'],
                (float) $openShift->amount_collected,
                $shiftProfit
            );
            $payload['today_profit'] = $shiftProfit;
            $payload['profit_today'] = $shiftProfit;
            $payload['gross_profit'] = $shiftProfit;
            $payload['daily_net_profit'] = $shiftProfit;
            $payload['cost_of_goods'] = $shiftMargins['cogs'];
            $payload['cogs'] = $shiftMargins['cogs'];
            $payload['money_in_circulation'] = $shiftCirculation;
            $payload['circulation'] = $shiftCirculation;
            $payload['today_circulation'] = $shiftCirculation;
            $payload['circulation_generated'] = $shiftCirculation;
            $payload['net_profit'] = [
                'day' => $shiftProfit,
                'period' => $profit['period'],
            ];
        }

        return $payload;
    }

    /**
     * Live day margin from sale lines (not gated on day close / Master Sheet).
     *
     * @return array{day: float, period: float}
     */
    public function profitBlock(Business $business, string $date, ?int $branchFilterId = null, ?User $user = null): array
    {
        $branchKey = $branchFilterId ?? $this->activeBranch->activeBranchId() ?? 'all';
        $memoryKey = $business->id.'|'.$branchKey.'|'.$date.'|live';

        if (isset($this->profitBlockMemory[$memoryKey])) {
            return $this->profitBlockMemory[$memoryKey];
        }

        $cacheKey = 'api.live_today_profit.'.$memoryKey;

        $result = Cache::remember($cacheKey, 60, function () use ($business, $date, $branchFilterId, $branchKey) {
            $margins = $this->liveMarginsFromSalesQuery(
                $this->todaySalesQuery($business, $date, is_numeric($branchKey) ? (int) $branchKey : $branchFilterId)
            );
            $dayGross = $margins['profit'];

            if ($branchKey === 'all' || $branchFilterId === null) {
                $periodNet = $this->periodNetProfitFromReports($business, $date, $dayGross);

                return [
                    'day' => round($dayGross, 2),
                    'period' => round($periodNet, 2),
                ];
            }

            return [
                'day' => round($dayGross, 2),
                'period' => round($dayGross, 2),
            ];
        });

        return $this->profitBlockMemory[$memoryKey] = $result;
    }

    private function todaySalesQuery(Business $business, string $date, ?int $branchFilterId): Builder
    {
        $query = Sale::query()
            ->where('business_id', $business->id)
            ->whereDate('sale_date', $date)
            ->where('payment_status', '!=', 'cancelled');

        $this->scopeSalesToBranchCatalog($query, $branchFilterId);

        return $query;
    }

    /**
     * @return array{profit: float, cogs: float, revenue: float}
     */
    private function liveMarginsFromSalesQuery(Builder $salesQuery): array
    {
        $sales = (clone $salesQuery)
            ->with(['items.item.packagings.packagingType', 'items.itemPackaging.packagingType', 'items.service'])
            ->get(['sales.id', 'sales.total_amount']);

        if ($sales->isEmpty()) {
            return ['profit' => 0.0, 'cogs' => 0.0, 'revenue' => 0.0];
        }

        $costOfGoods = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $costOfGoods += $this->saleItemCost->lineCostOfGoods($line);
            }
        }

        $revenue = round((float) $sales->sum('total_amount'), 2);
        $cogs = round($costOfGoods, 2);

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'profit' => round($revenue - $cogs, 2),
        ];
    }

    private function resolveCirculation(float $cogs, float $collected, float $profit): float
    {
        if ($cogs > 0) {
            return round($cogs, 2);
        }

        return round(max(0, $collected - $profit), 2);
    }

    private function periodNetProfitFromReports(Business $business, string $date, float $todayNet): float
    {
        $start = Carbon::parse($date)->startOfMonth()->toDateString();
        $yesterday = Carbon::parse($date)->subDay()->toDateString();

        $priorSum = 0.0;
        if ($start <= $yesterday) {
            $priorSum = (float) OwnerDailyReport::query()
                ->where('business_id', $business->id)
                ->whereDate('report_date', '>=', $start)
                ->whereDate('report_date', '<=', $yesterday)
                ->sum('net_profit');
        }

        return $priorSum + $todayNet;
    }

    private function scopeSalesToBranchCatalog(Builder $query, ?int $branchId): void
    {
        if (! $branchId) {
            return;
        }

        $query->where(function (Builder $scoped) use ($branchId) {
            $scoped->whereHas('items.item.category', function (Builder $categoryQuery) use ($branchId) {
                $categoryQuery->where('branch_id', $branchId);
            })->orWhereHas('items.service', function (Builder $serviceQuery) use ($branchId) {
                $serviceQuery->where('branch_id', $branchId);
            });
        });
    }
}
