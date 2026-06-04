<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function authorizeAny(array $abilities): void
    {
        foreach ($abilities as $ability) {
            if (auth()->user()?->can($ability)) {
                return;
            }
        }

        abort(403);
    }

    protected function actsAsBusinessWideViewer(): bool
    {
        return (bool) auth()->user()?->seesBusinessWideData();
    }

    protected function scopeToCurrentStaff($query, string $column = 'user_id')
    {
        if ($this->actsAsBusinessWideViewer()) {
            return $this->scopeToActiveBranchUsers($query, $column);
        }

        return $query->where($column, auth()->id());
    }

    protected function scopeToActiveBranchUsers($query, string $column = 'user_id')
    {
        return active_branch_service()->scopeRecordsByBranchUsers($query, $column);
    }

    protected function scopeDayClosingsForActiveBranch($query)
    {
        if ($this->actsAsBusinessWideViewer() && auth()->user()?->role === 'owner') {
            return $query->where(function ($scoped) {
                $this->scopeToActiveBranchUsers($scoped);
                $scoped->orWhere(function ($ownerQuery) {
                    $ownerQuery->where('user_id', auth()->id())->whereNull('shift_id');
                });
            });
        }

        return $this->scopeToActiveBranchUsers($query);
    }

    protected function scopeStockLossesForActiveBranch($query)
    {
        if ($this->actsAsBusinessWideViewer() && auth()->user()?->role === 'owner') {
            return $query->where(function ($scoped) {
                $this->scopeToActiveBranchUsers($scoped);
                $scoped->orWhere('user_id', auth()->id());
            });
        }

        return $this->scopeToActiveBranchUsers($query);
    }

    protected function scopeStaffToActiveBranch($query)
    {
        return active_branch_service()->scopeUsersInActiveBranch($query);
    }

    protected function currentBusinessId(): int
    {
        return current_business_id();
    }

    protected function currentBusiness(): ?\App\Models\Business
    {
        return active_business() ?? auth()->user()?->business;
    }

    protected function ensureCanAccessStaffRecord(int $recordUserId): void
    {
        if (! $this->actsAsBusinessWideViewer() && $recordUserId !== auth()->id()) {
            abort(403, 'You can only access your own records.');
        }
    }

    protected function redirectIfShiftOverdue(?\App\Models\Shift $openShift): ?\Illuminate\Http\RedirectResponse
    {
        if (! $openShift || ! auth()->user()?->requiresOpenShift()) {
            return null;
        }

        $business = auth()->user()->business;
        $policy = app(\App\Services\ShiftPolicyService::class);

        if (! $policy->mustBlockShiftActivity($openShift, $business)) {
            return null;
        }

        $status = $policy->shiftOverdueStatus($openShift, $business);

        return redirect()->route('day-closing.index', ['shift' => $openShift->id])
            ->with('error', $status['message']);
    }

    protected function resolveBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer() && auth()->user()?->branch_id) {
            return (int) auth()->user()->branch_id;
        }

        $branchId = active_branch_id();

        return $branchId ? (int) $branchId : null;
    }

    protected function branchBusinessFilterContext(?Request $request = null): array
    {
        $business = $this->currentBusiness() ?? auth()->user()?->business;
        $branchFilterId = $this->resolveBranchFilterId();
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            $businessTypes = collect($business->branchPosBusinessTypesMeta($branchFilterId))
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
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        $typeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();
        $activeBusinessType = $request?->get('business_type');
        if ($activeBusinessType && ! in_array($activeBusinessType, $typeKeys, true)) {
            $activeBusinessType = null;
        }

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? auth()->user()?->branch?->name ?? 'Branch')
            : null;

        return [
            'business' => $business,
            'branchFilterId' => $branchFilterId,
            'activeBranchName' => $activeBranchName,
            'viewingAllBranches' => $this->actsAsBusinessWideViewer() && ! $branchFilterId,
            'businessTypes' => $businessTypes,
            'multiBusiness' => count($businessTypes) > 1,
            'activeBusinessType' => $activeBusinessType ?: null,
            'activeBusinessLabel' => $activeBusinessType
                ? (collect($businessTypes)->firstWhere('key', $activeBusinessType)['label'] ?? $business->businessTypeLabel($activeBusinessType))
                : null,
        ];
    }

    protected function scopeSalesToBranchCategories($query): void
    {
        if ($branchFilterId = $this->resolveBranchFilterId()) {
            $query->whereHas('items.item.category', fn ($categoryQuery) => $categoryQuery->where('branch_id', $branchFilterId));
        }
    }

    protected function scopeItemsToBranch($query): void
    {
        if ($branchFilterId = $this->resolveBranchFilterId()) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('branch_id', $branchFilterId));
        }
    }

    protected function scopeItemsToBusinessType($query, ?string $businessTypeKey): void
    {
        if ($businessTypeKey) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('source_business_type_key', $businessTypeKey));
        }
    }

    protected function scopeItemsForStaffShift($query, ?\App\Models\User $user = null): void
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return;
        }

        if ($user->branch_id) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('branch_id', (int) $user->branch_id));
        }

        if ($user->branch_id) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('branch_id', (int) $user->branch_id));
        }

        $businessTypeKeys = $user->assignedBusinessTypeKeys();
        if ($businessTypeKeys !== []) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereIn('source_business_type_key', $businessTypeKeys));
        }
    }

    protected function staffShiftScopeLabels(?\App\Models\User $user = null): array
    {
        $user = $user ?? auth()->user();

        return [
            'assignedBranchName' => $user?->branch?->name,
            'assignedBusinessLabel' => $user?->displayBusinessTypeLabels(),
        ];
    }
}
