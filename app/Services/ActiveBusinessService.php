<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

class ActiveBusinessService
{
    public const SESSION_KEY = 'active_business_id';

    public function canSwitch(): bool
    {
        $user = auth()->user();

        return $user
            && $user->role === 'owner'
            && $this->businesses()->count() > 1;
    }

    protected function sessionKey(): string
    {
        return self::SESSION_KEY.'_'.auth()->id();
    }

    public function businesses(): Collection
    {
        $user = auth()->user();

        if (! $user || $user->role !== 'owner') {
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

    public function activeBusinessId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->role !== 'owner') {
            return $user->business_id;
        }

        $businesses = $this->businesses();

        if ($businesses->isEmpty()) {
            return $user->business_id;
        }

        if (! session()->has($this->sessionKey())) {
            $defaultId = $businesses->firstWhere('id', $user->business_id)?->id
                ?? $businesses->first()?->id;

            session()->put($this->sessionKey(), $defaultId);

            return $defaultId;
        }

        $value = (int) session($this->sessionKey());

        if ($businesses->contains('id', $value)) {
            return $value;
        }

        $fallback = $businesses->firstWhere('id', $user->business_id)?->id
            ?? $businesses->first()?->id;

        session()->put($this->sessionKey(), $fallback);

        return $fallback;
    }

    public function activeBusiness(): ?Business
    {
        $businessId = $this->activeBusinessId();

        if (! $businessId) {
            return null;
        }

        return Business::find($businessId);
    }

    public function activeBusinessLabel(): string
    {
        return $this->activeBusiness()?->name ?? 'Business';
    }

    public function setActiveBusiness(int $businessId): void
    {
        $user = auth()->user();

        if (! $user || $user->role !== 'owner') {
            return;
        }

        $business = $this->businesses()->firstWhere('id', $businessId);

        if (! $business) {
            abort(403, 'You do not have access to this business.');
        }

        session()->put($this->sessionKey(), $business->id);
        session()->save();

        active_branch_service()->setActiveBranch(null);
    }

    public function ensureOwnerCanAccessBusiness(int $businessId): void
    {
        if (! $this->businesses()->contains('id', $businessId)) {
            abort(403, 'You do not have access to this business.');
        }
    }
}
