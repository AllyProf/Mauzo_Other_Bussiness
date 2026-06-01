<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ActiveBranchService;
use App\Services\BusinessSettingsService;
use App\Services\DashboardService;
use App\Services\ItemStockDisplayService;
use App\Services\SalesTargetService;
use App\Services\ShiftPolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, ActiveBranchService $branchService, BusinessSettingsService $settingsService)
    {
        $user = $request->user();

        if ($user->needsShiftOpened()) {
            return redirect()->route('shifts.create');
        }

        if ($user->role === 'super_admin') {
            return view('home', [
                'totalBusinesses' => Business::count(),
                'activeBusinesses' => Business::where('is_active', true)->count(),
                'expiringThisWeek' => Business::where('is_active', true)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', Carbon::now()->addDays(7))
                    ->whereDate('expiry_date', '>=', Carbon::now())
                    ->count(),
                'openTickets' => Ticket::where('status', 'open')->count(),
                'expiringBusinesses' => Business::with('plan')
                    ->where('is_active', true)
                    ->where('pending_approval', false)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', Carbon::now()->addDays(7))
                    ->whereDate('expiry_date', '>=', Carbon::now())
                    ->get(),
                'pendingRegistrations' => Business::with(['plan', 'owner'])
                    ->where('pending_approval', true)
                    ->latest()
                    ->get(),
                'allBusinesses' => Business::with('plan')->latest()->get(),
            ]);
        }

        $businessId = $user->business_id;
        $isSalesOfficerDashboard = $user->requiresOpenShift() && $user->can('process_sales');

        if ($isSalesOfficerDashboard) {
            return view('home', array_merge(
                $this->salesOfficerDashboardData($user, $businessId, $branchService),
                $this->staffTargetProgress($user),
            ));
        }

        if ($user->role === 'owner' && $user->business) {
            return view('home', array_merge(
                ['isOwnerDashboard' => true],
                app(DashboardService::class)->ownerDashboard($user->business, $user),
            ));
        }

        $staffQuery = User::query()
            ->where('business_id', $businessId)
            ->where('role', '!=', 'owner');
        $branchService->scopeUsersInActiveBranch($staffQuery);

        $todaySalesQuery = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', today())
            ->where('payment_status', '!=', 'cancelled');
        $branchService->scopeRecordsByBranchUsers($todaySalesQuery);

        return view('home', array_merge([
            'staffCount' => $staffQuery->count(),
            'itemsCount' => Item::where('business_id', $businessId)->count(),
            'todaySalesTotal' => (float) $todaySalesQuery->sum('amount_paid'),
            'dashboardAlerts' => $user->role === 'owner' && $user->business
                ? $settingsService->dashboardAlerts($user->business)
                : [],
            'activeBranchLabel' => $branchService->activeBranchLabel(),
            'viewingAllBranches' => $branchService->isViewingAllBranches(),
        ], $this->staffTargetProgress($user)));
    }

    private function staffTargetProgress(User $user): array
    {
        if (! $user->business) {
            return ['targetProgress' => collect()];
        }

        return [
            'targetProgress' => app(SalesTargetService::class)->staffTargetProgress($user, $user->business),
        ];
    }

    private function salesOfficerDashboardData(User $user, int $businessId, ActiveBranchService $branchService): array
    {
        $business = $user->business;
        $shiftPolicy = app(ShiftPolicyService::class);
        $lowStockThreshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);
        $openShift = Shift::openForUser($user->id, $businessId);

        $verifyItems = Item::where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->whereNotNull('category_id')
            ->with(['category', 'packagings.packagingType', 'receivingPackaging'])
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($lowStockThreshold) {
                $stockInfo = app(ItemStockDisplayService::class)->format($item);
                $qty = $stockInfo['pieces'];

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category->name,
                    'category_slug' => \Illuminate\Support\Str::slug($item->category->name),
                    'unit' => $stockInfo['unit_name'],
                    'formatted_quantity' => $stockInfo['stock_display'],
                    'is_low_stock' => $qty <= $lowStockThreshold,
                ];
            });

        $verifyCategories = $verifyItems->pluck('category')->unique()->sort()->values();

        $data = [
            'isSalesOfficerDashboard' => true,
            'needsShift' => ! $openShift,
            'openShift' => $openShift,
            'verifyItems' => $verifyItems,
            'verifyCategories' => $verifyCategories,
            'lowStockThreshold' => $lowStockThreshold,
            'activeBranchLabel' => $branchService->activeBranchLabel(),
            'shiftOpenCheck' => $shiftPolicy->canOpenShift($business),
            'shiftOpenWindowLabel' => $shiftPolicy->openWindowLabel($business),
        ];

        if ($openShift) {
            $openShift->refreshTotals();
            $openShift->refresh();

            $data['shiftOverdueStatus'] = $shiftPolicy->shiftOverdueStatus($openShift, $business);

            $shiftSalesQuery = Sale::where('shift_id', $openShift->id)
                ->where('payment_status', '!=', 'cancelled');

            $data['shiftOrderCount'] = (int) $openShift->sales_count;
            $data['shiftRevenue'] = (float) $openShift->amount_collected;
            $data['stockItemsCount'] = $verifyItems->count();
            $data['pendingPayments'] = (clone $shiftSalesQuery)
                ->whereIn('payment_status', ['pending', 'partial', 'debt'])
                ->count();
            $data['recentSales'] = (clone $shiftSalesQuery)
                ->with('customer')
                ->latest()
                ->limit(10)
                ->get();
            $data['lowStockItems'] = Item::where('business_id', $businessId)
                ->where('current_stock', '>', 0)
                ->where('current_stock', '<=', $lowStockThreshold)
                ->whereNotNull('category_id')
                ->orderBy('current_stock')
                ->limit(10)
                ->get(['id', 'name', 'current_stock']);
        }

        return $data;
    }
}
