<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchApiService
{
    /**
     * @return array<string, mixed>
     */
    public function referenceList(User $user, ApiTenantContext $ctx): array
    {
        if ($user->role === 'owner') {
            return [
                'branches' => $ctx->ownerBranches()->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'is_default' => (bool) $b->is_default,
                ])->values()->all(),
            ];
        }

        if ($user->branch) {
            return [
                'branches' => [[
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'is_default' => (bool) $user->branch->is_default,
                ]],
            ];
        }

        return ['branches' => []];
    }

    /**
     * @return array<string, mixed>
     */
    public function index(User $user, Business $business, ApiTenantContext $ctx): array
    {
        $branches = Branch::query()
            ->with('businesses')
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $maxBranches = $business->maxBranchesAllowed();
        $currentCount = $branches->count();

        return [
            'branches' => $branches->map(fn (Branch $b) => $this->formatBranch($b))->values()->all(),
            'meta' => [
                'current_count' => $currentCount,
                'max_branches' => $maxBranches,
                'branches_limit_label' => $business->branchesLimitLabel(),
                'can_add_branch' => $maxBranches === null || $currentCount < $maxBranches,
                'plan_name' => $business->plan?->name,
                'active_branch_id' => $ctx->branchId(),
            ],
            'assignable_businesses' => $this->ownerBusinesses($user)
                ->map(fn (Business $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(User $user, Business $business, array $payload): array
    {
        $ownerBusinesses = $this->ownerBusinesses($user);
        $allowedBusinessIds = $ownerBusinesses->pluck('id')->all();
        $currentCount = Branch::where('owner_user_id', $user->id)->count();
        $maxBranches = $business->maxBranchesAllowed();

        if ($maxBranches !== null && $currentCount >= $maxBranches) {
            throw ValidationException::withMessages([
                'branch_limit' => "Your {$business->plan?->name} plan allows up to {$maxBranches} branch(es). Upgrade your plan to add more.",
            ]);
        }

        $validated = validator($payload, [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'leader_name' => 'nullable|string|max:255',
            'leader_phone' => 'nullable|string|max:50',
            'leader_email' => 'nullable|email|max:255',
            'business_ids' => 'required|array|min:1',
            'business_ids.*' => ['integer', Rule::in($allowedBusinessIds)],
        ])->validate();

        $isFirst = $currentCount === 0;
        $primaryBusinessId = (int) $validated['business_ids'][0];

        $branch = Branch::create([
            'owner_user_id' => $user->id,
            'business_id' => $primaryBusinessId,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'location' => $validated['location'] ?? null,
            'leader_name' => $validated['leader_name'] ?? null,
            'leader_phone' => $this->normalizePhone($validated['leader_phone'] ?? null),
            'leader_email' => $validated['leader_email'] ?? null,
            'is_active' => true,
            'is_default' => $isFirst,
        ]);

        $sync = [];
        foreach ($validated['business_ids'] as $index => $businessId) {
            $sync[(int) $businessId] = ['is_default' => $index === 0];
        }
        $branch->businesses()->sync($sync);
        $branch->load('businesses');

        return ['branch' => $this->formatBranch($branch)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(User $user, Business $business, Branch $branch, array $payload): array
    {
        $this->ensureBranchAccess($user, $branch, $business);

        $ownerBusinesses = $this->ownerBusinesses($user);
        $allowedBusinessIds = $ownerBusinesses->pluck('id')->all();

        $validated = validator($payload, [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'leader_name' => 'nullable|string|max:255',
            'leader_phone' => 'nullable|string|max:50',
            'leader_email' => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
            'business_ids' => 'required|array|min:1',
            'business_ids.*' => ['integer', Rule::in($allowedBusinessIds)],
        ])->validate();

        $primaryBusinessId = (int) $validated['business_ids'][0];

        $branch->update([
            'business_id' => $primaryBusinessId,
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'location' => $validated['location'] ?? null,
            'leader_name' => $validated['leader_name'] ?? null,
            'leader_phone' => $this->normalizePhone($validated['leader_phone'] ?? null),
            'leader_email' => $validated['leader_email'] ?? null,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $branch->is_active,
        ]);

        $sync = [];
        foreach ($validated['business_ids'] as $index => $businessId) {
            $sync[(int) $businessId] = ['is_default' => $index === 0];
        }
        $branch->businesses()->sync($sync);
        $branch->load('businesses');

        return ['branch' => $this->formatBranch($branch->fresh())];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(User $user, Business $business, Branch $branch): array
    {
        $this->ensureBranchAccess($user, $branch, $business);

        if ($branch->is_default) {
            throw ValidationException::withMessages([
                'branch' => 'The default branch cannot be deleted.',
            ]);
        }

        if ($branch->users()->exists()) {
            throw ValidationException::withMessages([
                'branch' => 'Move or remove employees from this branch before deleting it.',
            ]);
        }

        $deletedId = (int) $branch->id;
        $deletedName = $branch->name;

        $branch->businesses()->detach();
        $branch->delete();

        return [
            'deleted_branch_id' => $deletedId,
            'deleted_branch_name' => $deletedName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatBranch(Branch $branch): array
    {
        $branch->loadMissing('businesses');

        $staffCount = $branch->users()->where('role', 'staff')->count();

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'location' => $branch->location,
            'leader_name' => $branch->leader_name,
            'leader_phone' => $branch->leader_phone,
            'leader_email' => $branch->leader_email,
            'is_active' => (bool) $branch->is_active,
            'is_default' => (bool) $branch->is_default,
            'staff_count' => $staffCount,
            'can_delete' => ! $branch->is_default && $staffCount === 0,
            'businesses' => $branch->businesses->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_primary' => (bool) ($b->pivot->is_default ?? false),
            ])->values()->all(),
            'business_ids' => $branch->businesses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'created_at' => $branch->created_at?->toIso8601String(),
        ];
    }

    private function ensureBranchAccess(User $user, Branch $branch, Business $business): void
    {
        if ((int) $branch->owner_user_id === (int) $user->id) {
            return;
        }

        if ((int) $branch->business_id === (int) $business->id) {
            return;
        }

        throw ValidationException::withMessages([
            'branch' => 'You do not have access to this branch.',
        ]);
    }

    /**
     * @return Collection<int, Business>
     */
    private function ownerBusinesses(User $user): Collection
    {
        if ($user->role !== 'owner') {
            return collect();
        }

        return Business::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id);
                if ($user->business_id) {
                    $query->orWhere('id', $user->business_id);
                }
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        }

        return $digits ? '+255'.$digits : null;
    }
}
