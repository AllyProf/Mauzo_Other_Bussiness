<?php

namespace App\Console\Commands;

use App\Services\BusinessNoteReminderService;
use Illuminate\Console\Command;

class SendBusinessNoteReminders extends Command
{
    protected $signature = 'notes:send-reminders';

    protected $description = 'Send SMS for business notes whose reminder time has arrived';

    public function handle(BusinessNoteReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();

        if ($sent > 0) {
            $this->info("Sent {$sent} note reminder SMS message(s).");
        }

        return self::SUCCESS;
    }
}
