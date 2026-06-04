<?php

namespace App\Console\Commands;

use App\Services\BusinessDebtReminderService;
use Illuminate\Console\Command;

class SendBusinessDebtReminders extends Command
{
    protected $signature = 'debts:send-reminders';

    protected $description = 'Send SMS debt reminders to customers and staff when due dates are reached';

    public function handle(BusinessDebtReminderService $reminders): int
    {
        $counts = $reminders->sendDueReminders();
        $total = array_sum($counts);

        if ($total > 0) {
            $this->info(sprintf(
                'Debt SMS sent — due soon: %d, due soon (2nd): %d, due today: %d, overdue: %d.',
                $counts['due_soon'],
                $counts['due_soon_second'],
                $counts['due_today'],
                $counts['overdue'],
            ));
        }

        return self::SUCCESS;
    }
}
