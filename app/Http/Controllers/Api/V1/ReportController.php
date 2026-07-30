<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\BusinessReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(private BusinessReportApiService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->reports->branchFilterId($user, $this->tenantContext(), $request);

        return $this->success(
            $this->reports->indexMeta($user, $business, $branchFilterId, $request)
        );
    }

    public function circulationProfit(Request $request): JsonResponse
    {
        return $this->report($request, 'circulation-profit');
    }

    public function dailySales(Request $request): JsonResponse
    {
        return $this->report($request, 'daily-sales');
    }

    public function expenses(Request $request): JsonResponse
    {
        return $this->report($request, 'expenses');
    }

    public function profit(Request $request): JsonResponse
    {
        return $this->report($request, 'profit');
    }

    public function salesAnalytics(Request $request): JsonResponse
    {
        return $this->report($request, 'sales-analytics');
    }

    public function products(Request $request): JsonResponse
    {
        return $this->report($request, 'products');
    }

    public function debts(Request $request): JsonResponse
    {
        return $this->report($request, 'debts');
    }

    private function report(Request $request, string $key): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();

        if (! $business) {
            return $this->error('No active business.', 422);
        }

        $branchFilterId = $this->reports->branchFilterId($user, $this->tenantContext(), $request);

        try {
            return $this->success(
                $this->reports->build($user, $business, $branchFilterId, $request, $key)
            );
        } catch (\Throwable $e) {
            return $this->error('Failed to load report: '.$e->getMessage(), 500);
        }
    }
}
