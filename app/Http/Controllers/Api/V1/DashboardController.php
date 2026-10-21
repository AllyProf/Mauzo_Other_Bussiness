<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Api\ApiDashboardTodayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function today(Request $request, ApiDashboardTodayService $todayService): JsonResponse
    {
        $user = $request->user();
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('Business not found.', 404);
        }

        $branchFilterId = $this->branchFilterId($user, $request);

        return $this->success($todayService->snapshot($user, $business, $branchFilterId));
    }

    private function branchFilterId(User $user, Request $request): ?int
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
}
