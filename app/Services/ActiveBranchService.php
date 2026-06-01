<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActiveBranchService
{
    public const SESSION_KEY = 'active_branch_id';

    public function canSwitch(): bool
    {
        $user = auth()->user();

        return $user
            && $user->role === 'owner'
            && $user->business_id;
    }

    protected function sessionKey(): string
    {
        return self::SESSION_KEY.'_'.auth()->id();
    }

    public function branches(): Collection
    {
        if (! $this->canSwitch()) {
            return collect();
        }

        return Branch::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('is_active', true)
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
                ->where('business_id', auth()->user()->business_id)
                ->where('is_default', true)
                ->value('id');

            session()->put($this->sessionKey(), $defaultId ?? 'all');
        }

        $value = session($this->sessionKey());

        if ($value === 'all' || $value === null || $value === '') {
            return null;
        }

        $branchId = (int) $value;

        $isValid = Branch::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('id', $branchId)
            ->where('is_active', true)
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

        return Branch::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('id', $branchId)
            ->where('is_active', true)
            ->first();
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
            Branch::query()
                ->where('business_id', auth()->user()->business_id)
                ->where('id', $branchId)
                ->where('is_active', true)
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

        if (! $branchId || ! $this->canSwitch()) {
            return $query;
        }

        return $query->whereIn($userIdColumn, User::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('branch_id', $branchId)
            ->select('id'));
    }

    public function branchUserIds(): ?array
    {
        $branchId = $this->activeBranchId();

        if (! $branchId || ! $this->canSwitch()) {
            return null;
        }

        return User::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->all();
    }
}
