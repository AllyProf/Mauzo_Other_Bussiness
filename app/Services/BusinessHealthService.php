<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\CustomerSmsLog;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessHealthService
{
    public function smsUsageForBusiness(Business $business, ?Carbon $month = null): array
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $plan = $business->plan;
        $limit = (int) ($plan?->max_sms_per_month ?? 0);
        $used = (int) CustomerSmsLog::query()
            ->where('business_id', $business->id)
            ->where('status', 'sent')
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count();

        $percent = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $percent,
            'status' => $this->usageStatus($percent, $limit),
        ];
    }

    public function storageUsageForBusiness(Business $business): array
    {
        $plan = $business->plan;
        $limitMb = (int) ($plan?->max_storage_mb ?? 0);
        $usedBytes = $this->estimateStorageBytes($business);
        $usedMb = round($usedBytes / 1024 / 1024, 2);
        $percent = $limitMb > 0 ? min(100, round(($usedMb / $limitMb) * 100, 1)) : 0;

        return [
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'percent' => $percent,
            'status' => $this->usageStatus($percent, $limitMb),
        ];
    }

    public function usageSnapshot(Business $business): array
    {
        $business->loadMissing('plan', 'ownerUser');

        $lastLogin = AuditLog::query()
            ->where('business_id', $business->id)
            ->where('action', 'LOGIN')
            ->latest('created_at')
            ->value('created_at');

        $staffCount = User::query()
            ->where('business_id', $business->id)
            ->where('role', '!=', 'owner')
            ->count();

        $sales30 = (int) Sale::query()
            ->where('business_id', $business->id)
            ->where('payment_status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $itemsCount = (int) Item::query()->where('business_id', $business->id)->count();

        $sms = $this->smsUsageForBusiness($business);
        $storage = $this->storageUsageForBusiness($business);

        $health = $this->healthBadge($business, $lastLogin, $sales30, $sms, $storage);

        return [
            'business' => $business,
            'last_login_at' => $lastLogin,
            'staff_count' => $staffCount,
            'sales_30_days' => $sales30,
            'items_count' => $itemsCount,
            'sms' => $sms,
            'storage' => $storage,
            'health' => $health,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function allBusinessSnapshots(): Collection
    {
        return Business::query()
            ->with('plan')
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => $this->usageSnapshot($business));
    }

    /**
     * @return array<string, mixed>
     */
    public function regionalSummary(): array
    {
        $rows = Business::query()
            ->select('region', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active'))
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->groupBy('region')
            ->orderByDesc('total')
            ->get();

        return [
            'regions' => $rows,
            'total_businesses' => (int) Business::count(),
            'with_region' => (int) Business::whereNotNull('region')->where('region', '!=', '')->count(),
        ];
    }

    private function estimateStorageBytes(Business $business): int
    {
        $paths = [
            storage_path('app/businesses/'.$business->id),
            storage_path('app/public/businesses/'.$business->id),
            public_path('uploads/businesses/'.$business->id),
        ];

        $total = 0;

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $total += $this->directorySize($path);
            }
        }

        if ($total === 0) {
            $total = ((int) Item::where('business_id', $business->id)->count()) * 2048;
        }

        return $total;
    }

    private function directorySize(string $path): int
    {
        $size = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function usageStatus(float $percent, int $limit): string
    {
        if ($limit <= 0) {
            return 'unlimited';
        }

        if ($percent >= 95) {
            return 'critical';
        }

        if ($percent >= 80) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * @param  array<string, mixed>  $sms
     * @param  array<string, mixed>  $storage
     * @return array{label: string, class: string}
     */
    private function healthBadge(Business $business, mixed $lastLogin, int $sales30, array $sms, array $storage): array
    {
        if (! $business->is_active) {
            return ['label' => 'Suspended', 'class' => 'danger'];
        }

        if ($business->pending_approval) {
            return ['label' => 'Pending', 'class' => 'warning'];
        }

        if ($sms['status'] === 'critical' || $storage['status'] === 'critical') {
            return ['label' => 'At Limit', 'class' => 'danger'];
        }

        if (! $lastLogin) {
            return ['label' => 'Never Logged In', 'class' => 'secondary'];
        }

        $daysSinceLogin = Carbon::parse($lastLogin)->diffInDays(now());

        if ($daysSinceLogin > 30) {
            return ['label' => 'Inactive', 'class' => 'warning'];
        }

        if ($sales30 === 0) {
            return ['label' => 'No Sales', 'class' => 'warning'];
        }

        return ['label' => 'Healthy', 'class' => 'success'];
    }
}
