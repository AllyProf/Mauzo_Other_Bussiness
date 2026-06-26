<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsService
{
    private const CACHE_KEY = 'platform_settings.all';

    public function defaults(): array
    {
        return [
            'platform_name' => 'Mauzo Link',
            'brand_color' => '#940000',
            'support_email' => 'admin@sp-pos.com',
            'support_phone' => '',
            'support_whatsapp' => '',
            'timezone' => 'Africa/Dar_es_Salaam',
            'currency_code' => 'TZS',
            'currency_symbol' => 'TZS',
            'legal_footer' => '',

            'allow_public_registration' => true,
            'require_admin_approval' => true,
            'default_plan_id' => null,
            'default_trial_days' => 30,
            'min_password_length' => 8,

            'grace_period_days' => 0,
            'expiry_warning_days' => 7,
            'auto_suspend_on_expiry' => true,
            'auto_email_billing_invoices' => true,
            'payment_reminder_days' => 7,
            'payment_reminder_channels' => 'email,sms',
            'auto_suspend_unpaid_days' => 14,
            'payment_instructions' => '',

            'admin_ip_allowlist' => '',
            'admin_notification_email' => '',
            'admin_notification_phone' => '',
            'audit_log_retention_days' => 365,

            'scheduler_last_run_at' => null,

            'sms_enabled' => true,
            'sms_registration_verification' => true,
            'sms_registration_approved' => true,
            'sms_registration_rejected' => true,
            'sms_password_reset' => true,
            'sms_account_suspended' => true,
            'sms_account_reactivated' => true,
            'sms_auto_suspend' => true,
            'sms_invoice_issued' => true,
            'sms_payment_confirmed' => true,
            'sms_ticket_new_admin' => true,
            'sms_ticket_reply_business' => true,
            'sms_staff_welcome' => true,
            'sms_demo_lead_admin' => true,

            'sms_template_registration_verification' => '{platform_name}: Your verification code is {code}. It expires in 10 minutes.',
            'sms_template_registration_pending' => 'Usajili wako wa biashara ya {business_name} umepokelewa na unasubiri idhini. Utapata ujumbe mfupi mara utakapokubaliwa.',
            'sms_template_registration_approved' => 'Usajili wa biashara {business_name} umekubaliwa. Ingia kupitia email yako ambayo ni {login_email}. Nenosiri: {password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {support_phone}',
            'sms_template_registration_rejected' => '{platform_name}: Your registration for {business_name} was not approved. Contact support for help.',
            'sms_template_business_registered' => 'Akaunti yako ya biashara {business_name} iko tayari. Ingia kupitia email yako ambayo ni {login_email}. Nenosiri: {password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {support_phone}',
            'sms_template_business_linked' => 'Biashara {business_name} imeongezwa kwenye akaunti yako. Ingia kupitia email yako ambayo ni {login_email}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {support_phone}',
            'sms_template_password_reset' => '{platform_name}: Nenosiri lako limewekwa upya. Ingia kupitia email yako ambayo ni {login_email}. Nenosiri jipya: {password}. Endapo una changamoto yoyote tumia namba hii kuwasiliana nasi: {support_phone}',
            'sms_template_account_suspended' => '{platform_name}: Your account has been suspended.{reason} Contact support to restore access.',
            'sms_template_account_reactivated' => '{platform_name}: Your account is active again. You can sign in and continue using your POS.',

            'email_enabled' => true,
            'email_registration_verification' => true,
            'email_registration_approved' => true,
            'email_registration_rejected' => true,
            'email_password_reset' => true,
            'email_account_suspended' => true,
            'email_account_reactivated' => true,
            'email_auto_suspend' => true,
            'email_invoice_issued' => true,
            'email_payment_confirmed' => true,
            'email_ticket_new_admin' => true,
            'email_ticket_reply_business' => true,
            'email_staff_welcome' => true,
            'email_demo_lead_admin' => true,

            'default_billing_model' => 'fixed_monthly',
            'default_profit_share_percent' => 5,
            'default_profit_share_basis' => 'net_profit',

            'mail_driver' => 'smtp',
            'mail_host' => '',
            'mail_port' => 587,
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_address' => '',
            'mail_from_name' => '',

            'maintenance_mode' => false,
            'maintenance_message' => 'The system is under maintenance. Please try again shortly.',
        ];
    }

    public function all(): array
    {
        try {
            if (! database_is_ready() || ! Schema::hasTable('platform_settings')) {
                return $this->defaults();
            }
        } catch (\Throwable) {
            return $this->defaults();
        }

        return Cache::remember(self::CACHE_KEY, 300, function () {
            try {
                $stored = PlatformSetting::instance()->settings ?? [];
            } catch (\Throwable) {
                return $this->defaults();
            }

            return array_merge($this->defaults(), $stored);
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function update(array $partial): void
    {
        try {
            if (! database_is_ready() || ! Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $current = PlatformSetting::instance();
        $merged = array_merge($this->all(), $partial);

        if (array_key_exists('mail_password', $partial)) {
            if ($partial['mail_password'] === '' || $partial['mail_password'] === null) {
                $merged['mail_password'] = $current->settings['mail_password'] ?? '';
            } else {
                $merged['mail_password'] = Crypt::encryptString($partial['mail_password']);
            }
        }

        $current->update([
            'settings' => collect($merged)->only(array_keys($this->defaults()))->all(),
        ]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Store a single key/value directly (bypasses the defaults-only filter).
     * Useful for runtime telemetry like scheduler heartbeats.
     */
    public function set(string $key, mixed $value): void
    {
        try {
            if (! database_is_ready() || ! Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $current = PlatformSetting::instance();
        $settings = $current->settings ?? [];
        $settings[$key] = $value;
        $current->update(['settings' => $settings]);
        Cache::forget(self::CACHE_KEY);
    }

    public function mailPassword(): ?string
    {
        $encrypted = $this->get('mail_password');

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function applyMailConfig(): void
    {
        $host = trim((string) $this->get('mail_host'));
        $usePlatformSettings = $host !== '';

        if (! $usePlatformSettings) {
            $host = trim((string) env('MAIL_HOST', ''));

            if ($host === '' || env('MAIL_MAILER', 'log') === 'log') {
                return;
            }
        }

        $port = $usePlatformSettings
            ? (int) $this->get('mail_port', 587)
            : (int) env('MAIL_PORT', 587);
        $encryption = $usePlatformSettings
            ? (string) $this->get('mail_encryption', 'tls')
            : (string) (env('MAIL_SCHEME') === 'smtps' ? 'ssl' : env('MAIL_ENCRYPTION', 'tls'));
        $username = $usePlatformSettings ? $this->get('mail_username') : env('MAIL_USERNAME');
        $password = $usePlatformSettings ? $this->mailPassword() : env('MAIL_PASSWORD');
        $fromAddress = trim((string) ($usePlatformSettings ? $this->get('mail_from_address') : env('MAIL_FROM_ADDRESS', '')));
        $fromName = trim((string) ($usePlatformSettings ? $this->get('mail_from_name') : env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))));

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : null);

        if ($fromAddress !== '') {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName !== '' ? $fromName : $this->get('platform_name'));
        }
    }

    public function isMailConfigured(): bool
    {
        if (trim((string) $this->get('mail_host')) !== '') {
            return true;
        }

        return trim((string) env('MAIL_HOST', '')) !== ''
            && env('MAIL_MAILER', 'log') !== 'log';
    }

    public function isRegistrationOpen(): bool
    {
        return (bool) $this->get('allow_public_registration', true);
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_mode', false);
    }

    public function businessIsLocked(\App\Models\Business $business): bool
    {
        if (! $business->is_active) {
            return true;
        }

        if (! $business->expiry_date) {
            return false;
        }

        $expiry = \Carbon\Carbon::parse($business->expiry_date)->endOfDay();
        $graceDays = max(0, (int) $this->get('grace_period_days', 0));
        $lockDate = $expiry->copy()->addDays($graceDays);

        if ($lockDate->isFuture()) {
            return false;
        }

        if ((bool) $this->get('auto_suspend_on_expiry', true) && $business->is_active) {
            $business->forceFill(['is_active' => false])->save();
        }

        return true;
    }
}
