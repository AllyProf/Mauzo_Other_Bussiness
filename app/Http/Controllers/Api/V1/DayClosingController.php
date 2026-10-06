<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\DayClosing;
use App\Models\Shift;
use App\Services\DayClosingHandoverService;
use App\Services\MoneyShortSettlementService;
use App\Services\OwnerReportApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DayClosingController extends ApiController
{
    use UsesWebBranchContext;

    public function __construct(private DayClosingHandoverService $handover)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['submit_day_closing', 'verify_day_closing', 'process_sales', 'view_reports'])) {
            return $deny;
        }

        if (! $request->filled('shift_id') && $request->filled('shift')) {
            $request->merge(['shift_id' => $request->input('shift')]);
        }

        $request->validate([
            'shift_id' => 'nullable|integer',
            'closing_date' => 'nullable|date',
            'handover_context' => 'nullable|in:retail,services',
        ]);

        try {
            $data = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->useHandoverContext($request->get('handover_context'))
                ->buildPreview(
                    $request->user(),
                    $this->apiBusinessId(),
                    $request->filled('shift_id') ? (int) $request->shift_id : null,
                    $request->get('closing_date')
                );

            return $this->success($data);
        } catch (ValidationException $e) {
            return $this->error($this->firstErrorMessage($e), 422, $this->flattenErrorCode($e->errors()));
        }
    }

    private function firstErrorMessage(ValidationException $e): string
    {
        foreach ($e->errors() as $field => $messages) {
            if ($field !== 'code') {
                return (string) ($messages[0] ?? $e->getMessage());
            }
        }

        return $e->getMessage();
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, mixed>
     */
    private function flattenErrorCode(array $errors): array
    {
        if (isset($errors['code']) && is_array($errors['code'])) {
            $errors['code'] = $errors['code'][0] ?? null;
        }

        return $errors;
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['submit_day_closing', 'process_sales'])) {
            return $deny;
        }

        try {
            $closing = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->useHandoverContext($request->get('handover_context'))
                ->submitHandover($request->user(), $this->apiBusinessId(), $request->all());

            return $this->success([
                'handover' => $this->handover
                    ->forBusiness($this->apiBusinessId())
                    ->handoverDetail($closing, $request->user()),
                'shift_closed' => (bool) $closing->shift_id,
                'next' => 'shifts.open',
            ], $closing->shift_id
                ? 'Handover submitted and your shift is now closed.'
                : 'Daily reconciliation submitted to your boss successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error($this->firstErrorMessage($e), 422, $this->flattenErrorCode($e->errors()));
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

    public function ownerDirectPreview(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'owner') {
            return $this->forbidden('Only the business owner can post direct POS sales.');
        }

        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $data = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->ownerDirectPreview(
                    $request->user(),
                    $this->apiBusinessId(),
                    $request->get('date')
                );

            return $this->success($data);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function postOwnerDirectSales(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'owner') {
            return $this->forbidden('Only the business owner can post direct POS sales.');
        }

        try {
            $data = $this->handover
                ->forBusiness($this->apiBusinessId())
                ->postOwnerDirectSalesApi(
                    $request->user(),
                    $this->apiBusinessId(),
                    $request->all()
                );

            $message = 'Your direct POS sales are posted to the Master Sheet — finalize the day there when ready.';

            if (($data['money_short'] ?? 0) > 0) {
                $message = 'Your direct POS sales are posted with a money short. Finalize the day on the Master Sheet when ready.';
            }

            return $this->success($data, $message, 201);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage() ?: 'Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to post owner sales: '.$e->getMessage(), 500);
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
        if ($request->filled('date_from')) {
            $query->whereDate('closing_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('closing_date', '<=', $request->date('date_to'));
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

    /**
     * Same as web /day-closing/history.
     */
    public function history(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_closing_history', 'view_reports', 'verify_day_closing'])) {
            return $deny;
        }

        $this->bootWebBranchContext($request);
        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'];
        $activeBusinessType = $filter['activeBusinessType'];
        $settlementService = app(MoneyShortSettlementService::class);

        $query = DayClosing::where('business_id', $this->apiBusinessId())
            ->with(['user:id,name', 'verifier:id,name', 'shift']);

        $this->scopeDayClosingsForActiveBranch($query);

        if ($activeBusinessType) {
            $settlementService->scopeClosingsForBusinessType($query, $this->apiBusinessId(), $activeBusinessType);
        }
        if (in_array($request->get('status'), ['submitted', 'verified', 'disputed'], true)) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('closing_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('closing_date', '<=', $request->get('date_to'));
        }

        $closings = $query->latest('submitted_at')->latest('id')->paginate($this->perPage($request));

        return $this->success([
            'closings' => collect($closings->items())->map(function (DayClosing $c) use ($settlementService, $business, $activeBusinessType, $filter) {
                $typeKeys = $settlementService->closingBusinessTypeKeys($c);

                return [
                    'id' => $c->id,
                    'closing_date' => $c->closing_date->toDateString(),
                    'closing_date_label' => $c->closing_date->format('M d, Y'),
                    'staff' => ['id' => $c->user?->id, 'name' => $c->user?->name],
                    'shift_id' => $c->shift_id,
                    'business_types' => $activeBusinessType
                        ? [collect($filter['businessTypes'])->firstWhere('key', $activeBusinessType)['label'] ?? $activeBusinessType]
                        : collect($typeKeys)->map(fn ($key) => $business->businessTypeLabel($key))->values()->all(),
                    'sales_count' => (int) $c->sales_count,
                    'gross_sales' => (float) $c->gross_sales,
                    'payments_received' => (float) $c->payments_received,
                    'total_expenses' => (float) $c->total_expenses,
                    'net_amount' => (float) $c->net_amount,
                    'money_short' => (float) ($c->money_short ?? 0),
                    'status' => $c->status,
                    'verifier' => $c->verifier?->name,
                    'submitted_at' => $c->submitted_at?->toIso8601String(),
                    'verified_at' => $c->verified_at?->toIso8601String(),
                ];
            })->values(),
            'filters' => $this->filterMetaPayload($filter),
            'meta' => $this->paginationMeta($closings),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'owner') {
            return $this->forbidden('Only owners can view pending handovers to verify.');
        }

        $businessId = $this->apiBusinessId();

        $this->bootWebBranchContext($request);
        $queue = $this->handover->forBusiness($businessId)->verifyQueueIds($businessId);
        $rank = array_flip($queue);

        $pending = DayClosing::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['submitted', 'disputed'])
            ->with(['user:id,name', 'shift'])
            ->get()
            ->sortBy(fn (DayClosing $c) => [$rank[$c->id] ?? PHP_INT_MAX, $c->id])
            ->values();

        return $this->success([
            'verify_queue' => $queue,
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
        if ($user->role !== 'owner' && ! $user->can('verify_day_closing') && (int) $dayClosing->user_id !== (int) $user->id) {
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

            $posted = $closing->status === 'verified' && $this->postDayIfComplete($request, $closing);

            $message = match (true) {
                $closing->status === 'disputed' => 'Handover marked as disputed.',
                $posted => 'Handover verified and posted to Master Sheet.',
                default => 'Handover verified. The day posts to Master Sheet after the remaining handovers are verified.',
            };

            return $this->success([
                'handover' => $this->handover
                    ->forBusiness($this->apiBusinessId())
                    ->handoverDetail($closing->fresh(), $request->user()),
                'posted_to_master_sheet' => $posted,
            ], $message);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Finalizes the Master Sheet day once every handover for that date is verified
     * and no staff shift on that date is still waiting to hand over.
     */
    private function postDayIfComplete(Request $request, DayClosing $closing): bool
    {
        $businessId = $this->apiBusinessId();
        $date = $closing->closing_date->toDateString();

        $stillPending = DayClosing::where('business_id', $businessId)
            ->whereDate('closing_date', $date)
            ->whereIn('status', ['submitted', 'disputed'])
            ->exists();

        $awaitingShift = Shift::where('business_id', $businessId)
            ->whereDoesntHave('dayClosing')
            ->whereDate('opened_at', '<=', $date)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhereDate('closed_at', '>=', $date))
            ->exists();

        if ($stillPending || $awaitingShift) {
            return false;
        }

        $user = $request->user();
        $branchId = (! $user->seesBusinessWideData() && $user->branch_id)
            ? (int) $user->branch_id
            : $this->tenantContext()->branchId();

        try {
            app(OwnerReportApiService::class)->finalizeDay($user, $businessId, $branchId, $date, []);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
