<?php

namespace App\Services;

use App\Mail\NewSupportTicketMail;
use App\Mail\PaymentReminderMail;
use App\Mail\PlatformNotificationMail;
use App\Models\Business;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PlatformAdminService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlatformMailService
{
    public function __construct(private PlatformSettingsService $settings)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('email_enabled', true);
    }

    public function actionEnabled(string $action): bool
    {
        if (! $this->isEnabled() || ! $this->settings->isMailConfigured()) {
            return false;
        }

        return (bool) $this->settings->get('email_'.$action, true);
    }

    public function sendRegistrationVerification(string $email, string $code): bool
    {
        $platformName = $this->platformName();

        return $this->send(
            $email,
            "{$platformName} — Verification Code",
            "Your verification code is {$code}. It expires in 10 minutes.",
            'registration_verification',
        );
    }

    public function sendRegistrationApproved(Business $business, string $password, string $loginEmail): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Account Approved",
            "Your registration for {$business->name} is approved.\n\nSign in with email: {$loginEmail}\nPassword: {$password}\n\nPlease change your password after signing in.",
            'registration_approved',
        );
    }

    public function sendBusinessRegistered(Business $business, string $password, string $loginEmail): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Your Account Is Ready",
            "Welcome! Your business account for {$business->name} is ready.\n\nSign in with email: {$loginEmail}\nPassword: {$password}\n\nPlease change your password after signing in.",
            'business_registered',
        );
    }

    public function sendBusinessLinkedToOwner(Business $business, string $loginEmail): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — New Business Added",
            "{$business->name} has been added to your account.\n\nSign in with email: {$loginEmail} to access it.",
            'business_linked',
        );
    }

    public function sendRegistrationRejected(?string $email, string $businessName): bool
    {
        if (! $this->isDeliverableEmail($email)) {
            return false;
        }

        $platformName = $this->platformName();

        return $this->send(
            $email,
            "{$platformName} — Registration Not Approved",
            "Your registration for {$businessName} was not approved. Contact support for help.",
            'registration_rejected',
        );
    }

    public function sendPasswordReset(Business $business, string $password, string $loginEmail): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Password Reset",
            "Your password was reset.\n\nSign in with email: {$loginEmail}\nNew password: {$password}",
            'password_reset',
        );
    }

    public function sendAccountSuspended(Business $business, ?string $reason = null): bool
    {
        $platformName = $this->platformName();
        $suffix = filled($reason) ? "\n\nReason: {$reason}" : '';

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Account Suspended",
            "Your account has been suspended. Contact support to restore access.{$suffix}",
            'account_suspended',
        );
    }

    public function sendAccountReactivated(Business $business): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Account Active",
            'Your account is active again. You can sign in and continue using your POS.',
            'account_reactivated',
        );
    }

    public function sendAutoSuspendNotice(Business $business): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Account Suspended",
            'Your account was suspended due to an expired subscription or unpaid invoice. Please renew to restore access.',
            'auto_suspend',
        );
    }

    public function sendPaymentConfirmed(Business $business, ?string $expiryLabel = null): bool
    {
        $platformName = $this->platformName();
        $suffix = filled($expiryLabel) ? "\n\nSubscription valid until {$expiryLabel}." : '';

        return $this->sendToBusiness(
            $business,
            "{$platformName} — Payment Received",
            "Your payment was received. Thank you!{$suffix}",
            'payment_confirmed',
        );
    }

    public function notifyAdminNewTicket(Ticket $ticket): bool
    {
        if (! $this->actionEnabled('ticket_new_admin')) {
            return false;
        }

        $sent = false;
        $seenEmails = [];

        foreach (app(PlatformAdminService::class)->ticketNotificationStaff() as $staff) {
            $email = strtolower(trim((string) ($staff->email ?? '')));
            if (! $this->isDeliverableEmail($email) || in_array($email, $seenEmails, true)) {
                continue;
            }

            $seenEmails[] = $email;

            try {
                Mail::to($email)->send(new NewSupportTicketMail($ticket));
                $sent = true;
            } catch (\Throwable $exception) {
                Log::warning('Platform ticket staff email failed', [
                    'ticket_id' => $ticket->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $adminEmail = $this->adminEmail();
        if ($adminEmail && ! in_array(strtolower($adminEmail), $seenEmails, true)) {
            try {
                Mail::to($adminEmail)->send(new NewSupportTicketMail($ticket));
                $sent = true;
            } catch (\Throwable $exception) {
                Log::warning('Platform ticket admin email failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    public function notifyBusinessTicketReply(Ticket $ticket): bool
    {
        $ticket->loadMissing(['business.ownerUser', 'user']);
        $platformName = $this->platformName();
        $body = "Support replied to your ticket \"{$ticket->subject}\". Sign in to read the response.";

        $email = null;
        $recipientName = $ticket->user?->name;

        if ($ticket->user && $this->isDeliverableEmail($ticket->user->email)) {
            $email = strtolower(trim((string) $ticket->user->email));
        } else {
            $email = $this->resolveBusinessEmail($ticket->business);
            $recipientName = $recipientName ?: ($ticket->business?->contact_person ?: $ticket->business?->name);
        }

        if (! filled($email)) {
            return false;
        }

        return $this->send(
            $email,
            "{$platformName} — Support Reply",
            $body,
            'ticket_reply_business',
            $recipientName,
        );
    }

    /**
     * @param  array{name?: string, phone?: string|null, email?: string|null, company?: string|null, message?: string|null}  $lead
     */
    public function notifyAdminDemoLead(array $lead): bool
    {
        if (! $this->actionEnabled('demo_lead_admin')) {
            return false;
        }

        $email = $this->adminEmail();

        if (! filled($email)) {
            return false;
        }

        $platformName = $this->platformName();
        $name = $lead['name'] ?? 'Someone';

        return $this->send(
            $email,
            "{$platformName} — New Demo Request",
            "New demo request from {$name}.\nPhone: ".($lead['phone'] ?? '—')."\nEmail: ".($lead['email'] ?? '—')."\nCompany: ".($lead['company'] ?? '—')."\n\n".($lead['message'] ?? ''),
            'demo_lead_admin',
        );
    }

    public function sendStaffWelcome(User $user, string $password): bool
    {
        $platformName = $this->platformName();

        return $this->send(
            $user->email,
            "{$platformName} — Admin Account Created",
            "Your admin account is ready.\n\nEmail: {$user->email}\nPassword: {$password}",
            'staff_welcome',
            $user->name,
        );
    }

    public function sendExpiryReminder(Business $business, string $expiryLabel): bool
    {
        if (! $this->reminderChannelIncludesEmail()) {
            return false;
        }

        $email = $this->resolveBusinessEmail($business);

        if (! filled($email)) {
            return false;
        }

        $platformName = $this->platformName();

        try {
            Mail::to($email)->send(new PaymentReminderMail(
                $business,
                'Subscription Expiring Soon',
                "Your {$platformName} subscription expires on {$expiryLabel}. Please renew promptly to keep your POS active.",
                null
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Expiry reminder email failed', [
                'business_id' => $business->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function sendInvoiceReminder(Business $business, string $invoiceNumber, string $amount, ?\App\Models\PlatformBillingInvoice $invoice = null): bool
    {
        if (! $this->reminderChannelIncludesEmail()) {
            return false;
        }

        $email = $this->resolveBusinessEmail($business);

        if (! filled($email)) {
            return false;
        }

        try {
            Mail::to($email)->send(new PaymentReminderMail(
                $business,
                'Payment Reminder — '.$invoiceNumber,
                "This is a reminder that invoice {$invoiceNumber} for TZS {$amount} is still unpaid.",
                $invoice
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Invoice reminder email failed', [
                'business_id' => $business->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sendToBusiness(Business $business, string $subject, string $body, string $action): bool
    {
        $email = $this->resolveBusinessEmail($business);

        if (! filled($email)) {
            return false;
        }

        $recipientName = $business->contact_person ?: $business->name;

        return $this->send($email, $subject, $body, $action, $recipientName);
    }

    private function send(string $email, string $subject, string $body, string $action, ?string $recipientName = null): bool
    {
        if (! $this->actionEnabled($action) || ! $this->isDeliverableEmail($email)) {
            return false;
        }

        try {
            Mail::to($email)->send(new PlatformNotificationMail($subject, $body, $recipientName));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Platform email failed', [
                'action' => $action,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveBusinessEmail(Business $business): ?string
    {
        $business->loadMissing('ownerUser');

        foreach ([$business->email, $business->ownerUser?->email] as $email) {
            if ($this->isDeliverableEmail($email)) {
                return strtolower(trim((string) $email));
            }
        }

        return null;
    }

    private function isDeliverableEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return ! str_ends_with($email, '@phone.mauzolink.local');
    }

    private function adminEmail(): ?string
    {
        $email = trim((string) $this->settings->get('admin_notification_email', ''));

        if ($email !== '') {
            return $email;
        }

        $supportEmail = trim((string) $this->settings->get('support_email', ''));

        return $supportEmail !== '' ? $supportEmail : null;
    }

    private function platformName(): string
    {
        return (string) $this->settings->get('platform_name', 'Mauzo Link');
    }

    private function reminderChannelIncludesEmail(): bool
    {
        $raw = $this->settings->get('payment_reminder_channels', 'email,sms');
        $parts = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);

        return in_array('email', array_map('strtolower', $parts ?: []), true);
    }
}
