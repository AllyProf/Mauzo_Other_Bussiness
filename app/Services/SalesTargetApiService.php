<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesTargetApiService
{
    public function __construct(private SalesTargetService $targets)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(
        User $user,
        Business $business,
        ?int $branchFilterId,
        ?string $businessTypeKey = null,
        int $page = 1
    ): array {
        $this->assertFeatureAvailable($business);

        $branches = Branch::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $staffQuery = User::where('business_id', $business->id)
            ->where('role', '!=', 'owner')
            ->where('is_active', true)
            ->orderBy('name');

        if (! $user->seesBusinessWideData() && $user->branch_id) {
            $staffQuery->where('branch_id', (int) $user->branch_id);
        } elseif ($branchFilterId) {
            $staffQuery->where('branch_id', $branchFilterId);
        }

        $staff = $staffQuery->get(['id', 'name', 'branch_id']);

        $businessTypes = $this->businessTypesMeta($business, $branchFilterId);

        $targetsQuery = SalesTarget::where('business_id', $business->id)
            ->with(['branch', 'user', 'creator'])
            ->orderByDesc('period_start')
            ->orderBy('period_type');

        if ($branchFilterId) {
            $targetsQuery->where(function ($query) use ($branchFilterId) {
                $query->where('branch_id', $branchFilterId)->orWhereNull('branch_id');
            });
        }

        if ($businessTypeKey) {
            $targetsQuery->where(function ($query) use ($businessTypeKey) {
                $query->where('business_type_key', $businessTypeKey)->orWhereNull('business_type_key');
            });
        }

        $targets = $targetsQuery->paginate(20, ['*'], 'page', max(1, $page));

        $rows = collect($targets->items())->map(function (SalesTarget $target) use ($business) {
            return $this->formatTargetRow($target, $business);
        })->values()->all();

        return [
            'targets' => $rows,
            'pagination' => [
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
                'per_page' => $targets->perPage(),
                'total' => $targets->total(),
            ],
            'form' => [
                'period_types' => [
                    ['key' => 'daily', 'label' => 'Daily'],
                    ['key' => 'weekly', 'label' => 'Weekly'],
                    ['key' => 'monthly', 'label' => 'Monthly'],
                ],
                'branches' => $branches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                ])->values()->all(),
                'business_types' => array_merge(
                    $businessTypes,
                    [['key' => 'other', 'label' => 'Other', 'icon' => 'fa-ellipsis-h']]
                ),
                'staff' => $staff->map(fn (User $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'branch_id' => $s->branch_id ? (int) $s->branch_id : null,
                ])->values()->all(),
            ],
            'filters' => [
                'branch_id' => $branchFilterId,
                'business_type' => $businessTypeKey,
                'branch_name' => $branchFilterId
                    ? (Branch::find($branchFilterId)?->name ?? 'Branch')
                    : null,
                'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
            ],
            'meta' => [
                'plan_feature' => 'sales_targets',
                'available' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Business $business, SalesTarget $target): array
    {
        $this->assertFeatureAvailable($business);
        $this->assertOwnsTarget($business, $target);

        $target->loadMissing(['branch', 'user', 'creator']);

        return [
            'target' => $this->formatTargetRow($target, $business),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(Business $business, User $actor, array $payload): array
    {
        $this->assertFeatureAvailable($business);
        $validated = $this->validatePayload($payload, $business);
        $data = $this->buildPayload($validated, $business);

        $target = $this->targets->saveTarget($business, $data, (int) $actor->id);
        $target->loadMissing(['branch', 'user', 'creator']);

        return [
            'target' => $this->formatTargetRow($target, $business),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(Business $business, SalesTarget $target, array $payload): array
    {
        $this->assertFeatureAvailable($business);
        $this->assertOwnsTarget($business, $target);

        $validated = $this->validatePayload($payload, $business);
        $data = $this->buildPayload($validated, $business);

        $target = $this->targets->updateTarget($target, $business, $data);
        $target->loadMissing(['branch', 'user', 'creator']);

        return [
            'target' => $this->formatTargetRow($target, $business),
        ];
    }

    /**
     * @return array{deleted: true, id: int}
     */
    public function destroy(Business $business, SalesTarget $target): array
    {
        $this->assertFeatureAvailable($business);
        $this->assertOwnsTarget($business, $target);

        $id = (int) $target->id;
        $target->delete();

        return [
            'deleted' => true,
            'id' => $id,
        ];
    }

    public function branchFilterId(User $user, ApiTenantContext $ctx, ?int $requestedBranchId = null): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if ($requestedBranchId && $requestedBranchId > 0) {
            if ($ctx->ownerBranches()->contains('id', $requestedBranchId)) {
                return $requestedBranchId;
            }
        }

        return $ctx->branchId();
    }

    private function assertFeatureAvailable(Business $business): void
    {
        if (! $business->hasPlanFeature('sales_targets')) {
            throw ValidationException::withMessages([
                'plan' => ['Sales targets are not available on your current plan.'],
            ]);
        }
    }

    private function assertOwnsTarget(Business $business, SalesTarget $target): void
    {
        if ((int) $target->business_id !== (int) $business->id) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload, Business $business): array
    {
        $allowedTypes = collect($business->posBusinessTypesMeta())->pluck('key')->push('other')->unique()->all();

        return validator($payload, [
            'period_type' => 'required|in:daily,weekly,monthly',
            'period_date' => 'required|date',
            'target_amount' => 'required|numeric|min:1',
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $business->id),
            ],
            'business_type_key' => ['nullable', 'string', Rule::in($allowedTypes)],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('business_id', $business->id),
            ],
            'notes' => 'nullable|string|max:255',
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPayload(array $validated, Business $business): array
    {
        $staffUser = ! empty($validated['user_id'])
            ? User::where('business_id', $business->id)->find((int) $validated['user_id'])
            : null;

        $branchId = ! empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        if (! $branchId && $staffUser?->branch_id) {
            $branchId = (int) $staffUser->branch_id;
        }

        return [
            'period_type' => $validated['period_type'],
            'period_date' => $validated['period_date'],
            'target_amount' => $validated['target_amount'],
            'branch_id' => $branchId,
            'business_type_key' => $validated['business_type_key'] ?? null,
            'user_id' => $staffUser?->id,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTargetRow(SalesTarget $target, Business $business): array
    {
        $actual = $this->targets->actualRevenue($target);
        $targetAmount = max(0.01, (float) $target->target_amount);
        $progress = (int) min(100, round(($actual / $targetAmount) * 100));

        return [
            'id' => $target->id,
            'period_type' => $target->period_type,
            'period_type_label' => ucfirst($target->period_type),
            'period_start' => $target->period_start?->toDateString(),
            'period_end' => $target->period_end?->toDateString(),
            'period_label' => $target->periodLabel(),
            'period_date' => $target->period_start?->toDateString(),
            'target_amount' => (float) $target->target_amount,
            'actual_amount' => round($actual, 2),
            'progress' => $progress,
            'remaining_amount' => max(0, round((float) $target->target_amount - $actual, 2)),
            'title' => $target->displayTitle($business),
            'scope_label' => $this->targets->scopeSummary($target, $business),
            'branch_id' => $target->branch_id ? (int) $target->branch_id : null,
            'branch_name' => $target->branch?->name,
            'business_type_key' => $target->business_type_key,
            'business_type_label' => $target->business_type_key
                ? $business->businessTypeLabel($target->business_type_key)
                : null,
            'user_id' => $target->user_id ? (int) $target->user_id : null,
            'user_name' => $target->user?->name,
            'notes' => $target->notes,
            'created_by' => $target->creator ? [
                'id' => $target->creator->id,
                'name' => $target->creator->name,
            ] : null,
            'created_at' => $target->created_at?->toIso8601String(),
            'updated_at' => $target->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, icon: string}>
     */
    private function businessTypesMeta(Business $business, ?int $branchFilterId): array
    {
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            return collect($business->branchPosBusinessTypesMeta($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $type['icon'] ?? ($templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store')),
                    ];
                })
                ->values()
                ->all();
        }

        return collect($business->posBusinessTypesMeta())
            ->map(fn ($type) => [
                'key' => (string) ($type['key'] ?? ''),
                'label' => (string) ($type['label'] ?? $type['key'] ?? ''),
                'icon' => $type['icon'] ?? 'fa-store',
            ])
            ->values()
            ->all();
    }
}
