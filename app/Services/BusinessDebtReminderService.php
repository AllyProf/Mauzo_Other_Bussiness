<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BusinessDebtReminderService
{
    public function __construct(
        private BusinessStaffSmsService $staffSms,
        private BusinessStaffMailService $staffMail,
    ) {
    }

    /**
     * @return array{due_soon: int, due_soon_second: int, due_today: int, overdue: int, skipped_time: int}
     */
    public function sendDueReminders(): array
    {
        $counts = [
            'due_soon' => 0,
            'due_soon_second' => 0,
            'due_today' => 0,
            'overdue' => 0,
            'skipped_time' => 0,
        ];
        $today = now()->startOfDay();

        Sale::query()
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->whereNotNull('due_date')
            ->with(['business.plan', 'user', 'customer'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $sales) use (&$counts, $today) {
                foreach ($sales as $sale) {
                    $business = $sale->business;
                    if (! $business) {
                        continue;
                    }

                    $settings = $business->automationSettings();

                    if (! $this->isPastSendTime($settings)) {
                        $counts['skipped_time']++;

                        continue;
                    }

                    $dueDate = Carbon::parse($sale->due_date)->startOfDay();
                    $balance = $this->balanceDue($sale);
                    $sender = $this->resolveSender($business, $sale);
                    $firstOffset = max(1, (int) ($settings['debt_due_reminder_days'] ?? 3));
                    $secondOffset = max(0, (int) ($settings['debt_due_reminder_days_second'] ?? 1));
                    $frequency = ($settings['debt_reminder_frequency'] ?? 'once') === 'twice' ? 'twice' : 'once';
                    $firstReminderDay = $dueDate->copy()->subDays($firstOffset);

                    if (
                        (bool) ($settings['notify_debt_due_soon'] ?? true)
                        && $today->equalTo($firstReminderDay)
                        && $sale->debt_due_soon_sms_sent_at === null
                    ) {
                        if ($this->sendDueSoonReminders($business, $sender, $sale, $balance, $dueDate, 'first')) {
                            $sale->update(['debt_due_soon_sms_sent_at' => now()]);
                            $counts['due_soon']++;
                        }
                    }

                    if (
                        (bool) ($settings['notify_debt_due_soon'] ?? true)
                        && $frequency === 'twice'
                        && $secondOffset > 0
                        && $secondOffset !== $firstOffset
                    ) {
                        $secondReminderDay = $dueDate->copy()->subDays($secondOffset);

                        if (
                            $today->equalTo($secondReminderDay)
                            && $sale->debt_due_soon_second_sms_sent_at === null
                        ) {
                            if ($this->sendDueSoonReminders($business, $sender, $sale, $balance, $dueDate, 'second')) {
                                $sale->update(['debt_due_soon_second_sms_sent_at' => now()]);
                                $counts['due_soon_second']++;
                            }
                        }
                    }

                    if (
                        $dueDate->equalTo($today)
                        && $sale->debt_due_today_sms_sent_at === null
                    ) {
                        if ($this->sendDueTodayReminders($business, $sender, $sale, $balance, $dueDate)) {
                            $sale->update(['debt_due_today_sms_sent_at' => now()]);
                            $counts['due_today']++;
                        }
                    }

                    if (
                        (bool) ($settings['notify_debt_overdue'] ?? true)
                        && $dueDate->lt($today)
                        && $sale->debt_overdue_sms_sent_at === null
                    ) {
                        if ($this->sendOverdueReminders($business, $sender, $sale, $balance, $dueDate)) {
                            $sale->update(['debt_overdue_sms_sent_at' => now()]);
                            $counts['overdue']++;
                        }
                    }
                }
            });

        return $counts;
    }

    public static function clearDebtSmsFlags(Sale $sale): void
    {
        $sale->forceFill([
            'debt_due_soon_sms_sent_at' => null,
            'debt_due_soon_second_sms_sent_at' => null,
            'debt_due_today_sms_sent_at' => null,
            'debt_overdue_sms_sent_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function isPastSendTime(array $settings): bool
    {
        $sendTime = (string) ($settings['debt_reminder_send_time'] ?? '08:00');

        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $sendTime, $matches)) {
            return true;
        }

        $scheduled = now()->copy()->setTime((int) $matches[1], (int) $matches[2], 0);

        return now()->greaterThanOrEqualTo($scheduled);
    }

    private function sendDueSoonReminders(
        Business $business,
        User $sender,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $wave = 'first',
    ): bool {
        $customerSent = $this->staffSms->sendDebtDueSoonToCustomer($business, $sender, $sale, $balance, $dueDate, $wave);
        $staffSent = $this->staffSms->sendDebtDueSoonToStaff($business, $sender, $sale, $balance, $dueDate, $wave);
        $customerEmailSent = $this->staffMail->sendDebtDueSoonToCustomer($business, $sale, $balance, $dueDate, $wave);
        $staffEmailSent = $this->staffMail->sendDebtDueSoonToStaff($business, $sale, $balance, $dueDate, $wave);

        return $customerSent || $staffSent || $customerEmailSent || $staffEmailSent;
    }

    private function sendDueTodayReminders(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        $customerSent = $this->staffSms->sendDebtDueTodayToCustomer($business, $sender, $sale, $balance, $dueDate);
        $staffSent = $this->staffSms->sendDebtDueTodayToStaff($business, $sender, $sale, $balance, $dueDate);
        $customerEmailSent = $this->staffMail->sendDebtDueTodayToCustomer($business, $sale, $balance, $dueDate);
        $staffEmailSent = $this->staffMail->sendDebtDueTodayToStaff($business, $sale, $balance, $dueDate);

        return $customerSent || $staffSent || $customerEmailSent || $staffEmailSent;
    }

    private function sendOverdueReminders(Business $business, User $sender, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        $customerSent = $this->staffSms->sendDebtOverdueToCustomer($business, $sender, $sale, $balance, $dueDate);
        $staffSent = $this->staffSms->sendDebtOverdueToStaff($business, $sender, $sale, $balance, $dueDate);
        $customerEmailSent = $this->staffMail->sendDebtOverdueToCustomer($business, $sale, $balance, $dueDate);
        $staffEmailSent = $this->staffMail->sendDebtOverdueToStaff($business, $sale, $balance, $dueDate);

        return $customerSent || $staffSent || $customerEmailSent || $staffEmailSent;
    }

    private function balanceDue(Sale $sale): float
    {
        return max(0, round((float) $sale->total_amount - (float) $sale->amount_paid, 2));
    }

    private function resolveSender(Business $business, Sale $sale): User
    {
        if ($sale->user) {
            return $sale->user;
        }

        return $business->resolveOwner() ?? new User(['name' => $business->name]);
    }
}
