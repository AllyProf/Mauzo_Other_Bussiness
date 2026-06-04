<?php

namespace App\Http\Controllers;

use App\Services\LiveSalesPulseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveSalesController extends Controller
{
    public function index(Request $request, LiveSalesPulseService $pulse)
    {
        $this->authorizeAny(['view_reports', 'view_sales_history', 'process_sales']);

        $user = Auth::user();
        $business = $this->currentBusiness();

        if (! $business) {
            abort(403, 'No business context.');
        }

        $filterContext = $this->liveSalesFilterContext($request);
        $businessWide = $this->actsAsBusinessWideViewer();
        $scopeToStaffOnly = $user->requiresOpenShift() || (! $businessWide && $user->role !== 'owner');

        $snapshot = $pulse->snapshot(
            $user,
            $business,
            $businessWide,
            $scopeToStaffOnly,
            [
                'branch_filter_id' => $filterContext['branchFilterId'],
                'branch_name' => $filterContext['activeBranchName'],
                'business_type_key' => $filterContext['activeBusinessType'],
                'business_type_label' => $filterContext['activeBusinessLabel'],
            ],
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($pulse->jsonPayload($snapshot));
        }

        return view('live-sales.index', array_merge(
            $this->viewData($snapshot),
            $filterContext,
            [
                'filterQuery' => $request->only(['business_type']),
            ],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function liveSalesFilterContext(Request $request): array
    {
        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'];
        $branchFilterId = $filter['branchFilterId'];

        $categoryTemplates = config('category_templates', []);
        $serviceTemplates = config('service_templates', []);

        $types = collect($filter['businessTypes']);

        $serviceTypes = $branchFilterId
            ? collect($business->importedServiceTypesForBranch($branchFilterId))
            : collect($business->serviceBusinessTypesList());

        foreach ($serviceTypes as $type) {
            $key = (string) ($type['key'] ?? '');
            if ($key === '' || $types->contains(fn ($row) => ($row['key'] ?? '') === $key)) {
                continue;
            }

            $types->push([
                'key' => $key,
                'label' => (string) ($type['label'] ?? $key),
                'icon' => $serviceTemplates[$key]['icon'] ?? 'fa-briefcase',
            ]);
        }

        $businessTypes = $types->values()->all();

        if (! $this->actsAsBusinessWideViewer()) {
            $assigned = Auth::user()->assignedBusinessTypeKeys();
            if ($assigned !== []) {
                $businessTypes = collect($businessTypes)
                    ->filter(fn ($type) => in_array($type['key'] ?? '', $assigned, true))
                    ->values()
                    ->all();
            }
        }

        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();
        $activeBusinessType = $filter['activeBusinessType'];
        if ($activeBusinessType && ! in_array($activeBusinessType, $typeKeys, true)) {
            $activeBusinessType = null;
        }

        return [
            'business' => $business,
            'branchFilterId' => $branchFilterId,
            'activeBranchName' => $filter['activeBranchName'],
            'viewingAllBranches' => $filter['viewingAllBranches'],
            'businessTypes' => $businessTypes,
            'multiBusiness' => count($businessTypes) > 1,
            'activeBusinessType' => $activeBusinessType ?: null,
            'activeBusinessLabel' => $activeBusinessType
                ? (collect($businessTypes)->firstWhere('key', $activeBusinessType)['label'] ?? $business->businessTypeLabel($activeBusinessType))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(array $snapshot): array
    {
        $shift = $snapshot['active_shift'];

        return [
            'activeShift' => $shift,
            'pulseMode' => $snapshot['context']['mode'],
            'scopeLabel' => $snapshot['context']['scope_label'],
            'filterNote' => $snapshot['filter_note'] ?? '',
            'totalRevenue' => $snapshot['total_revenue'],
            'shiftProfit' => $snapshot['shift_profit'],
            'todayCash' => $snapshot['today_cash'],
            'todayDigital' => $snapshot['today_digital'],
            'moneyInCirculation' => $snapshot['money_in_circulation'],
            'totalOrders' => $snapshot['total_orders'],
            'activeOrders' => $snapshot['active_orders'],
            'servedOrders' => $snapshot['served_orders'],
            'hourlyData' => $snapshot['hourly_data'],
            'categoryMix' => $snapshot['category_mix'],
            'liveFeed' => $snapshot['live_feed'],
            'staffPulse' => $snapshot['staff_pulse'],
            'topProducts' => $snapshot['top_products'],
            'topServices' => $snapshot['top_services'],
            'marginPercent' => $snapshot['margin_percent'],
        ];
    }
}
