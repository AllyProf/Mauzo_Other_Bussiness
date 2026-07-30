<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Services\ActiveBranchService;
use App\Services\ActiveBusinessService;
use App\Services\LiveSalesPulseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSalesController extends ApiController
{
    public function __construct(private LiveSalesPulseService $pulse)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny([
            'view_live_sales',
            'view_reports',
            'view_sales_history',
            'process_sales',
        ])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();

        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $branchFilterId = $this->branchFilterId($user, $request);
        $this->applyWebBranchContext($user, (int) $business->id, $branchFilterId);

        $filterContext = $this->filterContext($user, $business, $branchFilterId, $request);
        $businessWide = $user->seesBusinessWideData();
        $scopeToStaffOnly = $user->requiresOpenShift() || (! $businessWide && $user->role !== 'owner');

        try {
            $snapshot = $this->pulse->snapshot(
                $user,
                $business,
                $businessWide,
                $scopeToStaffOnly,
                [
                    'branch_filter_id' => $filterContext['branch_id'],
                    'branch_name' => $filterContext['branch_name'],
                    'business_type_key' => $filterContext['active_business_type'],
                    'business_type_label' => $filterContext['active_business_label'],
                ],
            );

            return $this->success([
                'filters' => $filterContext,
                'pulse' => $this->pulse->apiPayload($snapshot),
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to load live sales: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filterContext($user, $business, ?int $branchFilterId, Request $request): array
    {
        $categoryTemplates = config('category_templates', []);
        $serviceTemplates = config('service_templates', []);

        if ($branchFilterId) {
            $types = collect($business->branchPosBusinessTypesMeta($branchFilterId))
                ->map(function ($type) use ($categoryTemplates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $type['icon'] ?? ($categoryTemplates[$key]['icon'] ?? 'fa-store'),
                    ];
                });
            $serviceTypes = collect($business->importedServiceTypesForBranch($branchFilterId));
        } else {
            $types = collect($business->posBusinessTypesMeta());
            $serviceTypes = collect($business->serviceBusinessTypesList());
        }

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

        if (! $user->seesBusinessWideData()) {
            $assigned = $user->assignedBusinessTypeKeys();
            if ($assigned !== []) {
                $businessTypes = collect($businessTypes)
                    ->filter(fn ($type) => in_array($type['key'] ?? '', $assigned, true))
                    ->values()
                    ->all();
            }
        }

        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();
        $activeBusinessType = $request->query('business_type');
        if (! $activeBusinessType || $activeBusinessType === 'all' || ! in_array($activeBusinessType, $typeKeys, true)) {
            $activeBusinessType = null;
        }

        return [
            'branch_id' => $branchFilterId,
            'branch_name' => $branchFilterId
                ? (Branch::find($branchFilterId)?->name ?? $user->branch?->name ?? 'Branch')
                : null,
            'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
            'business_types' => $businessTypes,
            'multi_business' => count($businessTypes) > 1,
            'active_business_type' => $activeBusinessType,
            'active_business_label' => $activeBusinessType
                ? (collect($businessTypes)->firstWhere('key', $activeBusinessType)['label']
                    ?? $business->businessTypeLabel($activeBusinessType))
                : null,
        ];
    }

    private function branchFilterId($user, Request $request): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if ($request->filled('branch_id')) {
            $requested = (int) $request->input('branch_id');
            if ($requested > 0 && $this->tenantContext()->ownerBranches()->contains('id', $requested)) {
                return $requested;
            }
        }

        return $this->tenantContext()->branchId();
    }

    private function applyWebBranchContext($user, int $businessId, ?int $branchFilterId): void
    {
        if ($user->role === 'owner') {
            app(ActiveBusinessService::class)->setActiveBusiness($businessId);
            if ($user->seesBusinessWideData()) {
                app(ActiveBranchService::class)->setActiveBranch($branchFilterId);
            }
        }
    }
}
