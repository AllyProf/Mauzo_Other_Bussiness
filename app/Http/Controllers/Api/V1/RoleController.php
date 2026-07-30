<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\StaffApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends ApiController
{
    public function __construct(private StaffApiService $staff)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        return $this->success($this->staff->listRoles($this->apiBusinessId()));
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        return $this->success($this->staff->createRoleForm());
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->storeRole($this->apiBusinessId(), $request->all());

            return $this->success($data, 'Role created successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to create role: '.$e->getMessage(), 500);
        }
    }
}
