<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\BusinessOwnerExpense;
use App\Services\PettyCashApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PettyCashController extends ApiController
{
    public function __construct(private PettyCashApiService $pettyCash)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_petty_cash', 'view_reports'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->branchFilterId($user, $request);

        $data = $this->pettyCash->index(
            $user,
            $business,
            $branchFilterId,
            $request->input('business_type'),
            $request->input('date'),
            $request->only(['start_date', 'end_date', 'fund_source', 'category', 'page'])
        );

        return $this->success($data);
    }

    public function balances(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_petty_cash', 'view_reports'])) {
            return $deny;
        }

        $request->validate([
            'date' => 'required|date',
            'business_type' => 'nullable|string|max:100',
        ]);

        $business = $this->apiBusiness();
        $data = $this->pettyCash->balances(
            $business,
            $request->input('date'),
            $request->input('business_type')
        );

        return $this->success($data);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_petty_cash'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->branchFilterId($user, $request);

        try {
            $data = $this->pettyCash->store(
                $user,
                $business,
                $branchFilterId,
                $request->all()
            );

            return $this->success($data, 'Petty cash issued successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to issue petty cash: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, BusinessOwnerExpense $expense): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_petty_cash'])) {
            return $deny;
        }

        $user = $request->user();
        $business = $this->apiBusiness();
        $branchFilterId = $this->branchFilterId($user, $request);

        try {
            $data = $this->pettyCash->destroy(
                $user,
                $business,
                $branchFilterId,
                $expense
            );

            return $this->success($data, 'Petty cash entry removed.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to remove petty cash: ' . $e->getMessage(), 500);
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
