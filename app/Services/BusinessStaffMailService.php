<?php

namespace App\Services;

use App\Mail\CustomerCommunicationMail;
use App\Mail\StaffNotificationMail;
use App\Models\Business;
use App\Models\BusinessNote;
use App\Models\Customer;
use App\Models\DayClosing;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BusinessStaffMailService
{
    public function __construct(private BusinessStaffSmsService $staffSms)
    {
    }

    public function isEnabled(Business $business): bool
    {
        return (bool) ($business->automationSettings()['email_staff_enabled'] ?? true);
    }

    public function debtEmailEnabled(Business $business): bool
    {
        return (bool) ($business->automationSettings()['email_debt_enabled'] ?? true);
    }

    public function actionEnabled(Business $business, string $action): bool
    {
        if (! $this->isEnabled($business)) {
            return false;
        }

        return (bool) ($business->automationSettings()['email_staff_'.$action] ?? true);
    }

    public function debtActionEnabled(Business $business, string $phase, string $recipient): bool
    {
        if (! $this->debtEmailEnabled($business)) {
            return false;
        }

        $key = 'email_debt_'.$phase.'_'.$recipient;

        return (bool) ($business->automationSettings()[$key] ?? true);
    }

    public function sendStaffWelcome(Business $business, User $staff, string $password): bool
    {
        return $this->sendStaff(
            $business,
            $staff,
            'Staff Account Ready',
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_welcome', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
                '{password}' => $password,
            ]),
            'welcome',
        );
    }

    public function sendPasswordReset(Business $business, User $staff, string $password): bool
    {
        return $this->sendStaff(
            $business,
            $staff,
            'Password Reset',
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_password_reset', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
                '{password}' => $password,
            ]),
            'password_reset',
        );
    }

    public function sendAccountActivated(Business $business, User $staff): bool
    {
        return $this->sendStaff(
            $business,
            $staff,
            'Account Activated',
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_activated', [
                '{staff_name}' => $staff->name,
                '{email}' => $staff->email,
            ]),
            'activated',
        );
    }

    public function sendAccountDeactivated(Business $business, User $staff): bool
    {
        return $this->sendStaff(
            $business,
            $staff,
            'Account Deactivated',
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_deactivated', [
                '{staff_name}' => $staff->name,
            ]),
            'deactivated',
        );
    }

    public function notifyHandoverSubmitted(Business $business, User $submitter, DayClosing $closing): void
    {
        $vars = $this->staffSms->buildHandoverSubmittedVars($business, $submitter, $closing);
        $notifiedEmails = [];

        $owner = $business->resolveOwner();
        $ownerEmail = $this->resolveUserEmail($owner, $business);
        $ownerName = $owner?->name ?? $business->contact_person ?? $business->name;

        if (filled($ownerEmail) && $this->handoverSubmittedNotifyEnabled($business, 'owner')) {
            $sent = $this->sendHandoverSubmittedEmail(
                $business,
                $ownerEmail,
                $ownerName,
                $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_handover_submitted_owner', array_merge($vars, [
                    '{owner}' => $ownerName,
                ])),
            );

            if ($sent) {
                $notifiedEmails[] = strtolower($ownerEmail);
            }
        }

        foreach ($business->resolveManagers() as $manager) {
            if ($manager->id === $submitter->id) {
                continue;
            }

            $email = $this->resolveUserEmail($manager, $business);
            if (! filled($email)) {
                continue;
            }

            $emailKey = strtolower($email);
            if (in_array($emailKey, $notifiedEmails, true)) {
                continue;
            }

            if (! $this->handoverSubmittedNotifyEnabled($business, 'manager')) {
                continue;
            }

            $sent = $this->sendHandoverSubmittedEmail(
                $business,
                $email,
                $manager->name,
                $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_handover_submitted_manager', array_merge($vars, [
                    '{manager}' => $manager->name,
                ])),
            );

            if ($sent) {
                $notifiedEmails[] = $emailKey;
            }
        }
    }

    /** @deprecated Use notifyHandoverSubmitted() */
    public function notifyOwnerHandoverSubmitted(Business $business, User $submitter, DayClosing $closing): bool
    {
        $this->notifyHandoverSubmitted($business, $submitter, $closing);

        return true;
    }

    public function notifyStaffHandoverVerified(Business $business, User $verifier, DayClosing $closing): bool
    {
        $closing->loadMissing('user');
        $staff = $closing->user;

        if (! $staff) {
            return false;
        }

        $dateLabel = $closing->closing_date->format('d M Y');
        $moneyShort = (float) ($closing->money_short ?? 0);
        $moneyShortNote = $moneyShort > 0
            ? ' Note: money short of TZS '.number_format($moneyShort, 0).' recorded.'
            : '';

        return $this->sendStaff(
            $business,
            $staff,
            'Reconciliation Verified',
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_handover_verified_staff', [
                '{staff_name}' => $staff->name,
                '{verifier}' => $verifier->name,
                '{date}' => $dateLabel,
                '{money_short}' => $moneyShort > 0 ? number_format($moneyShort, 0) : '',
                '{money_short_note}' => $moneyShortNote,
            ]),
            'handover_verified_staff',
        );
    }

    public function sendNoteReminder(Business $business, User $recipient, BusinessNote $note): bool
    {
        $email = $this->resolveUserEmail($recipient, $business);

        if (! filled($email)) {
            return false;
        }

        $title = $note->displayTitle();
        $preview = Str::limit(trim(strip_tags($note->body)), 100);
        $when = $note->remind_at?->format('d M Y g:i A') ?? 'now';

        return $this->sendRaw(
            $business,
            $email,
            $recipient->name,
            'Reminder: '.$title,
            $this->staffSms->buildAutomationMessage($business, 'sms_staff_template_note_reminder', [
                '{staff_name}' => $recipient->name,
                '{title}' => $title,
                '{when}' => $when,
                '{preview}' => $preview,
            ]),
            'note_reminder',
        );
    }

    public function sendDebtDueSoonToCustomer(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $wave = 'first'): bool
    {
        return $this->sendDebtToCustomer($business, $sale, $balance, $dueDate, 'due_soon', $wave);
    }

    public function sendDebtDueTodayToCustomer(Business $business, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToCustomer($business, $sale, $balance, $dueDate, 'due_today');
    }

    public function sendDebtOverdueToCustomer(Business $business, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToCustomer($business, $sale, $balance, $dueDate, 'overdue');
    }

    public function sendDebtDueSoonToStaff(Business $business, Sale $sale, float $balance, Carbon $dueDate, string $wave = 'first'): bool
    {
        return $this->sendDebtToStaff($business, $sale, $balance, $dueDate, 'due_soon', $wave);
    }

    public function sendDebtDueTodayToStaff(Business $business, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToStaff($business, $sale, $balance, $dueDate, 'due_today');
    }

    public function sendDebtOverdueToStaff(Business $business, Sale $sale, float $balance, Carbon $dueDate): bool
    {
        return $this->sendDebtToStaff($business, $sale, $balance, $dueDate, 'overdue');
    }

    private function sendDebtToCustomer(
        Business $business,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $phase,
        string $wave = 'first',
    ): bool {
        if (! $this->debtActionEnabled($business, $phase, 'customer')) {
            return false;
        }

        $email = $this->resolveDebtorEmail($sale);

        if (! filled($email)) {
            return false;
        }

        $message = $this->staffSms->buildDebtCustomerMessage($business, $sale, $balance, $dueDate, $phase, $wave);
        $customer = $this->debtCustomerModel($sale, $business);

        try {
            Mail::to($email)->send(new CustomerCommunicationMail(
                $business,
                $customer,
                $business->name.' — Payment Reminder',
                $message,
                'debt_reminder_'.$phase,
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Debt reminder customer email failed', [
                'sale_id' => $sale->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendDebtToStaff(
        Business $business,
        Sale $sale,
        float $balance,
        Carbon $dueDate,
        string $phase,
        string $wave = 'first',
    ): bool {
        $staff = $sale->user;

        if (! $staff) {
            return false;
        }

        if (! $this->debtActionEnabled($business, $phase, 'staff')) {
            return false;
        }

        return $this->sendStaffMessage(
            $business,
            $staff,
            'Debt Reminder',
            $this->staffSms->buildDebtStaffMessage($business, $sale, $balance, $dueDate, $phase, $wave),
        );
    }

    private function sendStaffMessage(Business $business, User $staff, string $subject, string $message): bool
    {
        if (! filled($staff->email)) {
            return false;
        }

        try {
            Mail::to($staff->email)->send(new StaffNotificationMail(
                $business,
                $business->name.' — '.$subject,
                $message,
                $staff->name,
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Staff email failed', [
                'business_id' => $business->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendStaff(Business $business, User $staff, string $subject, string $message, string $action): bool
    {
        if (! $this->actionEnabled($business, $action) || ! filled($staff->email)) {
            return false;
        }

        return $this->sendStaffMessage($business, $staff, $subject, $message);
    }

    private function handoverSubmittedNotifyEnabled(Business $business, string $role): bool
    {
        if (! $this->isEnabled($business)) {
            return false;
        }

        return (bool) ($business->automationSettings()['sms_staff_handover_submitted_'.$role] ?? true);
    }

    private function sendHandoverSubmittedEmail(
        Business $business,
        string $email,
        string $recipientName,
        string $message,
    ): bool {
        if (blank($email)) {
            return false;
        }

        try {
            Mail::to($email)->send(new StaffNotificationMail(
                $business,
                $business->name.' — Shift Closed — Sales Summary',
                $message,
                $recipientName,
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Handover summary email failed', [
                'business_id' => $business->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendRaw(
        Business $business,
        string $email,
        string $recipientName,
        string $subject,
        string $message,
        string $action,
    ): bool {
        if (! $this->actionEnabled($business, $action) || blank($email)) {
            return false;
        }

        try {
            Mail::to($email)->send(new StaffNotificationMail(
                $business,
                $business->name.' — '.$subject,
                $message,
                $recipientName,
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Staff email failed', [
                'business_id' => $business->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveUserEmail(?User $user, Business $business): ?string
    {
        if ($user && filled($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return $user->email;
        }

        if ($user?->role === 'owner' && filled($business->email) && filter_var($business->email, FILTER_VALIDATE_EMAIL)) {
            return $business->email;
        }

        return null;
    }

    private function resolveDebtorEmail(Sale $sale): ?string
    {
        $sale->loadMissing('customer');

        if ($sale->customer && filled($sale->customer->email)) {
            return $sale->customer->email;
        }

        return null;
    }

    private function debtCustomerModel(Sale $sale, Business $business): Customer
    {
        if ($sale->customer) {
            return $sale->customer;
        }

        return new Customer([
            'business_id' => $business->id,
            'name' => $sale->customer_name ?: 'Customer',
            'email' => null,
            'phone' => $sale->customer_phone,
        ]);
    }
}
