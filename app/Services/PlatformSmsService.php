<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformSmsLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PlatformAdminService;
use Illuminate\Support\Facades\Log;

class PlatformSmsService
{
    public function __construct(
        private SmsService $smsService,
        private PlatformSettingsService $settings,
    ) {
    }

    public function formatPhoneNumber(string $phone): string
    {
        return $this->smsService->formatPhoneNumber($phone);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('sms_enabled', true);
    }

    public function actionEnabled(string $action): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return (bool) $this->settings->get('sms_'.$action, true);
    }

    public function sendRegistrationVerification(string $phone, string $code): bool
    {
        $platformName = $this->platformName();

        return $this->sendToPhone(
            $phone,
            "{$platformName}: Your verification code is {$code}. It expires in 10 minutes.",
            'registration_verification',
            'registration_verification',
        );
    }

    public function sendRegistrationApproved(Business $business, string $password, string $loginEmail): bool
    {
        $supportPhone = $this->supportContactPhone();

        return $this->sendToBusiness(
            $business,
            "Usajili wa biashara {$business->name} umekubaliwa. Ingia kupitia email yako ambayo ni {$loginEmail}. Nenosiri: {$password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {$supportPhone}",
            'registration_approved',
            'registration_approved',
        );
    }

    public function sendBusinessRegistered(Business $business, string $password, string $loginEmail): bool
    {
        $supportPhone = $this->supportContactPhone();

        return $this->sendToBusiness(
            $business,
            "Akaunti yako ya biashara {$business->name} iko tayari. Ingia kupitia email yako ambayo ni {$loginEmail}. Nenosiri: {$password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {$supportPhone}",
            'business_registered',
            'business_registered',
        );
    }

    public function sendBusinessLinkedToOwner(Business $business, string $loginEmail): bool
    {
        $supportPhone = $this->supportContactPhone();

        return $this->sendToBusiness(
            $business,
            "Biashara {$business->name} imeongezwa kwenye akaunti yako. Ingia kupitia email yako ambayo ni {$loginEmail}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {$supportPhone}",
            'business_linked',
            'business_linked',
        );
    }

    public function sendRegistrationRejected(?string $phone, string $businessName): bool
    {
        $platformName = $this->platformName();

        return $this->sendToPhone(
            $phone,
            "{$platformName}: Your registration for {$businessName} was not approved. Contact support for help.",
            'registration_rejected',
            'registration_rejected',
        );
    }

    public function sendPasswordReset(Business $business, string $password, ?string $loginEmail = null): bool
    {
        $supportPhone = $this->supportContactPhone();
        $loginEmail = $loginEmail ?: $business->email;
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Nenosiri lako limewekwa upya. Ingia kupitia email yako ambayo ni {$loginEmail}. Nenosiri jipya: {$password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {$supportPhone}",
            'password_reset',
            'password_reset',
        );
    }

    public function sendAccountSuspended(Business $business, ?string $reason = null): bool
    {
        $platformName = $this->platformName();
        $suffix = filled($reason) ? " Reason: {$reason}" : '';

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Your account has been suspended.{$suffix} Contact support to restore access.",
            'account_suspended',
            'account_suspended',
        );
    }

    public function sendAccountReactivated(Business $business): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Your account is active again. You can sign in and continue using your POS.",
            'account_reactivated',
            'account_reactivated',
        );
    }

    public function sendAutoSuspendNotice(Business $business): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Your account was suspended due to an expired subscription or unpaid invoice. Please renew to restore access.",
            'auto_suspend',
            'auto_suspend',
        );
    }

    public function sendExpiryReminder(Business $business, string $expiryLabel): bool
    {
        if (! $this->reminderChannelIncludesSms()) {
            return false;
        }

        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Your subscription expires on {$expiryLabel}. Please renew to avoid interruption.",
            'expiry_reminder',
            null,
        );
    }

    public function sendInvoiceReminder(Business $business, string $invoiceNumber, string $amount): bool
    {
        if (! $this->reminderChannelIncludesSms()) {
            return false;
        }

        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Invoice {$invoiceNumber} for TZS {$amount} is unpaid. Please pay to keep your account active.",
            'invoice_reminder',
            null,
        );
    }

    public function sendInvoiceIssued(Business $business, string $invoiceNumber, string $amount): bool
    {
        $platformName = $this->platformName();

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Invoice {$invoiceNumber} for TZS {$amount} has been issued. Please pay before the due date.",
            'invoice_issued',
            'invoice_issued',
        );
    }

    public function sendPaymentConfirmed(Business $business, ?string $expiryLabel = null): bool
    {
        $platformName = $this->platformName();
        $suffix = filled($expiryLabel) ? " Subscription valid until {$expiryLabel}." : '';

        return $this->sendToBusiness(
            $business,
            "{$platformName}: Your payment was received. Thank you!{$suffix}",
            'payment_confirmed',
            'payment_confirmed',
        );
    }

    public function notifyAdminNewTicket(Ticket $ticket): bool
    {
        $ticket->loadMissing('business');
        $platformName = $this->platformName();
        $businessName = $ticket->business?->name ?? 'Unknown';
        $message = "{$platformName}: New support ticket from {$businessName}: ".mb_substr($ticket->subject, 0, 60);

        if (! $this->actionEnabled('ticket_new_admin')) {
            return false;
        }

        $sent = false;
        $seenPhones = [];

        foreach (app(PlatformAdminService::class)->ticketNotificationStaff() as $staff) {
            $phone = trim((string) ($staff->phone ?? ''));
            if ($phone === '') {
                continue;
            }

            $normalized = $this->formatPhoneNumber($phone);
            if (in_array($normalized, $seenPhones, true)) {
                continue;
            }

            $seenPhones[] = $normalized;

            if ($this->sendToPhone(
                $phone,
                $message,
                'ticket_new_admin',
                'ticket_new_admin',
                null,
                $staff->id,
                $staff->name,
            )) {
                $sent = true;
            }
        }

        if (! $sent) {
            $sent = $this->sendToAdmin($message, 'ticket_new_admin', 'ticket_new_admin');
        }

        return $sent;
    }

    public function notifyBusinessTicketReply(Ticket $ticket): bool
    {
        $ticket->loadMissing(['business', 'user']);
        $platformName = $this->platformName();
        $message = "{$platformName}: Support replied to your ticket \"{$ticket->subject}\". Sign in to read the response.";

        $phone = trim((string) ($ticket->user?->phone ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($ticket->business?->phone ?? ''));
        }

        return $this->sendToPhone(
            $phone,
            $message,
            'ticket_reply_business',
            'ticket_reply_business',
            $ticket->business_id,
            $ticket->user_id,
            $ticket->user?->name ?? $ticket->business?->name,
        );
    }

    /**
     * @param  array{name: string, phone?: string|null, email?: string|null, company?: string|null}  $lead
     */
    public function notifyAdminDemoLead(array $lead): bool
    {
        $platformName = $this->platformName();
        $name = $lead['name'] ?? 'Someone';
        $phone = $lead['phone'] ?? '—';

        return $this->sendToAdmin(
            "{$platformName}: New demo request from {$name}. Phone: {$phone}",
            'demo_lead_admin',
            'demo_lead_admin',
        );
    }

    public function sendStaffWelcome(User $user, string $password): bool
    {
        $platformName = $this->platformName();

        return $this->sendToPhone(
            $user->phone,
            "{$platformName}: Admin account created. Email: {$user->email}. Password: {$password}",
            'staff_welcome',
            'staff_welcome',
            null,
            $user->id,
            $user->name,
        );
    }

    private function sendToBusiness(Business $business, string $message, string $purpose, ?string $settingKey): bool
    {
        return $this->sendToPhone(
            $business->phone,
            $message,
            $purpose,
            $settingKey,
            $business->id,
            $business->owner_user_id,
            $business->name,
        );
    }

    private function sendToAdmin(string $message, string $purpose, string $settingKey): bool
    {
        $phone = $this->adminPhone();

        if (! filled($phone)) {
            return false;
        }

        return $this->sendToPhone($phone, $message, $purpose, $settingKey);
    }

    private function sendToPhone(
        ?string $phone,
        string $message,
        string $purpose,
        ?string $settingKey,
        ?int $businessId = null,
        ?int $userId = null,
        ?string $recipientName = null,
    ): bool {
        if (! filled($phone)) {
            return false;
        }

        if (! $this->isEnabled()) {
            return false;
        }

        if ($settingKey !== null && ! $this->actionEnabled($settingKey)) {
            return false;
        }

        $formattedPhone = $this->smsService->formatPhoneNumber($phone);

        $log = PlatformSmsLog::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'phone' => $formattedPhone,
            'recipient_name' => $recipientName,
            'message' => $message,
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        try {
            $result = $this->smsService->sendSms($formattedPhone, $message);
            $success = (bool) ($result['success'] ?? false);

            $log->update([
                'status' => $success ? 'sent' : 'failed',
                'provider_response' => is_string($result['response'] ?? null)
                    ? $result['response']
                    : json_encode($result),
            ]);

            if (! $success) {
                Log::warning('Platform SMS failed', [
                    'purpose' => $purpose,
                    'phone' => $formattedPhone,
                    'error' => $result['error'] ?? $result['response'] ?? 'unknown',
                ]);
            }

            return $success;
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'provider_response' => $exception->getMessage(),
            ]);

            Log::error('Platform SMS exception', [
                'purpose' => $purpose,
                'phone' => $formattedPhone,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function platformName(): string
    {
        return (string) $this->settings->get('platform_name', 'Mauzo Link');
    }

    private function supportContactPhone(): string
    {
        $supportPhone = trim((string) $this->settings->get('support_phone', ''));

        return $supportPhone !== '' ? $supportPhone : '0749719998';
    }

    private function adminPhone(): ?string
    {
        $phone = trim((string) $this->settings->get('admin_notification_phone', ''));

        if ($phone !== '') {
            return $phone;
        }

        $supportPhone = trim((string) $this->settings->get('support_phone', ''));

        return $supportPhone !== '' ? $supportPhone : null;
    }

    private function reminderChannelIncludesSms(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $raw = $this->settings->get('payment_reminder_channels', 'email,sms');
        $parts = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw);

        return in_array('sms', array_map('strtolower', $parts ?: []), true);
    }
}
