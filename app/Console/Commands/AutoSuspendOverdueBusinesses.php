<?php

namespace App\Console\Commands;

use App\Services\PlatformReminderService;
use Illuminate\Console\Command;

class AutoSuspendOverdueBusinesses extends Command
{
    protected $signature = 'platform:auto-suspend-overdue';

    protected $description = 'Suspend businesses with expired subscriptions and unpaid invoices';

    public function handle(PlatformReminderService $reminders): int
    {
        $count = $reminders->autoSuspendOverdue();

        $this->info("Suspended {$count} overdue business(es).");

        return self::SUCCESS;
    }
}
