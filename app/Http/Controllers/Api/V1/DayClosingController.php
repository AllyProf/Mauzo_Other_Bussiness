<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\DayClosing;
use App\Services\DayClosingHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DayClosingController extends ApiController
{
    public function __construct(private DayClosingHandoverService $handover)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['submit_day_closing', 'verify_day_closing', 'process_sales', 'view_reports'])) {
            return $deny;
        }

        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'closing_date' => 'nullable|date',
        ]);

        try {
            $data = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->buildPreview(
                    $request->user(),
                    $this->apiBusinessId(),
                    $request->filled('shift_id') ? (int) $request->shift_id : null,
                    $request->get('closing_date')
                );

            return $this->success($data);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['submit_day_closing', 'process_sales'])) {
            return $deny;
        }

        try {
            $closing = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->submitHandover($request->user(), $this->apiBusinessId(), $request->all());

            return $this->success([
                'handover' => $this->handover
                    ->forBusiness($this->apiBusinessId())
                    ->handoverDetail($closing, $request->user()),
            ], 'Handover submitted successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to submit handover: '.$e->getMessage(), 500);
        }
    }

    public function review(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'owner' && ! $request->user()->can('view_boss_financial_review')) {
            return $this->forbidden('Only owners can open the boss day-closing review.');
        }

        $request->validate([
            'date' => 'required|date',
            'handover_id' => 'nullable|integer|exists:day_closings,id',
        ]);

        try {
            $data = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->buildBossDayReview(
                    $request->user(),
                    $this->apiBusinessId(),
                    $request->get('date'),
                    $request->filled('handover_id') ? (int) $request->handover_id : null
                );

            return $this->success($data);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny([
            'verify_day_closing',
            'view_reports',
            'view_closing_history',
            'submit_day_closing',
            'process_sales',
        ])) {
            return $deny;
        }

        $businessId = $this->apiBusinessId();
        $user = $request->user();
        $status = $request->get('status');

        $query = DayClosing::query()
            ->where('business_id', $businessId)
            ->with(['user:id,name', 'shift', 'verifier:id,name'])
            ->latest('submitted_at')
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($request->filled('date')) {
            $query->whereDate('closing_date', $request->get('date'));
        }

        if ($user->role !== 'owner' && ! $user->can('verify_day_closing')) {
            $query->where('user_id', $user->id);
        }

        $closings = $query->paginate(min(50, (int) $request->get('per_page', 20)));

        return $this->success([
            'handovers' => collect($closings->items())->map(fn (DayClosing $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'closing_date' => $c->closing_date->toDateString(),
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'staff' => ['id' => $c->user?->id, 'name' => $c->user?->name],
                'shift_id' => $c->shift_id,
                'net_amount' => (float) $c->net_amount,
                'money_short' => (float) ($c->money_short ?? 0),
                'verifier' => $c->verifier?->name,
                'review_url' => '/day-closing?date='.$c->closing_date->toDateString().'#handover-'.$c->id,
            ])->values(),
            'meta' => [
                'current_page' => $closings->currentPage(),
                'last_page' => $closings->lastPage(),
                'per_page' => $closings->perPage(),
                'total' => $closings->total(),
                'date' => $request->get('date'),
            ],
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'owner') {
            return $this->forbidden('Only owners can view pending handovers to verify.');
        }

        $businessId = $this->apiBusinessId();

        $pending = DayClosing::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['submitted', 'disputed'])
            ->with(['user:id,name', 'shift'])
            ->orderBy('submitted_at')
            ->get();

        return $this->success([
            'pending' => $pending->map(fn (DayClosing $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'closing_date' => $c->closing_date->toDateString(),
                'submitted_at' => $c->submitted_at?->toIso8601String(),
                'staff' => ['id' => $c->user?->id, 'name' => $c->user?->name],
                'shift_id' => $c->shift_id,
                'net_amount' => (float) $c->net_amount,
            ])->values(),
        ]);
    }

    public function show(Request $request, DayClosing $dayClosing): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['submit_day_closing', 'verify_day_closing', 'view_reports', 'view_closing_history'])) {
            return $deny;
        }

        if ($dayClosing->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $user = $request->user();
        if ($user->role !== 'owner' && ! $user->can('verify_day_closing') && $dayClosing->user_id !== $user->id) {
            return $this->forbidden('You can only view your own handovers.');
        }

        return $this->success([
            'handover' => $this->handover
                ->forBusiness($this->apiBusinessId())
                ->handoverDetail($dayClosing, $user),
        ]);
    }

    public function verify(Request $request, DayClosing $dayClosing): JsonResponse
    {
        if ($request->user()->role !== 'owner') {
            return $this->forbidden('Only the business owner can verify staff handovers.');
        }

        if ($dayClosing->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        try {
            $closing = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->verifyHandover($request->user(), $dayClosing, $request->all());

            return $this->success([
                'handover' => $this->handover
                    ->forBusiness($this->apiBusinessId())
                    ->handoverDetail($closing, $request->user()),
            ], $closing->status === 'disputed' ? 'Handover marked as disputed.' : 'Handover verified.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
