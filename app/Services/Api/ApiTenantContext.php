<?php

namespace App\Services\Api;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ApiTenantContext
{
  private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 30;

  private ?User $user = null;

  public function setUser(?User $user): void
  {
    $this->user = $user;
  }

  public function user(): ?User
  {
    return $this->user;
  }

  /**
   * @return array{business_id: int|null, branch_id: int|null, branch_all: bool}
   */
  public function get(): array
  {
    if (! $this->user) {
      return ['business_id' => null, 'branch_id' => null, 'branch_all' => true];
    }

    return Cache::get($this->cacheKey(), $this->defaultContext());
  }

  /**
   * @param  array{business_id?: int|null, branch_id?: int|null, branch_all?: bool}  $context
   */
  public function put(array $context): void
  {
    if (! $this->user) {
      return;
    }

    Cache::put($this->cacheKey(), array_merge($this->defaultContext(), $context), self::CACHE_TTL_SECONDS);
  }

  public function clear(): void
  {
    if ($this->user) {
      Cache::forget($this->cacheKey());
    }
  }

  public function businessId(): int
  {
    $user = $this->user;
    if (! $user) {
      return 0;
    }

    if ($user->role === 'owner') {
      $ctx = $this->get();
      $businessId = (int) ($ctx['business_id'] ?? 0);
      if ($businessId > 0 && $this->ownerBusinesses()->contains('id', $businessId)) {
        return $businessId;
      }

      return (int) ($this->ownerBusinesses()->first()?->id ?? $user->business_id);
    }

    return (int) $user->business_id;
  }

  public function business(): ?Business
  {
    $id = $this->businessId();

    return $id > 0 ? Business::find($id) : null;
  }

  public function branchId(): ?int
  {
    $user = $this->user;
    if (! $user) {
      return null;
    }

    if ($user->role !== 'owner') {
      return $user->branch_id ? (int) $user->branch_id : null;
    }

    $ctx = $this->get();
    if (! empty($ctx['branch_all'])) {
      return null;
    }

    $branchId = (int) ($ctx['branch_id'] ?? 0);
    if ($branchId <= 0) {
      return null;
    }

    return $this->ownerBranches()->contains('id', $branchId) ? $branchId : null;
  }

  public function branch(): ?Branch
  {
    $id = $this->branchId();

    return $id ? Branch::find($id) : null;
  }

  public function isViewingAllBranches(): bool
  {
    return $this->user?->role === 'owner' && $this->branchId() === null;
  }

  public function ownerBusinesses(): Collection
  {
    $user = $this->user;
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

  public function ownerBranches(): Collection
  {
    $businessId = $this->businessId();
    if (! $this->user || $this->user->role !== 'owner' || ! $businessId) {
      return collect();
    }

    return Branch::query()
      ->where('is_active', true)
      ->where(function ($query) use ($businessId) {
        $query->whereHas('businesses', fn ($q) => $q->where('businesses.id', $businessId))
          ->orWhere('business_id', $businessId);
      })
      ->orderByDesc('is_default')
      ->orderBy('name')
      ->get();
  }

  public function setActiveBusiness(int $businessId): void
  {
    if ($this->user?->role !== 'owner') {
      abort(403, 'Only owners can switch business.');
    }

    if (! $this->ownerBusinesses()->contains('id', $businessId)) {
      abort(403, 'You do not have access to this business.');
    }

    $this->put([
      'business_id' => $businessId,
      'branch_id' => null,
      'branch_all' => true,
    ]);
  }

  public function setActiveBranch(?int $branchId): void
  {
    if ($this->user?->role !== 'owner') {
      abort(403, 'Only owners can switch branch.');
    }

    if ($branchId === null) {
      $this->put(['branch_id' => null, 'branch_all' => true]);

      return;
    }

    if (! $this->ownerBranches()->contains('id', $branchId)) {
      abort(403, 'Invalid branch for this business.');
    }

    $this->put(['branch_id' => $branchId, 'branch_all' => false]);
  }

  /**
   * @return array{business_id: int|null, branch_id: int|null, branch_all: bool}
   */
  private function defaultContext(): array
  {
    $user = $this->user;
    if (! $user) {
      return ['business_id' => null, 'branch_id' => null, 'branch_all' => true];
    }

    if ($user->role === 'owner') {
      $businessId = $this->ownerBusinesses()->firstWhere('id', $user->business_id)?->id
        ?? $this->ownerBusinesses()->first()?->id
        ?? $user->business_id;

      return [
        'business_id' => $businessId ? (int) $businessId : null,
        'branch_id' => null,
        'branch_all' => true,
      ];
    }

    return [
      'business_id' => $user->business_id ? (int) $user->business_id : null,
      'branch_id' => $user->branch_id ? (int) $user->branch_id : null,
      'branch_all' => false,
    ];
  }

  private function cacheKey(): string
  {
    return 'api_tenant_context_'.$this->user?->id;
  }
}
