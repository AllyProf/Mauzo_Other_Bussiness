<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    public function __construct(private PlatformSettingsService $settings)
    {
    }

    public function index()
    {
        $this->ensureSuperAdmin();

        $settings = $this->settings->all();
        $plans = Plan::orderBy('price')->get();

        return view('admin.settings.index', compact('settings', 'plans'));
    }

    public function updateProfile(Request $request)
    {
        $this->ensureSuperAdmin();

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
        $this->ensureSuperAdmin();

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
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'grace_period_days' => 'required|integer|min:0|max:90',
            'expiry_warning_days' => 'required|integer|min:1|max:90',
            'auto_suspend_on_expiry' => 'nullable|boolean',
            'auto_email_billing_invoices' => 'nullable|boolean',
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
        $this->ensureSuperAdmin();

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
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:500',
        ]);

        $this->settings->update([
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'maintenance_message' => $data['maintenance_message'] ?? $this->settings->get('maintenance_message'),
        ]);

        AuditLog::log('UPDATE_PLATFORM_SETTINGS', 'Updated security settings (maintenance mode)');

        return redirect()->route('admin.settings.index', ['tab' => 'security'])->with('success', 'Security settings saved.');
    }

    public function updatePassword(Request $request)
    {
        $this->ensureSuperAdmin();

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

    private function ensureSuperAdmin(): void
    {
        if (Auth::user()?->role !== 'super_admin') {
            abort(403);
        }
    }
}
