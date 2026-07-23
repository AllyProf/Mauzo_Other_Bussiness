<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BusinessOwnerReportSmsService
{
    public function __construct(private BusinessSmsService $businessSms)
    {
    }

    /**
     * @return array{daily: int, weekly: int, skipped: int, failed: int}
     */
    public function sendDueReports(): array
    {
        $counts = [
            'daily' => 0,
            'weekly' => 0,
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

                    if (! $dailyEnabled && ! $weeklyEnabled) {
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

                    if ($dailyEnabled && $this->shouldSendDaily($settings)) {
                        if ($this->sendDailyReport($business)) {
                            $counts['daily']++;
                        } else {
                            $counts['failed']++;
                        }
                    }

                    if ($weeklyEnabled && $this->shouldSendWeekly($settings)) {
                        if ($this->sendWeeklyReport($business)) {
                            $counts['weekly']++;
                        } else {
                            $counts['failed']++;
                        }
                    }
                }
            });

        return $counts;
    }

    public function sendDailyReport(Business $business): bool
    {
        $date = now()->subDay()->startOfDay();
        $from = $date->toDateString();
        $to = $date->toDateString();
        $stats = $this->salesStats($business->id, $from, $to);

        $message = sprintf(
            '%s Daily Report %s: Sales TZS %s, Collected TZS %s, Orders %s, Outstanding TZS %s.',
            $business->name,
            $date->format('d M Y'),
            number_format($stats['sales'], 0),
            number_format($stats['collected'], 0),
            number_format($stats['orders'], 0),
            number_format($stats['outstanding'], 0),
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_daily_report')) {
            return false;
        }

        $this->markSent($business, 'sms_daily_report_last_sent', now()->toDateString());

        return true;
    }

    public function sendWeeklyReport(Business $business): bool
    {
        $to = now()->subDay()->startOfDay();
        $from = $to->copy()->subDays(6);
        $stats = $this->salesStats($business->id, $from->toDateString(), $to->toDateString());

        $message = sprintf(
            '%s Weekly Report %s-%s: Sales TZS %s, Collected TZS %s, Orders %s, Outstanding TZS %s.',
            $business->name,
            $from->format('d M'),
            $to->format('d M Y'),
            number_format($stats['sales'], 0),
            number_format($stats['collected'], 0),
            number_format($stats['orders'], 0),
            number_format($stats['outstanding'], 0),
        );

        if (! $this->dispatchToOwner($business, $message, 'owner_weekly_report')) {
            return false;
        }

        $this->markSent($business, 'sms_weekly_report_last_sent', now()->toDateString());

        return true;
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
    private function shouldSendDaily(array $settings): bool
    {
        $lastSent = (string) ($settings['sms_daily_report_last_sent'] ?? '');

        return $lastSent !== now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendWeekly(array $settings): bool
    {
        $weeklyDay = (int) ($settings['sms_weekly_report_day'] ?? 1);

        if ((int) now()->dayOfWeek !== $weeklyDay) {
            return false;
        }

        $lastSent = (string) ($settings['sms_weekly_report_last_sent'] ?? '');

        return $lastSent !== now()->toDateString();
    }

    private function canSendSms(Business $business): bool
    {
        $business->loadMissing('plan');

        return $business->plan === null || $business->plan->allowsSmsSending();
    }

    /**
     * @return array{sales: float, collected: float, orders: int, outstanding: float}
     */
    private function salesStats(int $businessId, string $from, string $to): array
    {
        $sales = Sale::query()
            ->where('business_id', $businessId)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->get(['total_amount', 'amount_paid', 'payment_status']);

        $salesTotal = (float) $sales->sum('total_amount');
        $collected = (float) $sales->sum('amount_paid');
        $orders = $sales->count();

        $outstanding = (float) Sale::query()
            ->where('business_id', $businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->get(['total_amount', 'amount_paid'])
            ->sum(fn (Sale $sale) => max(0, (float) $sale->total_amount - (float) $sale->amount_paid));

        return [
            'sales' => $salesTotal,
            'collected' => $collected,
            'orders' => $orders,
            'outstanding' => $outstanding,
        ];
    }

    private function dispatchToOwner(Business $business, string $message, string $purpose): bool
    {
        $owner = $business->resolveOwner();
        $phone = filled($owner?->phone) ? $owner->phone : $business->phone;
        $sender = $owner ?? new User(['id' => 0, 'name' => $business->name]);

        if (! filled($phone)) {
            Log::info('Owner report SMS skipped — no phone', ['business_id' => $business->id]);

            return false;
        }

        // sendInternalSms requires a real user id for the log
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
