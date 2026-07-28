<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\Receiving;
use App\Services\ReceivingApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReceivingController extends ApiController
{
    public function __construct(private ReceivingApiService $receivings)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['receive_stock'])) {
            return $deny;
        }

        $data = $this->receivings->listReceivings(
            $request->user(),
            $this->apiBusinessId(),
            $this->branchFilterId($request->user()),
            $request
        );

        return $this->success($data);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['receive_stock'])) {
            return $deny;
        }

        $user = $request->user();
        $branchFilterId = $this->branchFilterId($user);
        $branches = $this->allBusinessBranches();

        $form = $this->receivings->buildCreateForm($user, $this->apiBusinessId(), $branchFilterId);

        return $this->success($form + [
            'meta' => [
                'branch_filter_id' => $branchFilterId,
                'active_branch_name' => $branchFilterId
                    ? ($this->tenantContext()->branch()?->name ?? Branch::find($branchFilterId)?->name)
                    : null,
                'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
                'can_pick_branch' => $user->seesBusinessWideData() && $branches->count() > 1,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['receive_stock'])) {
            return $deny;
        }

        try {
            $receiving = $this->receivings->createReceiving(
                $request->user(),
                $this->apiBusinessId(),
                $this->tenantContext()->branchId(),
                $request->all()
            );

            return $this->success([
                'receiving' => $this->receivings->receivingDetail($receiving, $request->user()),
            ], "Stock-in record created successfully ({$receiving->reference_no}).", 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Something went wrong: '.$e->getMessage(), 500);
        }
    }

    public function show(Request $request, Receiving $receiving): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['receive_stock'])) {
            return $deny;
        }

        if ($receiving->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if ($deny = $this->ensureCanAccessReceiving($request->user(), $receiving)) {
            return $deny;
        }

        return $this->success($this->receivings->receivingDetail($receiving, $request->user()));
    }

    public function cancelPreview(Request $request, Receiving $receiving): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['cancel_receiving', 'receive_stock'])) {
            return $deny;
        }

        if ($receiving->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if ($deny = $this->ensureCanAccessReceiving($request->user(), $receiving)) {
            return $deny;
        }

        return $this->success($this->receivings->partialCancelPreview($receiving));
    }

    public function cancel(Request $request, Receiving $receiving): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['cancel_receiving', 'receive_stock'])) {
            return $deny;
        }

        if ($receiving->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if ($deny = $this->ensureCanAccessReceiving($request->user(), $receiving)) {
            return $deny;
        }

        $request->validate([
            'partial_ok' => 'nullable|boolean',
        ]);

        try {
            $result = $this->receivings->cancelReceiving(
                $request->user(),
                $receiving,
                $request->boolean('partial_ok')
            );

            if ($result['partial'] ?? false) {
                return $this->success($result, $result['message']);
            }

            return $this->success($result, $result['message']);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['partial_cancel_required'])) {
                return response()->json([
                    'success' => false,
                    'message' => $errors['partial_cancel_required'][0] ?? 'Partial cancellation confirmation required.',
                    'errors' => $errors,
                    'data' => $this->receivings->partialCancelPreview($receiving),
                ], 409);
            }

            return $this->error($e->getMessage(), 422, $errors);
        } catch (\Throwable $e) {
            return $this->error('Error cancelling receiving: '.$e->getMessage(), 500);
        }
    }

    private function branchFilterId($user): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $this->tenantContext()->branchId();
    }

    private function allBusinessBranches()
    {
        return Branch::query()
            ->where('business_id', $this->apiBusinessId())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function ensureCanAccessReceiving($user, Receiving $receiving): ?JsonResponse
    {
        if (! $user->seesBusinessWideData() && $user->branch_id && (int) $receiving->branch_id !== (int) $user->branch_id) {
            return $this->forbidden('You do not have access to this receiving.');
        }

        $branchFilterId = $this->branchFilterId($user);
        if ($user->seesBusinessWideData() && $branchFilterId && (int) $receiving->branch_id !== $branchFilterId) {
            return $this->forbidden('Switch to the correct branch to view this receiving.');
        }

        return null;
    }
}
