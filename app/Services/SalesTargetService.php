<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesTargetService
{
    public function __construct(private ActiveBranchService $branchService)
    {
    }

    public function periodBounds(string $periodType, Carbon $anchor): array
    {
        return match ($periodType) {
            'daily' => [
                $anchor->copy()->startOfDay(),
                $anchor->copy()->endOfDay(),
            ],
            'weekly' => [
                $anchor->copy()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $anchor->copy()->startOfMonth(),
                $anchor->copy()->endOfMonth(),
            ],
        };
    }

    public function saveTarget(Business $business, array $data, int $createdBy): SalesTarget
    {
        $periodType = $data['period_type'];
        $anchor = Carbon::parse($data['period_date']);
        [$start, $end] = $this->periodBounds($periodType, $anchor);

        return SalesTarget::updateOrCreate(
            [
                'business_id' => $business->id,
                'period_type' => $periodType,
                'period_start' => $start->toDateString(),
                'branch_id' => $data['branch_id'] ?? null,
                'business_type_key' => $data['business_type_key'] ?? null,
                'user_id' => $data['user_id'] ?? null,
            ],
            [
                'period_end' => $end->toDateString(),
                'target_amount' => (float) $data['target_amount'],
                'created_by' => $createdBy,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    public function updateTarget(SalesTarget $target, Business $business, array $data): SalesTarget
    {
        $periodType = $data['period_type'];
        $anchor = Carbon::parse($data['period_date']);
        [$start, $end] = $this->periodBounds($periodType, $anchor);

        $duplicate = SalesTarget::query()
            ->where('business_id', $business->id)
            ->where('id', '!=', $target->id)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $start->toDateString())
            ->where('branch_id', $data['branch_id'] ?? null)
            ->where('business_type_key', $data['business_type_key'] ?? null)
            ->where('user_id', $data['user_id'] ?? null)
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'period_date' => 'A target with the same period and scope already exists.',
            ]);
        }

        $target->update([
            'period_type' => $periodType,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'target_amount' => (float) $data['target_amount'],
            'branch_id' => $data['branch_id'] ?? null,
            'business_type_key' => $data['business_type_key'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $target->fresh();
    }

    public function activeTargets(Business $business, ?int $branchId = null): Collection
    {
        $today = now()->toDateString();

        $query = SalesTarget::query()
            ->where('business_id', $business->id)
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->with(['branch', 'user'])
            ->orderByRaw("FIELD(period_type, 'monthly', 'weekly', 'daily')")
            ->orderByDesc('target_amount');

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $query->get();
    }

    public function dashboardProgress(Business $business): Collection
    {
        $branchId = $this->branchService->canSwitch()
            ? $this->branchService->activeBranchId()
            : auth()->user()?->branch_id;

        return $this->mapTargetProgress(
            $this->activeTargets($business, $branchId),
            $business,
            true
        );
    }

    /**
     * Active targets assigned to a specific staff member.
     *
     * @return Collection<int, array{target: SalesTarget, actual: float, progress: int, title: string, period_label: string, scope_label: string, period_type: string}>
     */
    public function staffTargetProgress(User $user, Business $business): Collection
    {
        $today = now()->toDateString();

        $targets = SalesTarget::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->whereDate('period_start', '<=', $today)
            ->whereDate('period_end', '>=', $today)
            ->with(['branch'])
            ->orderByRaw("FIELD(period_type, 'monthly', 'weekly', 'daily')")
            ->orderByDesc('target_amount')
            ->get();

        return $this->mapTargetProgress($targets, $business, false);
    }

    /**
     * @param  Collection<int, SalesTarget>  $targets
     */
    private function mapTargetProgress(Collection $targets, Business $business, bool $ownerView): Collection
    {
        return $targets->map(function (SalesTarget $target) use ($business, $ownerView) {
            $actual = $this->actualRevenue($target);
            $targetAmount = max(0.01, (float) $target->target_amount);

            return [
                'target' => $target,
                'actual' => $actual,
                'progress' => (int) min(100, round(($actual / $targetAmount) * 100)),
                'title' => $target->displayTitle($business),
                'period_label' => $target->periodLabel(),
                'scope_label' => $ownerView
                    ? $this->scopeSummary($target, $business)
                    : $this->staffScopeSummary($target, $business),
                'period_type' => $target->period_type,
            ];
        })->values();
    }

    public function staffScopeSummary(SalesTarget $target, Business $business): string
    {
        $parts = [];

        if ($target->business_type_key) {
            $parts[] = $business->businessTypeLabel($target->business_type_key);
        }

        if ($target->branch) {
            $parts[] = $target->branch->name;
        }

        if ($target->notes) {
            $parts[] = $target->notes;
        }

        return $parts ? implode(' · ', $parts) : 'Personal sales goal';
    }

    public function actualRevenue(SalesTarget $target): float
    {
        $from = $target->period_start->toDateString();
        $to = $target->period_end->toDateString();

        if ($target->business_type_key) {
            $query = SaleItem::query()
                ->whereHas('sale', function ($q) use ($target, $from, $to) {
                    $q->where('business_id', $target->business_id)
                        ->whereBetween('sale_date', [$from, $to])
                        ->where('payment_status', '!=', 'cancelled');
                    if ($target->user_id) {
                        $q->where('user_id', $target->user_id);
                    } else {
                        $this->applyBranchScope($q, $target->branch_id);
                    }
                })
                ->whereHas('item.category', function ($q) use ($target) {
                    if ($target->business_type_key === 'other') {
                        $q->where(function ($q) {
                            $q->whereNull('source_business_type_key')
                                ->orWhere('source_business_type_key', 'other')
                                ->orWhere('source_business_type_key', '');
                        });
                    } else {
                        $q->where('source_business_type_key', $target->business_type_key);
                    }
                });

            return (float) $query->sum('subtotal');
        }

        $query = Sale::query()
            ->where('business_id', $target->business_id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled');

        if ($target->user_id) {
            $query->where('user_id', $target->user_id);
        } else {
            $this->applyBranchScope($query, $target->branch_id);
        }

        return (float) $query->sum('amount_paid');
    }

    public function scopeSummary(SalesTarget $target, Business $business): string
    {
        $parts = [];

        if ($target->branch) {
            $parts[] = $target->branch->name;
        } else {
            $parts[] = 'All branches';
        }

        if ($target->business_type_key) {
            $parts[] = $business->businessTypeLabel($target->business_type_key);
        } else {
            $parts[] = 'All departments';
        }

        if ($target->user) {
            $parts[] = $target->user->name;
        } else {
            $parts[] = 'All staff';
        }

        return implode(' · ', $parts);
    }

    private function applyBranchScope($query, ?int $branchId): void
    {
        if (! $branchId) {
            return;
        }

        $userIds = User::query()
            ->where('branch_id', $branchId)
            ->pluck('id');

        $query->whereIn('user_id', $userIds);
    }
}
