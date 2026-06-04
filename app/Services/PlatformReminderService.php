<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlatformReminderService
{
    public function __construct(
        private PlatformSettingsService $settings,
        private PlatformSmsService $platformSms,
        private PlatformMailService $platformMail,
    ) {
    }

    public function sendExpiryReminders(): int
    {
        $warningDays = max(1, (int) $this->settings->get('expiry_warning_days', 7));
        $sent = 0;

        Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays($warningDays))
            ->whereDate('expiry_date', '>=', now())
            ->with('plan')
            ->each(function (Business $business) use (&$sent) {
                if ($this->sendExpiryReminder($business)) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function sendUnpaidInvoiceReminders(): int
    {
        $reminderDays = max(1, (int) $this->settings->get('payment_reminder_days', 7));
        $sent = 0;

        PlatformBillingInvoice::query()
            ->with(['business.plan'])
            ->whereIn('status', [PlatformBillingInvoice::STATUS_PENDING, PlatformBillingInvoice::STATUS_NOTIFIED])
            ->where('created_at', '<=', now()->subDays($reminderDays))
            ->whereNull('payment_reminder_sent_at')
            ->each(function (PlatformBillingInvoice $invoice) use (&$sent) {
                if ($this->sendInvoiceReminder($invoice)) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function autoSuspendOverdue(): int
    {
        $overdueDays = max(0, (int) $this->settings->get('auto_suspend_unpaid_days', 14));
        $graceDays = max(0, (int) $this->settings->get('grace_period_days', 0));
        $suspended = 0;

        if (! (bool) $this->settings->get('auto_suspend_on_expiry', true)) {
            return 0;
        }

        Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->whereNotNull('expiry_date')
            ->with('plan')
            ->each(function (Business $business) use ($graceDays, $overdueDays, &$suspended) {
                $expiry = Carbon::parse($business->expiry_date)->endOfDay()->addDays($graceDays + $overdueDays);

                if ($expiry->isFuture()) {
                    return;
                }

                $hasUnpaid = PlatformBillingInvoice::query()
                    ->where('business_id', $business->id)
                    ->whereIn('status', [PlatformBillingInvoice::STATUS_PENDING, PlatformBillingInvoice::STATUS_NOTIFIED])
                    ->exists();

                if ($hasUnpaid || Carbon::parse($business->expiry_date)->endOfDay()->addDays($graceDays)->isPast()) {
                    $business->update(['is_active' => false]);
                    $this->platformSms->sendAutoSuspendNotice($business->fresh());
                    $this->platformMail->sendAutoSuspendNotice($business->fresh());
                    $suspended++;
                }
            });

        return $suspended;
    }

    private function sendExpiryReminder(Business $business): bool
    {
        $channels = $this->reminderChannels();
        $sentAny = false;
        $expiryLabel = Carbon::parse($business->expiry_date)->format('d M Y');

        if (in_array('email', $channels, true) && filled($business->email)) {
            $sentAny = $this->platformMail->sendExpiryReminder($business, $expiryLabel) || $sentAny;
        }

        if (in_array('sms', $channels, true) && filled($business->phone)) {
            $sentAny = $this->platformSms->sendExpiryReminder($business, $expiryLabel) || $sentAny;
        }

        return $sentAny;
    }

    private function sendInvoiceReminder(PlatformBillingInvoice $invoice): bool
    {
        $channels = $this->reminderChannels();
        $sentAny = false;
        $business = $invoice->business;
        $amount = number_format((float) $invoice->amount, 0);

        if (in_array('email', $channels, true) && filled($business->email)) {
            $sentAny = $this->platformMail->sendInvoiceReminder($business, $invoice->invoice_number, $amount, $invoice) || $sentAny;
        }

        if (in_array('sms', $channels, true) && filled($business->phone)) {
            $sentAny = $this->platformSms->sendInvoiceReminder($business, $invoice->invoice_number, $amount) || $sentAny;
        }

        if ($sentAny) {
            $invoice->update(['payment_reminder_sent_at' => now()]);
        }

        return $sentAny;
    }

    /**
     * @return list<string>
     */
    private function reminderChannels(): array
    {
        $raw = $this->settings->get('payment_reminder_channels', 'email,sms');
        $parts = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);

        return array_values(array_filter(array_map('strtolower', $parts ?: ['email'])));
    }
}
