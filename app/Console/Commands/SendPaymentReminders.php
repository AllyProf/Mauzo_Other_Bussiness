<?php

namespace App\Console\Commands;

use App\Services\PlatformReminderService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'platform:send-payment-reminders';

    protected $description = 'Send expiry and unpaid invoice reminders via email/SMS';

    public function handle(PlatformReminderService $reminders): int
    {
        $expiry = $reminders->sendExpiryReminders();
        $invoices = $reminders->sendUnpaidInvoiceReminders();

        $this->info("Sent {$expiry} expiry reminder(s) and {$invoices} invoice reminder(s).");

        return self::SUCCESS;
    }
}
