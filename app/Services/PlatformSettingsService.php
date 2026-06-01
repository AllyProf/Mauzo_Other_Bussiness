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
            'payment_instructions' => '',

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
        if (! Schema::hasTable('platform_settings')) {
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
        if (! Schema::hasTable('platform_settings')) {
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

        if ($host === '') {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) $this->get('mail_port', 587));
        Config::set('mail.mailers.smtp.encryption', $this->get('mail_encryption') ?: null);
        Config::set('mail.mailers.smtp.username', $this->get('mail_username'));
        Config::set('mail.mailers.smtp.password', $this->mailPassword());

        $fromAddress = trim((string) $this->get('mail_from_address'));
        $fromName = trim((string) $this->get('mail_from_name'));

        if ($fromAddress !== '') {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName !== '' ? $fromName : $this->get('platform_name'));
        }
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
