<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
  public function today(Request $request, DashboardService $dashboard): JsonResponse
  {
    $user = $request->user();
    $businessId = $this->apiBusinessId();
    $business = $this->apiBusiness();
    if (! $business) {
      return $this->error('Business not found.', 404);
    }

    $today = now()->toDateString();

    if ($user->role === 'owner' || $user->can('view_reports')) {
      $salesQuery = Sale::query()
        ->where('business_id', $businessId)
        ->whereDate('sale_date', $today)
        ->where('payment_status', '!=', 'cancelled');
      $this->scopeSalesToBranch($salesQuery);

      $collected = (float) SalePayment::query()
        ->whereHas('sale', function ($q) use ($businessId, $today) {
          $q->where('business_id', $businessId)
            ->whereDate('sale_date', $today)
            ->where('payment_status', '!=', 'cancelled');
        })
        ->whereDate('created_at', $today)
        ->sum('amount');

      $outstanding = (float) Sale::query()
        ->where('business_id', $businessId)
        ->whereNotIn('payment_status', ['paid', 'cancelled'])
        ->whereColumn('total_amount', '>', 'amount_paid')
        ->sum(DB::raw('total_amount - amount_paid'));

      return $this->success([
        'date' => $today,
        'orders' => (clone $salesQuery)->count(),
        'gross_sales' => (float) (clone $salesQuery)->sum('total_amount'),
        'collected' => $collected > 0 ? $collected : (float) (clone $salesQuery)->sum('amount_paid'),
        'outstanding' => $outstanding,
        'open_shifts' => Shift::query()->where('business_id', $businessId)->where('status', 'open')->count(),
        'today_revenue' => $dashboard->todayRevenue($business),
      ]);
    }

    $openShift = Shift::openForUser($user->id, $businessId);
    $shiftStats = [
      'open_shift_id' => $openShift?->id,
      'orders' => 0,
      'gross_sales' => 0.0,
      'collected' => 0.0,
    ];

    if ($openShift) {
      $openShift->refreshTotals();
      $shiftStats['orders'] = (int) $openShift->sales_count;
      $shiftStats['gross_sales'] = (float) $openShift->gross_sales;
      $shiftStats['collected'] = (float) $openShift->amount_collected;
    }

    return $this->success([
      'date' => $today,
      ...$shiftStats,
    ]);
  }

  private function scopeSalesToBranch($query): void
  {
    $ctx = app(ApiTenantContext::class);
    $branchId = $ctx->branchId();
    if (! $branchId || $ctx->user()?->role !== 'owner') {
      return;
    }

    $businessId = $ctx->businessId();
    $query->whereIn('user_id', User::query()
      ->where('business_id', $businessId)
      ->where(function ($q) use ($branchId) {
        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
      })
      ->pluck('id'));
  }
}
