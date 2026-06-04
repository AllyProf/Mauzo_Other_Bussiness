<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActiveBranchService
{
    public const SESSION_KEY = 'active_branch_id';

    public function canSwitch(): bool
    {
        $user = auth()->user();

        return $user
            && $user->role === 'owner'
            && $this->currentBusinessId();
    }

    protected function sessionKey(): string
    {
        return self::SESSION_KEY.'_'.auth()->id();
    }

    protected function currentBusinessId(): ?int
    {
        return active_business_id();
    }

    public function branches()
    {
        if (! $this->canSwitch()) {
            return collect();
        }

        $businessId = $this->currentBusinessId();

        if (! $businessId) {
            return collect();
        }

        return Branch::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($businessId) {
                $query->whereHas('businesses', fn (Builder $businessQuery) => $businessQuery->where('businesses.id', $businessId))
                    ->orWhere('business_id', $businessId);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function activeBranchId(): ?int
    {
        if (! $this->canSwitch()) {
            return auth()->user()?->branch_id;
        }

        if (! session()->has($this->sessionKey())) {
            $defaultId = Branch::query()
                ->where('is_active', true)
                ->where(function (Builder $query) {
                    $businessId = $this->currentBusinessId();
                    $query->whereHas('businesses', fn (Builder $businessQuery) => $businessQuery->where('businesses.id', $businessId))
                        ->orWhere('business_id', $businessId);
                })
                ->where('is_default', true)
                ->value('id');

            session()->put($this->sessionKey(), $defaultId ?? 'all');
        }

        $value = session($this->sessionKey());

        if ($value === 'all' || $value === null || $value === '') {
            return null;
        }

        $branchId = (int) $value;
        $businessId = $this->currentBusinessId();

        $isValid = Branch::query()
            ->where('id', $branchId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($businessId) {
                $query->whereHas('businesses', fn (Builder $businessQuery) => $businessQuery->where('businesses.id', $businessId))
                    ->orWhere('business_id', $businessId);
            })
            ->exists();

        if (! $isValid) {
            session()->put($this->sessionKey(), 'all');

            return null;
        }

        return $branchId;
    }

    public function activeBranch(): ?Branch
    {
        $branchId = $this->activeBranchId();

        if (! $branchId) {
            return null;
        }

        return Branch::find($branchId);
    }

    public function activeBranchLabel(): string
    {
        if (! $this->canSwitch()) {
            return $this->activeBranch()?->name ?? 'Branch';
        }

        return $this->activeBranch()?->name ?? 'All Branches';
    }

    public function isViewingAllBranches(): bool
    {
        return $this->canSwitch() && $this->activeBranchId() === null;
    }

    public function setActiveBranch(?int $branchId): void
    {
        if (! $this->canSwitch()) {
            return;
        }

        if ($branchId === null) {
            session()->put($this->sessionKey(), 'all');
        } else {
            $businessId = $this->currentBusinessId();

            Branch::query()
                ->where('id', $branchId)
                ->where('is_active', true)
                ->where(function (Builder $query) use ($businessId) {
                    $query->whereHas('businesses', fn (Builder $businessQuery) => $businessQuery->where('businesses.id', $businessId))
                        ->orWhere('business_id', $businessId);
                })
                ->firstOrFail();

            session()->put($this->sessionKey(), $branchId);
        }

        session()->save();
    }

    public function scopeUsersInActiveBranch(Builder $query): Builder
    {
        $branchId = $this->activeBranchId();

        if (! $branchId || ! $this->canSwitch()) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function scopeRecordsByBranchUsers(Builder $query, string $userIdColumn = 'user_id'): Builder
    {
        $branchId = $this->activeBranchId();
        $businessId = $this->currentBusinessId() ?? auth()->user()?->business_id;

        $usersQuery = User::query()->where('business_id', $businessId);

        if ($branchId && $this->canSwitch()) {
            $usersQuery->where('branch_id', $branchId);
        }

        return $query->whereIn($userIdColumn, $usersQuery->select('id'));
    }

    public function branchUserIds(): ?array
    {
        $branchId = $this->activeBranchId();

        if (! $branchId || ! $this->canSwitch()) {
            return null;
        }

        $businessId = $this->currentBusinessId() ?? auth()->user()?->business_id;

        return User::query()
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->all();
    }
}
