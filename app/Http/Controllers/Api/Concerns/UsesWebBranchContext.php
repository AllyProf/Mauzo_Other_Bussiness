<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Branch;
use App\Services\ActiveBranchService;
use App\Services\ActiveBusinessService;
use Illuminate\Http\Request;

/**
 * Lets API controllers reuse web Controller helpers (active_branch_id(), branchBusinessFilterContext(),
 * scope*ForActiveBranch()) by mirroring the API tenant context into the web active business/branch.
 */
trait UsesWebBranchContext
{
    /**
     * Staff: locked to own branch. Owners: ?branch_id= (0 = all branches) or the branch picked via /auth/switch-branch.
     */
    protected function apiBranchFilterId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user->seesBusinessWideData()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        if ($request->exists('branch_id')) {
            $requested = (int) $request->input('branch_id');
            if ($requested > 0 && $this->tenantContext()->ownerBranches()->contains('id', $requested)) {
                return $requested;
            }

            return null;
        }

        return $this->tenantContext()->branchId();
    }

    protected function bootWebBranchContext(Request $request): ?int
    {
        $user = $request->user();
        $branchFilterId = $this->apiBranchFilterId($request);

        if ($user->role === 'owner') {
            app(ActiveBusinessService::class)->setActiveBusiness($this->apiBusinessId());
            if ($user->seesBusinessWideData()) {
                app(ActiveBranchService::class)->setActiveBranch($branchFilterId);
            }
        }

        return $branchFilterId;
    }

    /**
     * @param  array<string, mixed>  $filter  Result of branchBusinessFilterContext()
     * @return array<string, mixed>
     */
    protected function filterMetaPayload(array $filter): array
    {
        return [
            'branch_id' => $filter['branchFilterId'] ?? null,
            'branch_name' => $filter['activeBranchName'] ?? null,
            'viewing_all_branches' => (bool) ($filter['viewingAllBranches'] ?? false),
            'business_types' => array_values($filter['businessTypes'] ?? []),
            'multi_business' => (bool) ($filter['multiBusiness'] ?? false),
            'active_business_type' => $filter['activeBusinessType'] ?? null,
        ];
    }

    protected function branchName(?int $branchId): ?string
    {
        return $branchId ? Branch::find($branchId)?->name : null;
    }

    /**
     * @return array<string, int>
     */
    protected function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    protected function perPage(Request $request, int $default = 20): int
    {
        return max(1, min(50, (int) $request->get('per_page', $default)));
    }
}
