<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Item;
use App\Models\Receiving;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private BusinessReportService $reports,
        private ActiveBranchService $branchService,
        private SalesTargetService $salesTargets,
    ) {
    }

    public function ownerDashboard(Business $business, User $user): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $businessTypes = $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;
        $lowStockThreshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);

        $departmentRevenues = $this->departmentRevenues($business, $monthStart, $monthEnd);
        $monthRevenue = (float) $departmentRevenues->sum('revenue');

        $productsReport = $this->reports->productsReport($business, $monthStart, $monthEnd);
        $analyticsReport = $this->reports->salesAnalyticsReport($business, $monthStart, $monthEnd);

        $topStaff = $this->topStaff($business, $monthStart, $monthEnd);

        return [
            'owner' => $user,
            'businessTypes' => $businessTypes,
            'multiBusiness' => $multiBusiness,
            'todayRevenue' => $this->todayRevenue($business),
            'monthRevenue' => $monthRevenue,
            'departmentRevenues' => $departmentRevenues,
            'pendingShortages' => $this->pendingShortagesCount($business->id),
            'monthlyPurchaseCost' => $this->monthlyPurchaseCost($business->id),
            'outstandingDebt' => $this->outstandingDebt($business->id),
            'lowStockCount' => Item::where('business_id', $business->id)
                ->where('current_stock', '>', 0)
                ->where('current_stock', '<=', $lowStockThreshold)
                ->count(),
            'openShiftsCount' => Shift::where('business_id', $business->id)->where('status', 'open')->count(),
            'staffCount' => User::where('business_id', $business->id)->where('role', '!=', 'owner')->count(),
            'itemsCount' => Item::where('business_id', $business->id)->count(),
            'revenueTrend' => $this->revenueTrend($business, $businessTypes),
            'categoryDistribution' => collect($productsReport['by_category'] ?? [])->take(8),
            'topProducts' => collect($productsReport['top_products'] ?? [])->take(8),
            'topStaff' => $topStaff,
            'monthOrders' => (int) ($analyticsReport['summary']['total_orders'] ?? 0),
            'monthCollected' => (float) ($analyticsReport['summary']['collected'] ?? 0),
            'activeBranchLabel' => $this->branchService->activeBranchLabel(),
            'viewingAllBranches' => $this->branchService->isViewingAllBranches(),
            'targetProgress' => $this->salesTargets->dashboardProgress($business),
        ];
    }

    private function todayRevenue(Business $business): float
    {
        $query = Sale::query()
            ->where('business_id', $business->id)
            ->whereDate('sale_date', today())
            ->where('payment_status', '!=', 'cancelled');

        $this->branchService->scopeRecordsByBranchUsers($query);

        return (float) $query->sum('amount_paid');
    }

    private function monthlyPurchaseCost(int $businessId): float
    {
        return (float) Receiving::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('received_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total_amount');
    }

    private function outstandingDebt(int $businessId): float
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid');

        $this->branchService->scopeRecordsByBranchUsers($query);

        return (float) $query->get()->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid);
    }

    private function pendingShortagesCount(int $businessId): int
    {
        return ShiftStockCheck::query()
            ->whereHas('shift', fn ($q) => $q->where('business_id', $businessId))
            ->shortages()
            ->pendingVerification()
            ->count();
    }

    private function departmentRevenues(Business $business, string $from, string $to): Collection
    {
        $lines = $this->saleItemsInRange($business, $from, $to)->get();

        return $lines->groupBy(function ($line) {
            $key = $line->item?->category?->source_business_type_key;

            return ($key && $key !== '') ? $key : 'other';
        })->map(function ($group, $key) use ($business) {
            return [
                'key' => $key,
                'label' => $business->businessTypeLabel((string) $key),
                'revenue' => (float) $group->sum('subtotal'),
            ];
        })->sortByDesc('revenue')->values();
    }

    private function topStaff(Business $business, string $from, string $to): Collection
    {
        $sales = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->with('user')
            ->get();

        return $sales->groupBy('user_id')->map(function ($group) use ($business, $from, $to) {
            $user = $group->first()->user;
            $userId = (int) $group->first()->user_id;

            return [
                'name' => $user?->name ?? 'Unknown',
                'orders_count' => $group->count(),
                'total_revenue' => (float) $group->sum('total_amount'),
                'collected' => (float) $group->sum('amount_paid'),
                'department_revenues' => $this->staffDepartmentRevenues($business, $userId, $from, $to),
            ];
        })->sortByDesc('total_revenue')->values()->take(6);
    }

    private function staffDepartmentRevenues(Business $business, int $userId, string $from, string $to): Collection
    {
        $lines = SaleItem::query()
            ->whereHas('sale', function ($q) use ($business, $from, $to, $userId) {
                $q->where('business_id', $business->id)
                    ->where('user_id', $userId)
                    ->whereBetween('sale_date', [$from, $to])
                    ->where('payment_status', '!=', 'cancelled');
                $this->branchService->scopeRecordsByBranchUsers($q);
            })
            ->with('item.category')
            ->get();

        return $lines->groupBy(function ($line) {
            $key = $line->item?->category?->source_business_type_key;

            return ($key && $key !== '') ? $key : 'other';
        })->map(function ($group, $key) use ($business) {
            return [
                'key' => $key,
                'label' => $business->businessTypeLabel((string) $key),
                'revenue' => (float) $group->sum('subtotal'),
            ];
        })->sortByDesc('revenue')->values();
    }

    private function revenueTrend(Business $business, array $businessTypes): array
    {
        $from = now()->subDays(6)->startOfDay()->toDateString();
        $to = now()->endOfDay()->toDateString();
        $typeKeys = collect($businessTypes)->pluck('key')->all();

        $ordersByDay = $this->scopedSales($business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->selectRaw('DATE(sale_date) as day')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $deptByDay = $this->departmentDailyRevenues($business, $from, $to)->groupBy('day');

        $rows = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $orderRow = $ordersByDay->get($date);
            $departments = [];
            $dayDeptRows = $deptByDay->get($date, collect());

            foreach ($typeKeys as $key) {
                $match = $dayDeptRows->firstWhere('dept_key', $key);
                $departments[$key] = (float) ($match->revenue ?? 0);
            }

            if (empty($typeKeys)) {
                $departments['all'] = (float) ($orderRow->revenue ?? 0);
            }

            $rows[] = [
                'date' => $date,
                'revenue' => (float) ($orderRow->revenue ?? 0),
                'orders' => (int) ($orderRow->orders ?? 0),
                'departments' => $departments,
            ];
        }

        return $rows;
    }

    private function departmentDailyRevenues(Business $business, string $from, string $to): Collection
    {
        $query = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.sale_date', [$from, $to])
            ->where('sales.payment_status', '!=', 'cancelled');

        $this->branchService->scopeRecordsByBranchUsers($query, 'sales.user_id');

        return $query
            ->selectRaw('DATE(sales.sale_date) as day')
            ->selectRaw("COALESCE(NULLIF(categories.source_business_type_key, ''), 'other') as dept_key")
            ->selectRaw('COALESCE(SUM(sale_items.subtotal), 0) as revenue')
            ->groupBy('day', 'dept_key')
            ->get();
    }

    private function saleItemsInRange(Business $business, string $from, string $to)
    {
        return SaleItem::query()
            ->whereHas('sale', function ($q) use ($business, $from, $to) {
                $q->where('business_id', $business->id)
                    ->whereBetween('sale_date', [$from, $to])
                    ->where('payment_status', '!=', 'cancelled');
                $this->branchService->scopeRecordsByBranchUsers($q);
            })
            ->with('item.category');
    }

    private function scopedSales(int $businessId)
    {
        $query = Sale::where('business_id', $businessId);

        return $this->branchService->scopeRecordsByBranchUsers($query);
    }
}
