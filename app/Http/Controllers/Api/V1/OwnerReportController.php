<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\BusinessOwnerExpense;
use App\Services\OwnerReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerReportController extends ApiController
{
    public function __construct(private OwnerReportApiService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user);

        $data = $this->reports->listMasterSheet(
            $user,
            $this->apiBusinessId(),
            $branchFilterId,
            $request
        );

        $data['meta'] = array_merge(
            $data['meta'] ?? [],
            $this->reports->listMeta($user, $this->apiBusinessId(), $branchFilterId)
        );

        return $this->success($data);
    }

    public function show(Request $request, string $date): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user);

        try {
            $data = $this->reports->showDay(
                $user,
                $this->apiBusinessId(),
                $branchFilterId,
                $date
            );

            return $this->success($data + [
                'meta' => $this->reports->listMeta($user, $this->apiBusinessId(), $branchFilterId),
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to load owner report: '.$e->getMessage(), 500);
        }
    }

    public function storeExpense(Request $request, string $date): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user, $request);

        try {
            $data = $this->reports->storeExpense(
                $user,
                $this->apiBusinessId(),
                $branchFilterId,
                $date,
                $request->all()
            );

            return $this->success($data, 'Expense recorded. Circulation and profit updated.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to record expense: '.$e->getMessage(), 500);
        }
    }

    public function destroyExpense(Request $request, string $date, BusinessOwnerExpense $expense): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_reports'])) {
            return $deny;
        }

        if ($expense->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user);

        try {
            $data = $this->reports->destroyExpense(
                $user,
                $this->apiBusinessId(),
                $branchFilterId,
                $date,
                $expense
            );

            return $this->success($data, 'Expense removed.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to delete expense: '.$e->getMessage(), 500);
        }
    }

    public function finalize(Request $request, string $date): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['finalize_reports', 'view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user);

        try {
            $data = $this->reports->finalizeDay(
                $user,
                $this->apiBusinessId(),
                $branchFilterId,
                $date,
                $request->all()
            );

            return $this->success($data, 'Daily report finalized. Circulation balance carried to the next day.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to finalize: '.$e->getMessage(), 500);
        }
    }

    private function branchFilterId($user, ?Request $request = null): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if ($request?->filled('branch_id')) {
            $requested = (int) $request->input('branch_id');
            if ($requested > 0 && $this->tenantContext()->ownerBranches()->contains('id', $requested)) {
                return $requested;
            }
        }

        return $this->tenantContext()->branchId();
    }
}
