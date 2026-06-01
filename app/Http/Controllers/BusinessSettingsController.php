<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\BusinessSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessSettingsController extends Controller
{
    public function __construct(private BusinessSettingsService $settingsService)
    {
    }

    private function authorizeSettingsAccess(): void
    {
        $this->authorizeAny(['manage_business_settings', 'manage_payment_methods']);
    }

    public function index()
    {
        $this->authorizeSettingsAccess();

        $business = Auth::user()->business->load('plan');
        $automation = $business->automationSettings();
        $paymentMethods = $business->paymentMethodsConfig();
        $billingOverview = app(\App\Services\PlatformBillingService::class)->subscriptionOverview($business);

        return view('settings.index', compact('business', 'automation', 'paymentMethods', 'billingOverview'));
    }

    public function updateProfile(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $business = Auth::user()->business;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:businesses,email,'.$business->id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tin_number' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
        ]);

        $business->update($request->only([
            'name',
            'email',
            'phone',
            'address',
            'tin_number',
            'contact_person',
        ]));

        return redirect()->route('settings.index', ['tab' => 'profile'])
            ->with('success', 'Business profile updated successfully.');
    }

    public function updateFinance(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $request->validate([
            'expense_deduct_from' => 'required|in:circulation,profit',
            'circulation_balance' => 'nullable|numeric|min:0',
        ]);

        $business = Auth::user()->business;

        $business->update([
            'expense_deduct_from' => $request->expense_deduct_from,
            'circulation_balance' => $request->circulation_balance ?? $business->circulation_balance,
        ]);

        return redirect()->route('settings.index', ['tab' => 'finance'])
            ->with('success', 'Finance settings saved successfully.');
    }

    public function updateAutomation(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $request->validate([
            'debt_due_reminder_days' => 'required|integer|min:1|max:30',
            'default_debt_due_days' => 'required|integer|min:1|max:365',
            'low_stock_threshold' => 'required|integer|min:0|max:1000',
        ]);

        $business = Auth::user()->business;

        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                [
                    'notify_debt_overdue' => $request->boolean('notify_debt_overdue'),
                    'notify_debt_due_soon' => $request->boolean('notify_debt_due_soon'),
                    'debt_due_reminder_days' => (int) $request->debt_due_reminder_days,
                    'default_debt_due_days' => (int) $request->default_debt_due_days,
                    'notify_low_stock' => $request->boolean('notify_low_stock'),
                    'low_stock_threshold' => (int) $request->low_stock_threshold,
                    'notify_pending_handover' => $request->boolean('notify_pending_handover'),
                    'notify_finalize_daily_report' => $request->boolean('notify_finalize_daily_report'),
                    'notify_unclosed_shifts' => $request->boolean('notify_unclosed_shifts'),
                    'notify_opening_stock_shortages' => $request->boolean('notify_opening_stock_shortages'),
                ]
            ),
        ]);

        return redirect()->route('settings.index', ['tab' => 'automation'])
            ->with('success', 'Automation and notification settings saved.');
    }

    public function updateShiftRules(Request $request)
    {
        $this->authorizeAny(['manage_business_settings']);

        $request->validate([
            'shift_open_mode' => 'required|in:anytime,scheduled',
            'shift_open_time_from' => 'required_if:shift_open_mode,scheduled|nullable|date_format:H:i',
            'shift_open_time_to' => 'required_if:shift_open_mode,scheduled|nullable|date_format:H:i',
            'shift_open_days' => 'nullable|array',
            'shift_open_days.*' => 'integer|between:0,6',
            'shift_max_open_duration' => 'required|integer|min:1|max:365',
            'shift_max_open_unit' => 'required|in:days,weeks',
        ]);

        if ($request->shift_open_mode === 'scheduled') {
            $days = array_map('intval', $request->input('shift_open_days', []));
            if ($days === []) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Select at least one day when shift opening is allowed.');
            }
        }

        $business = Auth::user()->business;

        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                [
                    'shift_open_mode' => $request->shift_open_mode,
                    'shift_open_time_from' => $request->shift_open_time_from ?? '06:00',
                    'shift_open_time_to' => $request->shift_open_time_to ?? '22:00',
                    'shift_open_days' => $request->shift_open_mode === 'scheduled'
                        ? array_values(array_unique(array_map('intval', $request->input('shift_open_days', []))))
                        : [0, 1, 2, 3, 4, 5, 6],
                    'shift_max_open_duration' => (int) $request->shift_max_open_duration,
                    'shift_max_open_unit' => $request->shift_max_open_unit,
                    'shift_enforce_max_duration' => $request->boolean('shift_enforce_max_duration'),
                ]
            ),
        ]);

        return redirect()->route('settings.index', ['tab' => 'shifts'])
            ->with('success', 'Sales shift rules saved successfully.');
    }

    public function updatePaymentMethods(Request $request)
    {
        $this->authorizeAny(['manage_payment_methods', 'manage_business_settings']);
        $keys = $defaults->keys()->all();

        $request->validate([
            'methods' => 'required|array',
            'methods.*.label' => 'required|string|max:100',
            'methods.*.accounts' => 'nullable|array',
            'methods.*.accounts.*.name' => 'nullable|string|max:100',
            'methods.*.accounts.*.pay_number' => 'nullable|string|max:100',
            'methods.*.accounts.*.account_name' => 'nullable|string|max:255',
        ]);

        $methods = [];
        $enabledCount = 0;

        foreach ($keys as $key) {
            $input = $request->input("methods.{$key}", []);
            $enabled = $request->boolean("methods.{$key}.enabled");
            $providerAccounts = collect($input['accounts'] ?? [])
                ->map(fn ($account) => [
                    'name' => trim($account['name'] ?? ''),
                    'pay_number' => trim($account['pay_number'] ?? ''),
                    'account_name' => trim($account['account_name'] ?? ''),
                ])
                ->filter(fn ($account) => $account['name'] !== '')
                ->values()
                ->all();

            if ($enabled) {
                $enabledCount++;
            }

            $methods[] = [
                'key' => $key,
                'label' => trim($input['label'] ?? $defaults[$key]['label']),
                'enabled' => $enabled,
                'type' => $defaults[$key]['type'],
                'requires_reference' => $defaults[$key]['requires_reference'],
                'provider_accounts' => $providerAccounts,
                'providers' => array_column($providerAccounts, 'name'),
            ];
        }

        if ($enabledCount === 0) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'At least one payment method must be enabled.');
        }

        Auth::user()->business->update(['payment_methods' => $methods]);

        return redirect()->route('settings.index', ['tab' => 'payments'])
            ->with('success', 'Payment methods saved successfully.');
    }
}
