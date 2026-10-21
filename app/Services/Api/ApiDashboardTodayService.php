<?php

namespace App\Services\Api;

use App\Models\Business;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActiveBranchService;
use App\Services\ActiveBusinessService;
use App\Services\OwnerDailyReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class ApiDashboardTodayService
{
    public function __construct(
        private OwnerDailyReportService $ownerReports,
        private ActiveBusinessService $activeBusiness,
        private ActiveBranchService $activeBranch,
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
        $profit = $this->profitBlock($business, $date);

        if ($user->role === 'owner' || $user->can('view_reports')) {
            $salesQuery = Sale::query()
                ->where('business_id', $business->id)
                ->whereDate('sale_date', $date)
                ->where('payment_status', '!=', 'cancelled');
            $this->scopeSalesToBranch($salesQuery, $user, $business);

            $collected = (float) SalePayment::query()
                ->whereHas('sale', function ($q) use ($business, $date) {
                    $q->where('business_id', $business->id)
                        ->whereDate('sale_date', $date)
                        ->where('payment_status', '!=', 'cancelled');
                })
                ->whereDate('created_at', $date)
                ->sum('amount');

            $outstanding = (float) Sale::query()
                ->where('business_id', $business->id)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
                ->sum(DB::raw('total_amount - amount_paid'));

            $grossSales = (float) (clone $salesQuery)->sum('total_amount');
            $collected = $collected > 0 ? $collected : (float) (clone $salesQuery)->sum('amount_paid');

            return [
                'date' => $date,
                'orders' => (clone $salesQuery)->count(),
                'gross_sales' => $grossSales,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'open_shifts' => Shift::query()->where('business_id', $business->id)->where('status', 'open')->count(),
                'today_revenue' => $collected,
                'today_profit' => $profit['day'],
                'net_profit' => $profit,
                'summary' => ['net_profit' => $profit],
                'finance' => ['daily_net_profit' => $profit['day']],
            ];
        }

        $openShift = Shift::openForUser($user->id, $business->id);
        $payload = [
            'date' => $date,
            'open_shift_id' => $openShift?->id,
            'orders' => 0,
            'gross_sales' => 0.0,
            'collected' => 0.0,
        ];

        if ($openShift) {
            $openShift->refreshTotals();
            $payload['orders'] = (int) $openShift->sales_count;
            $payload['gross_sales'] = (float) $openShift->gross_sales;
            $payload['collected'] = (float) $openShift->amount_collected;
        }

        if ($user->can('view_reports') || $user->role === 'owner') {
            $payload['today_profit'] = $profit['day'];
            $payload['net_profit'] = $profit;
        }

        return $payload;
    }

    /**
     * @return array{day: float, period: float}
     */
    public function profitBlock(Business $business, string $date): array
    {
        $dayTotals = $this->ownerReports->buildDayEndTotals($business, $date);
        $dayNet = (float) ($dayTotals['net_profit'] ?? 0);

        $periodNet = 0.0;
        $start = Carbon::parse($date)->startOfMonth();
        $end = Carbon::parse($date);

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $d = $day->toDateString();
            $built = $this->ownerReports->buildDayEndTotals($business, $d);
            $periodNet += (float) ($built['net_profit'] ?? 0);
        }

        return [
            'day' => round($dayNet, 2),
            'period' => round($periodNet, 2),
        ];
    }

    private function scopeSalesToBranch($query, User $user, Business $business): void
    {
        $branchId = $this->activeBranch->activeBranchId();
        if (! $branchId || $user->role !== 'owner') {
            return;
        }

        $query->whereIn('user_id', User::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->pluck('id'));
    }
}
