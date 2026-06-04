<?php

namespace App\Services;

use App\Models\BusinessNote;
use Illuminate\Support\Facades\Log;

class BusinessNoteReminderService
{
    public function __construct(
        private BusinessStaffSmsService $staffSms,
        private BusinessStaffMailService $staffMail,
    ) {
    }

    public function sendDueReminders(): int
    {
        $sent = 0;

        BusinessNote::query()
            ->with(['business.plan', 'user'])
            ->dueForSms()
            ->orderBy('remind_at')
            ->limit(50)
            ->each(function (BusinessNote $note) use (&$sent) {
                if ($this->sendReminder($note)) {
                    $sent++;
                }
            });

        return $sent;
    }

    public function sendReminder(BusinessNote $note): bool
    {
        $note->loadMissing(['business.plan', 'user']);

        if ($note->isCompleted() || $note->remind_at === null || $note->remind_at->gt(now())) {
            return false;
        }

        if ($note->reminder_sms_sent_at !== null) {
            return false;
        }

        $business = $note->business;
        $user = $note->user;

        if (! $business || ! $user) {
            return false;
        }

        try {
            $smsSent = $this->staffSms->sendNoteReminder($business, $user, $note);
            $emailSent = $this->staffMail->sendNoteReminder($business, $user, $note);
            $sent = $smsSent || $emailSent;

            if ($sent) {
                $note->update(['reminder_sms_sent_at' => now()]);
            }

            return $sent;
        } catch (\Throwable $exception) {
            Log::warning('Note reminder SMS failed', [
                'note_id' => $note->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
