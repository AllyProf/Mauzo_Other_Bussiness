<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    use EnsuresPlatformAdmin;

    public function __construct(private PlatformSettingsService $settings)
    {
    }

    public function index()
    {
        $this->ensurePlatformAdmin('settings');

        $settings = $this->settings->all();
        $plans = Plan::orderBy('price')->get();

        return view('admin.settings.index', compact('settings', 'plans'));
    }

    public function updateProfile(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'platform_name' => 'required|string|max:100',
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => 'required|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'support_whatsapp' => 'nullable|string|max:50',
            'timezone' => 'required|string|max:100',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'legal_footer' => 'nullable|string|max:1000',
        ]);

        $this->settings->update($data);
        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated platform profile settings');

        return redirect()->route('admin.settings.index', ['tab' => 'profile'])->with('success', 'Platform profile saved.');
    }

    public function updateRegistration(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'allow_public_registration' => 'nullable|boolean',
            'require_admin_approval' => 'nullable|boolean',
            'default_plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'default_trial_days' => 'required|integer|min:1|max:365',
            'min_password_length' => 'required|integer|min:6|max:32',
        ]);

        $this->settings->update([
            'allow_public_registration' => $request->boolean('allow_public_registration'),
            'require_admin_approval' => $request->boolean('require_admin_approval'),
            'default_plan_id' => $data['default_plan_id'] ?? null,
            'default_trial_days' => $data['default_trial_days'],
            'min_password_length' => $data['min_password_length'],
        ]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated registration settings');

        return redirect()->route('admin.settings.index', ['tab' => 'registration'])->with('success', 'Registration settings saved.');
    }

    public function updateSubscription(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'grace_period_days' => 'required|integer|min:0|max:90',
            'expiry_warning_days' => 'required|integer|min:1|max:90',
            'auto_suspend_on_expiry' => 'nullable|boolean',
            'auto_email_billing_invoices' => 'nullable|boolean',
            'payment_reminder_days' => 'required|integer|min:1|max:90',
            'payment_reminder_channels' => 'nullable|string|max:50',
            'auto_suspend_unpaid_days' => 'required|integer|min:0|max:90',
            'payment_instructions' => 'nullable|string|max:2000',
            'default_billing_model' => 'required|in:fixed_monthly,profit_share',
            'default_profit_share_percent' => 'required|numeric|min:0|max:100',
            'default_profit_share_basis' => 'required|in:gross_profit,net_profit',
        ]);

        $this->settings->update([
            'grace_period_days' => $data['grace_period_days'],
            'expiry_warning_days' => $data['expiry_warning_days'],
            'auto_suspend_on_expiry' => $request->boolean('auto_suspend_on_expiry'),
            'auto_email_billing_invoices' => $request->boolean('auto_email_billing_invoices'),
            'payment_reminder_days' => $data['payment_reminder_days'],
            'payment_reminder_channels' => $data['payment_reminder_channels'] ?? 'email,sms',
            'auto_suspend_unpaid_days' => $data['auto_suspend_unpaid_days'],
            'payment_instructions' => $data['payment_instructions'] ?? '',
            'default_billing_model' => $data['default_billing_model'],
            'default_profit_share_percent' => $data['default_profit_share_percent'],
            'default_profit_share_basis' => $data['default_profit_share_basis'],
        ]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated subscription policy settings');

        return redirect()->route('admin.settings.index', ['tab' => 'subscription'])->with('success', 'Subscription policy saved.');
    }

    public function updateMail(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|in:tls,ssl,',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ]);

        $this->settings->update([
            'mail_host' => $data['mail_host'] ?? '',
            'mail_port' => $data['mail_port'] ?? 587,
            'mail_username' => $data['mail_username'] ?? '',
            'mail_password' => $data['mail_password'] ?? '',
            'mail_encryption' => $data['mail_encryption'] ?? 'tls',
            'mail_from_address' => $data['mail_from_address'] ?? '',
            'mail_from_name' => $data['mail_from_name'] ?? '',
        ]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated mail (SMTP) settings');

        return redirect()->route('admin.settings.index', ['tab' => 'mail'])->with('success', 'Mail settings saved.');
    }

    public function updateSecurity(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:500',
            'admin_ip_allowlist' => 'nullable|string|max:2000',
            'admin_notification_email' => 'nullable|email|max:255',
            'admin_notification_phone' => 'nullable|string|max:30',
            'audit_log_retention_days' => 'required|integer|min:30|max:3650',
            'sms_enabled' => 'nullable|boolean',
            'sms_registration_verification' => 'nullable|boolean',
            'sms_registration_approved' => 'nullable|boolean',
            'sms_registration_rejected' => 'nullable|boolean',
            'sms_password_reset' => 'nullable|boolean',
            'sms_account_suspended' => 'nullable|boolean',
            'sms_account_reactivated' => 'nullable|boolean',
            'sms_auto_suspend' => 'nullable|boolean',
            'sms_invoice_issued' => 'nullable|boolean',
            'sms_payment_confirmed' => 'nullable|boolean',
            'sms_ticket_new_admin' => 'nullable|boolean',
            'sms_ticket_reply_business' => 'nullable|boolean',
            'sms_staff_welcome' => 'nullable|boolean',
            'sms_demo_lead_admin' => 'nullable|boolean',
            'email_enabled' => 'nullable|boolean',
            'email_registration_verification' => 'nullable|boolean',
            'email_registration_approved' => 'nullable|boolean',
            'email_registration_rejected' => 'nullable|boolean',
            'email_password_reset' => 'nullable|boolean',
            'email_account_suspended' => 'nullable|boolean',
            'email_account_reactivated' => 'nullable|boolean',
            'email_auto_suspend' => 'nullable|boolean',
            'email_invoice_issued' => 'nullable|boolean',
            'email_payment_confirmed' => 'nullable|boolean',
            'email_ticket_new_admin' => 'nullable|boolean',
            'email_ticket_reply_business' => 'nullable|boolean',
            'email_staff_welcome' => 'nullable|boolean',
            'email_demo_lead_admin' => 'nullable|boolean',
        ]);

        $this->settings->update([
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'maintenance_message' => $data['maintenance_message'] ?? $this->settings->get('maintenance_message'),
            'admin_ip_allowlist' => $data['admin_ip_allowlist'] ?? '',
            'admin_notification_email' => $data['admin_notification_email'] ?? '',
            'admin_notification_phone' => $data['admin_notification_phone'] ?? '',
            'audit_log_retention_days' => $data['audit_log_retention_days'],
            'sms_enabled' => $request->boolean('sms_enabled'),
            'sms_registration_verification' => $request->boolean('sms_registration_verification'),
            'sms_registration_approved' => $request->boolean('sms_registration_approved'),
            'sms_registration_rejected' => $request->boolean('sms_registration_rejected'),
            'sms_password_reset' => $request->boolean('sms_password_reset'),
            'sms_account_suspended' => $request->boolean('sms_account_suspended'),
            'sms_account_reactivated' => $request->boolean('sms_account_reactivated'),
            'sms_auto_suspend' => $request->boolean('sms_auto_suspend'),
            'sms_invoice_issued' => $request->boolean('sms_invoice_issued'),
            'sms_payment_confirmed' => $request->boolean('sms_payment_confirmed'),
            'sms_ticket_new_admin' => $request->boolean('sms_ticket_new_admin'),
            'sms_ticket_reply_business' => $request->boolean('sms_ticket_reply_business'),
            'sms_staff_welcome' => $request->boolean('sms_staff_welcome'),
            'sms_demo_lead_admin' => $request->boolean('sms_demo_lead_admin'),
            'email_enabled' => $request->boolean('email_enabled'),
            'email_registration_verification' => $request->boolean('email_registration_verification'),
            'email_registration_approved' => $request->boolean('email_registration_approved'),
            'email_registration_rejected' => $request->boolean('email_registration_rejected'),
            'email_password_reset' => $request->boolean('email_password_reset'),
            'email_account_suspended' => $request->boolean('email_account_suspended'),
            'email_account_reactivated' => $request->boolean('email_account_reactivated'),
            'email_auto_suspend' => $request->boolean('email_auto_suspend'),
            'email_invoice_issued' => $request->boolean('email_invoice_issued'),
            'email_payment_confirmed' => $request->boolean('email_payment_confirmed'),
            'email_ticket_new_admin' => $request->boolean('email_ticket_new_admin'),
            'email_ticket_reply_business' => $request->boolean('email_ticket_reply_business'),
            'email_staff_welcome' => $request->boolean('email_staff_welcome'),
            'email_demo_lead_admin' => $request->boolean('email_demo_lead_admin'),
        ]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated security settings (maintenance mode)');

        return redirect()->route('admin.settings.index', ['tab' => 'security'])->with('success', 'Security settings saved.');
    }

    public function updatePassword(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->route('admin.settings.index', ['tab' => 'security'])
                ->with('error', 'Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Super admin changed account password');

        return redirect()->route('admin.settings.index', ['tab' => 'security'])->with('success', 'Your password was updated.');
    }
}
