<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\SalesTarget;
use App\Services\SalesTargetApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesTargetController extends ApiController
{
    public function __construct(private SalesTargetApiService $salesTargets)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_sales_targets', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $user = $request->user();
        $branchFilterId = $this->salesTargets->branchFilterId(
            $user,
            $this->tenantContext(),
            $request->filled('branch_id') ? (int) $request->input('branch_id') : null
        );

        try {
            $data = $this->salesTargets->index(
                $user,
                $business,
                $branchFilterId,
                $request->input('business_type'),
                max(1, (int) $request->input('page', 1))
            );

            return $this->success($data);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Sales targets are not available on your current plan.';

            return $this->error($message, 403, $e->errors());
        }
    }

    public function show(SalesTarget $salesTarget): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_sales_targets', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            return $this->success($this->salesTargets->show($business, $salesTarget));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Sales targets are not available on your current plan.';

            return $this->error($message, 403, $e->errors());
        }
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_sales_targets', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->salesTargets->store($business, $request->user(), $request->all());

            return $this->success($data, 'Sales target saved successfully.', 201);
        } catch (ValidationException $e) {
            $status = isset($e->errors()['plan']) ? 403 : 422;

            return $this->error(
                $status === 403
                    ? (collect($e->errors())->flatten()->first() ?? 'Forbidden')
                    : 'Validation failed.',
                $status,
                $e->errors()
            );
        } catch (\Throwable $e) {
            return $this->error('Failed to save sales target: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, SalesTarget $salesTarget): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_sales_targets', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->salesTargets->update($business, $salesTarget, $request->all());

            return $this->success($data, 'Sales target updated successfully.');
        } catch (ValidationException $e) {
            $status = isset($e->errors()['plan']) ? 403 : 422;

            return $this->error(
                $status === 403
                    ? (collect($e->errors())->flatten()->first() ?? 'Forbidden')
                    : 'Validation failed.',
                $status,
                $e->errors()
            );
        } catch (\Throwable $e) {
            return $this->error('Failed to update sales target: '.$e->getMessage(), 500);
        }
    }

    public function destroy(SalesTarget $salesTarget): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_sales_targets', 'manage_business_settings'])) {
            return $deny;
        }

        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->salesTargets->destroy($business, $salesTarget);

            return $this->success($data, 'Sales target removed.');
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Sales targets are not available on your current plan.';

            return $this->error($message, 403, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to remove sales target: '.$e->getMessage(), 500);
        }
    }
}
