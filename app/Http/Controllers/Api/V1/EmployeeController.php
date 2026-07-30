<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\StaffApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeController extends ApiController
{
    public function __construct(private StaffApiService $staff)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->staff->branchFilterId($user, $this->tenantContext());

        if ($search = trim((string) $request->get('q', ''))) {
            // Search is applied in-memory after fetch to keep branch scoping simple.
            $data = $this->staff->listEmployees($user, $this->apiBusinessId(), $branchFilterId);
            $needle = mb_strtolower($search);
            $data['employees'] = collect($data['employees'])
                ->filter(function (array $employee) use ($needle) {
                    return str_contains(mb_strtolower((string) ($employee['name'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($employee['email'] ?? '')), $needle)
                        || str_contains(mb_strtolower((string) ($employee['phone'] ?? '')), $needle);
                })
                ->values()
                ->all();
            $data['meta']['employees_count'] = count($data['employees']);
            $data['meta']['search'] = $search;

            return $this->success($data);
        }

        return $this->success(
            $this->staff->listEmployees($user, $this->apiBusinessId(), $branchFilterId)
        );
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->staff->branchFilterId($user, $this->tenantContext());
        $selectedBranchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        return $this->success(
            $this->staff->createEmployeeForm($user, $this->apiBusinessId(), $branchFilterId, $selectedBranchId)
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->storeEmployee(
                $request->user(),
                $this->apiBusinessId(),
                $request->all()
            );

            $message = 'Staff member added successfully.';
            if (($data['notifications']['sms_sent'] ?? false)) {
                $message .= ' Login details were sent by SMS.';
            }
            if (($data['notifications']['email_sent'] ?? false)) {
                $message .= ' Login details were also sent by email.';
            }

            return $this->success($data, $message, 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to add employee: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, User $employee): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->updateEmployee(
                $request->user(),
                $this->apiBusinessId(),
                $employee,
                $request->all()
            );

            $message = 'Staff member updated successfully.';
            if (($data['notifications']['password_sms_sent'] ?? false)) {
                $message .= ' New password sent by SMS.';
            }
            if (($data['notifications']['password_email_sent'] ?? false)) {
                $message .= ' New password sent by email.';
            }

            return $this->success($data, $message);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to update employee: '.$e->getMessage(), 500);
        }
    }

    public function resetPassword(Request $request, User $employee): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->resetPassword(
                $request->user(),
                $this->apiBusinessId(),
                $employee
            );

            $message = "New password generated for {$employee->name}.";
            if (($data['notifications']['sms_sent'] ?? false)) {
                $message .= ' An SMS was sent to the staff phone number.';
            }
            if (($data['notifications']['email_sent'] ?? false)) {
                $message .= ' An email was sent to the staff email address.';
            }

            return $this->success($data, $message);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to reset password: '.$e->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request, User $employee): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->toggleStatus(
                $request->user(),
                $this->apiBusinessId(),
                $employee
            );

            return $this->success($data, "{$employee->name} has been {$data['status']}.");
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to toggle status: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, User $employee): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_staff'])) {
            return $deny;
        }

        try {
            $data = $this->staff->destroyEmployee(
                $request->user(),
                $this->apiBusinessId(),
                $employee
            );

            return $this->success($data, 'Staff member removed.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to delete employee: '.$e->getMessage(), 500);
        }
    }
}
