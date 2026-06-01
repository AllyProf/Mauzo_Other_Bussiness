<?php

namespace App\Http\Controllers;

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

    protected function scopeStaffToActiveBranch($query)
    {
        return active_branch_service()->scopeUsersInActiveBranch($query);
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
}
