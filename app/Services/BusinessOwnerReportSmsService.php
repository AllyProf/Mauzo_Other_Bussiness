<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\OwnerDailyReport;
use App\Models\Receiving;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BusinessOwnerReportSmsService
{
    public function __construct(private BusinessSmsService $businessSms)
    {
    }

    /**
     * @return array{daily: int, weekly: int, branch_compare: int, receiving: int, skipped: int, failed: int}
     */
    public function sendDueReports(): array
    {
        $counts = [
            'daily' => 0,
            'weekly' => 0,
            'branch_compare' => 0,
            'receiving' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->with(['plan', 'ownerUser'])
            ->orderBy('id')
            ->chunkById(50, function ($businesses) use (&$counts) {
                foreach ($businesses as $business) {
                    $settings = $business->automationSettings();
                    $dailyEnabled = (bool) ($settings['sms_daily_report_enabled'] ?? false);
                    $weeklyEnabled = (bool) ($settings['sms_weekly_report_enabled'] ?? false);
                    $branchCompareEnabled = (bool) ($settings['sms_branch_compare_weekly_enabled'] ?? false);
                    $receivingEnabled = (bool) ($settings['sms_receiving_report_daily_enabled'] ?? false);

                    if (! $dailyEnabled && ! $weeklyEnabled && ! $branchCompareEnabled && ! $receivingEnabled) {
                        $counts['skipped']++;
                        continue;
                    }

                    if (! $this->canSendSms($business)) {
                        $counts['skipped']++;
                        continue;
                    }

                    if (! $this->isPastSendTime($settings)) {
                        $counts['skipped']++;
                        continue;
                    }

                    if ($dailyEnabled && $this->shouldSendDaily($settings, 'sms_daily_report_last_sent')) {
                        if ($this->sendDailyReport($business)) {
                            $counts['daily']++;
                        } else {
                            $counts['failed']++;
                        }
                    }

                    if ($receivingEnabled && $this->shouldSendDaily($settings, 'sms_receiving_report_daily_last_sent')) {
                        $result = $this->sendReceivingDailyReport($business);
                        if ($result === 'sent') {
                            $counts['receiving']++;
                        } elseif ($result === 'failed') {
                            $counts['failed']++;
                        }
                    }

                    if ($weeklyEnabled && $this->shouldSendWeekly($settings, 'sms_weekly_report_last_sent')) {
                        if ($this->sendWeeklyReport($business)) {
                            $counts['weekly']++;
                        } else {
                            $counts['failed']++;
                        }
                    }

                    if ($branchCompareEnabled && $this->shouldSendWeekly($settings, 'sms_branch_compare_weekly_last_sent')) {
                        $result = $this->sendBranchCompareWeeklyReport($business);
                        if ($result === 'sent') {
                            $counts['branch_compare']++;
                        } elseif ($result === 'failed') {
                            $counts['failed']++;
                        }
                    }
                }
            });

        return $counts;
    }

    public function sendDailyReport(Business $business, ?string $overridePhone = null): bool
    {
        $date = now()->subDay()->startOfDay();
        $from = $date->toDateString();
        $to = $date->toDateString();
        $stats = $this->salesStats($business->id, $from, $to);

        $message = sprintf(
            '%s Daily Report %s: Sales TZS %s, Collected TZS %s, Profit TZS %s, Orders %s, Outstanding TZS %s. For more information, visit your email.',
            $business->name,
            $date->format('d M Y'),
            number_format($stats['sales'], 0),
            number_format($stats['collected'], 0),
            number_format($stats['profit'], 0),
            number_format($stats['orders'], 0),
            number_format($stats['outstanding'], 0),
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_daily_report', $overridePhone)) {
            return false;
        }

        if ($overridePhone === null) {
            $this->markSent($business, 'sms_daily_report_last_sent', now()->toDateString());
        }

        return true;
    }

    public function sendWeeklyReport(Business $business, ?string $overridePhone = null): bool
    {
        $to = now()->subDay()->startOfDay();
        $from = $to->copy()->subDays(6);
        $stats = $this->salesStats($business->id, $from->toDateString(), $to->toDateString());

        $message = sprintf(
            '%s Weekly Report %s-%s: Sales TZS %s, Collected TZS %s, Profit TZS %s, Orders %s, Outstanding TZS %s. For more information, visit your email.',
            $business->name,
            $from->format('d M'),
            $to->format('d M Y'),
            number_format($stats['sales'], 0),
            number_format($stats['collected'], 0),
            number_format($stats['profit'], 0),
            number_format($stats['orders'], 0),
            number_format($stats['outstanding'], 0),
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_weekly_report', $overridePhone)) {
            return false;
        }

        if ($overridePhone === null) {
            $this->markSent($business, 'sms_weekly_report_last_sent', now()->toDateString());
        }

        return true;
    }

    /**
     * @return 'sent'|'skipped'|'failed'
     */
    public function sendBranchCompareWeeklyReport(Business $business, ?string $overridePhone = null): string
    {
        $branchCount = Branch::query()->where('business_id', $business->id)->count();
        if ($branchCount < 2) {
            if ($overridePhone === null) {
                $this->markSent($business, 'sms_branch_compare_weekly_last_sent', now()->toDateString());
            }

            return 'skipped';
        }

        $to = now()->subDay()->startOfDay();
        $from = $to->copy()->subDays(6);
        $rows = $this->branchSalesBreakdown($business->id, $from->toDateString(), $to->toDateString());

        if ($rows->isEmpty()) {
            if ($overridePhone === null) {
                $this->markSent($business, 'sms_branch_compare_weekly_last_sent', now()->toDateString());
            }

            return 'skipped';
        }

        $parts = $rows->take(5)->map(function (array $row) {
            return $row['name'].' '.$this->formatShortAmount($row['gross']);
        })->all();

        if ($rows->count() > 5) {
            $others = (float) $rows->slice(5)->sum('gross');
            $parts[] = 'Others '.$this->formatShortAmount($others);
        }

        $total = (float) $rows->sum('gross');
        $message = sprintf(
            '%s Branch sales %s-%s: %s. Total TZS %s. For more information, visit your email.',
            $business->name,
            $from->format('d M'),
            $to->format('d M Y'),
            implode(' · ', $parts),
            number_format($total, 0),
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_branch_compare_weekly', $overridePhone)) {
            return 'failed';
        }

        if ($overridePhone === null) {
            $this->markSent($business, 'sms_branch_compare_weekly_last_sent', now()->toDateString());
        }

        return 'sent';
    }

    /**
     * @return 'sent'|'skipped'|'failed'
     */
    public function sendReceivingDailyReport(Business $business, ?string $overridePhone = null): string
    {
        $date = now()->subDay()->startOfDay();
        $summary = $this->receivingSummary($business->id, $date->toDateString(), $date->toDateString());

        if ($summary['receipts'] === 0) {
            if ($overridePhone === null) {
                $this->markSent($business, 'sms_receiving_report_daily_last_sent', now()->toDateString());
            }

            return 'skipped';
        }

        $top = '';
        if ($summary['top_suppliers']->isNotEmpty()) {
            $top = ' Top: '.$summary['top_suppliers']
                ->take(2)
                ->map(fn (array $row) => $row['name'].' '.number_format($row['amount'], 0))
                ->implode(', ').'.';
        }

        $message = sprintf(
            '%s Purchases %s: TZS %s · %s receipts · %s suppliers.%s For more information, visit your email.',
            $business->name,
            $date->format('d M Y'),
            number_format($summary['total_amount'], 0),
            number_format($summary['receipts'], 0),
            number_format($summary['suppliers'], 0),
            $top,
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_receiving_daily', $overridePhone)) {
            return 'failed';
        }

        if ($overridePhone === null) {
            $this->markSent($business, 'sms_receiving_report_daily_last_sent', now()->toDateString());
        }

        return 'sent';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function isPastSendTime(array $settings): bool
    {
        $sendTime = (string) ($settings['sms_report_send_time'] ?? '18:00');

        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $sendTime, $matches)) {
            return true;
        }

        $scheduled = now()->copy()->setTime((int) $matches[1], (int) $matches[2], 0);

        return now()->greaterThanOrEqualTo($scheduled);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendDaily(array $settings, string $lastSentKey): bool
    {
        $lastSent = (string) ($settings[$lastSentKey] ?? '');

        return $lastSent !== now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendWeekly(array $settings, string $lastSentKey): bool
    {
        $weeklyDay = (int) ($settings['sms_weekly_report_day'] ?? 1);

        if ((int) now()->dayOfWeek !== $weeklyDay) {
            return false;
        }

        $lastSent = (string) ($settings[$lastSentKey] ?? '');

        return $lastSent !== now()->toDateString();
    }

    private function canSendSms(Business $business): bool
    {
        $business->loadMissing('plan');

        return $business->plan === null || $business->plan->allowsSmsSending();
    }

    private function formatShortAmount(float $amount): string
    {
        if ($amount >= 1000000) {
            return number_format($amount / 1000000, 1).'M';
        }

        if ($amount >= 1000) {
            return number_format($amount / 1000, 0).'k';
        }

        return number_format($amount, 0);
    }

    /**
     * @return Collection<int, array{name: string, gross: float, orders: int}>
     */
    private function branchSalesBreakdown(int $businessId, string $from, string $to): Collection
    {
        $sales = Sale::query()
            ->where('business_id', $businessId)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->with('user:id,branch_id')
            ->get(['id', 'user_id', 'total_amount']);

        if ($sales->isEmpty()) {
            return collect();
        }

        $branchIds = $sales->pluck('user.branch_id')->filter()->unique()->values();
        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        return $sales->groupBy(fn (Sale $sale) => (int) ($sale->user?->branch_id ?? 0))
            ->map(function (Collection $group, $branchId) use ($branchNames) {
                $branchId = (int) $branchId;

                return [
                    'name' => $branchId > 0
                        ? (string) ($branchNames[$branchId] ?? ('Branch #'.$branchId))
                        : 'HQ / Unassigned',
                    'gross' => (float) $group->sum('total_amount'),
                    'orders' => $group->count(),
                ];
            })
            ->sortByDesc('gross')
            ->values();
    }

    /**
     * @return array{receipts: int, suppliers: int, total_amount: float, top_suppliers: Collection<int, array{name: string, amount: float}>}
     */
    private function receivingSummary(int $businessId, string $from, string $to): array
    {
        $receivings = Receiving::query()
            ->where('business_id', $businessId)
            ->whereBetween('received_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->with('supplier:id,name')
            ->get(['id', 'supplier_id', 'total_amount']);

        $topSuppliers = $receivings
            ->groupBy(fn (Receiving $r) => (int) ($r->supplier_id ?? 0))
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'name' => $first?->supplier?->name ?? 'Unknown supplier',
                    'amount' => (float) $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'receipts' => $receivings->count(),
            'suppliers' => $receivings->pluck('supplier_id')->filter()->unique()->count(),
            'total_amount' => (float) $receivings->sum('total_amount'),
            'top_suppliers' => $topSuppliers,
        ];
    }

    /**
     * @return array{sales: float, collected: float, orders: int, outstanding: float, profit: float}
     */
    private function salesStats(int $businessId, string $from, string $to): array
    {
        $sales = Sale::query()
            ->where('business_id', $businessId)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->get(['id', 'total_amount', 'amount_paid', 'payment_status']);

        $salesTotal = (float) $sales->sum('total_amount');
        $collected = (float) $sales->sum('amount_paid');
        $orders = $sales->count();

        $outstanding = (float) Sale::query()
            ->where('business_id', $businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->get(['total_amount', 'amount_paid'])
            ->sum(fn (Sale $sale) => max(0, (float) $sale->total_amount - (float) $sale->amount_paid));

        $hasDailyProfit = OwnerDailyReport::query()
            ->where('business_id', $businessId)
            ->whereBetween('report_date', [$from, $to])
            ->exists();

        $profit = (float) OwnerDailyReport::query()
            ->where('business_id', $businessId)
            ->whereBetween('report_date', [$from, $to])
            ->sum('net_profit');

        if (! $hasDailyProfit && $orders > 0) {
            $profit = $this->estimateProfitFromSales($sales->pluck('id')->all());
        }

        return [
            'sales' => $salesTotal,
            'collected' => $collected,
            'orders' => $orders,
            'outstanding' => $outstanding,
            'profit' => $profit,
        ];
    }

    /**
     * @param  list<int>  $saleIds
     */
    private function estimateProfitFromSales(array $saleIds): float
    {
        if ($saleIds === []) {
            return 0.0;
        }

        $lines = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->get(['quantity', 'unit_price', 'cost_price', 'subtotal']);

        $revenue = 0.0;
        $cogs = 0.0;
        foreach ($lines as $line) {
            $revenue += (float) ($line->subtotal ?? ((float) $line->unit_price * (float) $line->quantity));
            $cogs += (float) ($line->cost_price ?? 0) * (float) ($line->quantity ?? 0);
        }

        return max(0, $revenue - $cogs);
    }

    private function dispatchToOwner(Business $business, string $message, string $purpose, ?string $overridePhone = null): bool
    {
        $owner = $business->resolveOwner();
        $phone = filled($overridePhone)
            ? $overridePhone
            : (filled($owner?->phone) ? $owner->phone : $business->phone);

        if (! filled($phone)) {
            Log::info('Owner report SMS skipped — no phone', ['business_id' => $business->id]);

            return false;
        }

        if (! $owner) {
            Log::info('Owner report SMS skipped — no owner user', ['business_id' => $business->id]);

            return false;
        }

        $result = $this->businessSms->sendInternalSms(
            $business,
            $owner,
            $phone,
            $message,
            $purpose,
            $owner->name,
            $owner->id,
        );

        return (bool) ($result['success'] ?? false);
    }

    private function markSent(Business $business, string $key, string $date): void
    {
        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                [$key => $date]
            ),
        ]);
    }
}
