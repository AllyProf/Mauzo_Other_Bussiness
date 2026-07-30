<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Services\BranchApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchController extends ApiController
{
    public function __construct(private BranchApiService $branches)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->can('manage_branches')) {
            $business = $this->apiBusiness();
            if (! $business) {
                return $this->error('No business context.', 422);
            }

            return $this->success(
                $this->branches->index($user, $business, $this->tenantContext())
            );
        }

        return $this->success(
            $this->branches->referenceList($user, $this->tenantContext())
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_branches'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();

        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->branches->store($user, $business, $request->all());

            return $this->success($data, 'Branch registered successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to register branch: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_branches'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();

        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->branches->update($user, $business, $branch, $request->all());

            return $this->success($data, 'Branch updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update branch: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_branches'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();

        if (! $business) {
            return $this->error('No business context.', 422);
        }

        try {
            $data = $this->branches->destroy($user, $business, $branch);

            return $this->success($data, 'Branch deleted successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to delete branch: '.$e->getMessage(), 500);
        }
    }
}
