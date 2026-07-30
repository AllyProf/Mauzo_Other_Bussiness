<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessSettingsApiService
{
    /**
     * @return array<string, mixed>
     */
    public function index(Business $business): array
    {
        $business->loadMissing('plan');
        $automation = $business->automationSettings();
        $billing = app(PlatformBillingService::class)->subscriptionOverview($business);

        return [
            'profile' => [
                'name' => $business->name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'tin_number' => $business->tin_number,
                'contact_person' => $business->contact_person,
                'vat_number' => $business->vat_number,
                'vat_rate' => $business->vat_rate !== null ? (float) $business->vat_rate : null,
                'invoice_show_vat' => (bool) $business->invoice_show_vat,
                'invoice_vat_inclusive' => (bool) $business->invoice_vat_inclusive,
                'logo_url' => $business->logoUrl(),
                'has_logo' => filled($business->logo_path),
            ],
            'finance' => [
                'expense_deduct_from' => $business->expense_deduct_from ?? 'circulation',
                'circulation_balance' => (float) ($business->circulation_balance ?? 0),
            ],
            'payment_methods' => $this->formatPaymentMethods($business->paymentMethodsConfig()),
            'automation' => $this->formatAutomation($business, $automation),
            'shift_rules' => $this->formatShiftRules($automation),
            'subscription' => $this->formatSubscription($business, $billing),
            'meta' => [
                'plan_features' => [
                    'automation_reminders' => $business->hasPlanFeature('automation_reminders'),
                ],
                'sms_template_defaults' => Business::defaultSmsTemplates(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProfile(Business $business, array $payload, ?UploadedFile $logo = null): array
    {
        $validated = validator($payload, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:businesses,email,'.$business->id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tin_number' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'invoice_show_vat' => 'nullable|boolean',
            'invoice_vat_inclusive' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ])->validate();

        $data = collect($validated)->only([
            'name', 'email', 'phone', 'address', 'tin_number', 'contact_person', 'vat_number',
        ])->all();

        $data['invoice_show_vat'] = filter_var($payload['invoice_show_vat'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['invoice_vat_inclusive'] = filter_var($payload['invoice_vat_inclusive'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['vat_rate'] = filled($validated['vat_rate'] ?? null) ? $validated['vat_rate'] : null;

        if (filter_var($payload['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN) && $business->logo_path) {
            Storage::disk('public')->delete($business->logo_path);
            $data['logo_path'] = null;
        }

        $uploaded = $logo ?? ($payload['logo'] ?? null);
        if ($uploaded instanceof UploadedFile) {
            if ($business->logo_path) {
                Storage::disk('public')->delete($business->logo_path);
            }
            $data['logo_path'] = $uploaded->store('business-logos', 'public');
        }

        $business->update($data);

        return ['profile' => $this->index($business->fresh())['profile']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateFinance(Business $business, array $payload): array
    {
        $validated = validator($payload, [
            'expense_deduct_from' => 'required|in:circulation,profit',
            'circulation_balance' => 'nullable|numeric|min:0',
        ])->validate();

        $business->update([
            'expense_deduct_from' => $validated['expense_deduct_from'],
            'circulation_balance' => $validated['circulation_balance'] ?? $business->circulation_balance,
        ]);

        return ['finance' => $this->index($business->fresh())['finance']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAutomation(Business $business, array $payload): array
    {
        if (! $business->hasPlanFeature('automation_reminders')) {
            throw ValidationException::withMessages([
                'plan' => 'Automated reminders are not included in your current plan.',
            ]);
        }

        $templateRules = collect(Business::defaultSmsTemplates())->mapWithKeys(
            fn ($default, $key) => [$key => 'required|string|max:480']
        )->all();

        $validated = validator($payload, array_merge([
            'debt_due_reminder_days' => 'required|integer|min:1|max:30',
            'debt_due_reminder_days_second' => 'nullable|required_if:debt_reminder_frequency,twice|integer|min:1|max:29|lt:debt_due_reminder_days',
            'debt_reminder_send_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'debt_reminder_frequency' => 'required|in:once,twice',
            'default_debt_due_days' => 'required|integer|min:1|max:365',
            'low_stock_threshold' => 'required|integer|min:0|max:1000',
            'sms_report_send_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'sms_weekly_report_day' => 'required|integer|between:0,6',
            'email_sales_report_send_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'email_sales_report_weekly_day' => 'required|integer|between:0,6',
            'email_sales_report_monthly_day' => 'required|integer|min:1|max:28',
            'email_sales_report_recipients' => 'nullable|string|max:2000',
        ], $templateRules))->validate();

        $reportEmails = app(BusinessSalesReportEmailService::class)
            ->parseRecipientEmails((string) ($payload['email_sales_report_recipients'] ?? ''));

        if (filter_var($payload['email_sales_report_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && $reportEmails === []
            && ! filled($business->email)
            && ! filled($business->resolveOwner()?->email)) {
            throw ValidationException::withMessages([
                'email_sales_report_recipients' => 'Add at least one recipient email for sales report emails, or set a business/owner email.',
            ]);
        }

        $smsTemplates = collect(Business::defaultSmsTemplates())
            ->mapWithKeys(fn ($default, $key) => [$key => trim((string) ($payload[$key] ?? $default))])
            ->all();

        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                $this->automationPayloadFromRequest($payload, $validated, $reportEmails, $smsTemplates)
            ),
        ]);

        return ['automation' => $this->index($business->fresh())['automation']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateShiftRules(Business $business, array $payload): array
    {
        $validated = validator($payload, [
            'shift_open_mode' => 'required|in:anytime,scheduled',
            'shift_open_time_from' => 'required_if:shift_open_mode,scheduled|nullable|date_format:H:i',
            'shift_open_time_to' => 'required_if:shift_open_mode,scheduled|nullable|date_format:H:i',
            'shift_open_days' => 'nullable|array',
            'shift_open_days.*' => 'integer|between:0,6',
            'shift_max_open_duration' => 'required|integer|min:1|max:365',
            'shift_max_open_unit' => 'required|in:days,weeks',
            'shift_enforce_max_duration' => 'nullable|boolean',
        ])->validate();

        if ($validated['shift_open_mode'] === 'scheduled') {
            $days = array_map('intval', $payload['shift_open_days'] ?? []);
            if ($days === []) {
                throw ValidationException::withMessages([
                    'shift_open_days' => 'Select at least one day when shift opening is allowed.',
                ]);
            }
        }

        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                [
                    'shift_open_mode' => $validated['shift_open_mode'],
                    'shift_open_time_from' => $validated['shift_open_time_from'] ?? '06:00',
                    'shift_open_time_to' => $validated['shift_open_time_to'] ?? '22:00',
                    'shift_open_days' => $validated['shift_open_mode'] === 'scheduled'
                        ? array_values(array_unique(array_map('intval', $payload['shift_open_days'] ?? [])))
                        : [0, 1, 2, 3, 4, 5, 6],
                    'shift_max_open_duration' => (int) $validated['shift_max_open_duration'],
                    'shift_max_open_unit' => $validated['shift_max_open_unit'],
                    'shift_enforce_max_duration' => filter_var($payload['shift_enforce_max_duration'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]
            ),
        ]);

        return ['shift_rules' => $this->index($business->fresh())['shift_rules']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePaymentMethods(Business $business, array $payload): array
    {
        $defaults = collect(Business::defaultPaymentMethods())->keyBy('key');
        $keys = $defaults->keys()->all();

        validator($payload, [
            'methods' => 'required|array',
            'methods.*.key' => ['required', 'string', Rule::in($keys)],
            'methods.*.label' => 'required|string|max:100',
            'methods.*.enabled' => 'nullable|boolean',
            'methods.*.accounts' => 'nullable|array',
            'methods.*.accounts.*.name' => 'nullable|string|max:100',
            'methods.*.accounts.*.pay_number' => 'nullable|string|max:100',
            'methods.*.accounts.*.account_name' => 'nullable|string|max:255',
        ])->validate();

        $methods = [];
        $enabledCount = 0;
        $inputMethods = collect($payload['methods'])->keyBy('key');

        foreach ($keys as $key) {
            $input = $inputMethods->get($key, []);
            $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
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
            throw ValidationException::withMessages([
                'methods' => 'At least one payment method must be enabled.',
            ]);
        }

        $business->update(['payment_methods' => $methods]);

        return [
            'payment_methods' => $this->formatPaymentMethods($business->fresh()->paymentMethodsConfig()),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @return list<array<string, mixed>>
     */
    private function formatPaymentMethods(array $methods): array
    {
        return collect($methods)->map(fn (array $method) => [
            'key' => $method['key'],
            'label' => $method['label'],
            'enabled' => (bool) ($method['enabled'] ?? false),
            'type' => $method['type'] ?? 'immediate',
            'requires_reference' => (bool) ($method['requires_reference'] ?? false),
            'accounts' => collect($method['provider_accounts'] ?? [])
                ->map(fn ($account) => [
                    'name' => $account['name'] ?? '',
                    'pay_number' => $account['pay_number'] ?? '',
                    'account_name' => $account['account_name'] ?? '',
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $automation
     * @return array<string, mixed>
     */
    private function formatAutomation(Business $business, array $automation): array
    {
        return [
            'enabled' => $business->hasPlanFeature('automation_reminders'),
            'settings' => $automation,
            'sms_templates' => collect(Business::defaultSmsTemplates())
                ->mapWithKeys(fn ($default, $key) => [$key => $automation[$key] ?? $default])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $automation
     * @return array<string, mixed>
     */
    private function formatShiftRules(array $automation): array
    {
        return [
            'shift_open_mode' => $automation['shift_open_mode'] ?? 'anytime',
            'shift_open_time_from' => $automation['shift_open_time_from'] ?? '06:00',
            'shift_open_time_to' => $automation['shift_open_time_to'] ?? '22:00',
            'shift_open_days' => array_values($automation['shift_open_days'] ?? [0, 1, 2, 3, 4, 5, 6]),
            'shift_max_open_duration' => (int) ($automation['shift_max_open_duration'] ?? 1),
            'shift_max_open_unit' => $automation['shift_max_open_unit'] ?? 'days',
            'shift_enforce_max_duration' => (bool) ($automation['shift_enforce_max_duration'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $billing
     * @return array<string, mixed>
     */
    private function formatSubscription(Business $business, array $billing): array
    {
        $plan = $billing['plan'] ?? $business->plan;

        return [
            'account_status' => $business->statusLabel(),
            'is_active' => (bool) $business->is_active,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'max_users' => $plan->max_users,
                'max_business_types' => $plan->max_business_types,
                'max_branches' => $plan->max_branches,
            ] : null,
            'current_fee' => $billing['current_fee'] ?? null,
            'renewal_fee' => $billing['renewal_fee'] ?? null,
            'limits' => [
                'staff_limit' => $plan->max_users ?? null,
                'business_types_limit' => ($plan->max_business_types ?? 1) === 0 ? null : ($plan->max_business_types ?? 1),
                'business_types_used' => $business->categoryBusinessTypesUsed(),
                'branch_limit' => $business->branchesLimitLabel(),
                'branches_registered' => $business->branches()->count(),
            ],
            'expiry_date' => $business->expiry_date,
            'invoices' => collect($billing['invoices'] ?? [])->map(fn (PlatformBillingInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'billing_month' => $invoice->billing_month?->toDateString(),
                'billing_month_label' => $invoice->billing_month?->format('M Y'),
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $reportEmails
     * @param  array<string, string>  $smsTemplates
     * @return array<string, mixed>
     */
    private function automationPayloadFromRequest(
        array $payload,
        array $validated,
        array $reportEmails,
        array $smsTemplates
    ): array {
        $bool = fn (string $key, bool $default = false) => filter_var($payload[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);

        return [
            'notify_debt_overdue' => $bool('notify_debt_overdue', true),
            'notify_debt_due_soon' => $bool('notify_debt_due_soon', true),
            'debt_due_reminder_days' => (int) $validated['debt_due_reminder_days'],
            'debt_due_reminder_days_second' => $validated['debt_reminder_frequency'] === 'twice'
                ? (int) $validated['debt_due_reminder_days_second']
                : 1,
            'debt_reminder_send_time' => (string) $validated['debt_reminder_send_time'],
            'debt_reminder_frequency' => (string) $validated['debt_reminder_frequency'],
            'default_debt_due_days' => (int) $validated['default_debt_due_days'],
            'notify_low_stock' => $bool('notify_low_stock', true),
            'low_stock_threshold' => (int) $validated['low_stock_threshold'],
            'notify_pending_handover' => $bool('notify_pending_handover', true),
            'notify_finalize_daily_report' => $bool('notify_finalize_daily_report', true),
            'notify_unclosed_shifts' => $bool('notify_unclosed_shifts', true),
            'notify_opening_stock_shortages' => $bool('notify_opening_stock_shortages', true),
            'sms_staff_enabled' => $bool('sms_staff_enabled', true),
            'sms_staff_welcome' => $bool('sms_staff_welcome', true),
            'sms_staff_password_reset' => $bool('sms_staff_password_reset', true),
            'sms_staff_activated' => $bool('sms_staff_activated', true),
            'sms_staff_deactivated' => $bool('sms_staff_deactivated', true),
            'sms_staff_handover_submitted_owner' => $bool('sms_staff_handover_submitted_owner', true),
            'sms_staff_handover_submitted_manager' => $bool('sms_staff_handover_submitted_manager', true),
            'sms_staff_handover_verified_staff' => $bool('sms_staff_handover_verified_staff', true),
            'sms_staff_stock_received_owner' => $bool('sms_staff_stock_received_owner', true),
            'sms_staff_stock_received_manager' => $bool('sms_staff_stock_received_manager', true),
            'sms_staff_note_reminder' => $bool('sms_staff_note_reminder', true),
            'sms_debt_enabled' => $bool('sms_debt_enabled', true),
            'sms_debt_due_soon_customer' => $bool('sms_debt_due_soon_customer', true),
            'sms_debt_due_soon_staff' => $bool('sms_debt_due_soon_staff', true),
            'sms_debt_due_today_customer' => $bool('sms_debt_due_today_customer', true),
            'sms_debt_due_today_staff' => $bool('sms_debt_due_today_staff', true),
            'sms_debt_overdue_customer' => $bool('sms_debt_overdue_customer', true),
            'sms_debt_overdue_staff' => $bool('sms_debt_overdue_staff', true),
            'sms_daily_report_enabled' => $bool('sms_daily_report_enabled'),
            'sms_weekly_report_enabled' => $bool('sms_weekly_report_enabled'),
            'sms_branch_compare_weekly_enabled' => $bool('sms_branch_compare_weekly_enabled'),
            'sms_receiving_report_daily_enabled' => $bool('sms_receiving_report_daily_enabled'),
            'sms_report_send_time' => (string) $validated['sms_report_send_time'],
            'sms_weekly_report_day' => (int) $validated['sms_weekly_report_day'],
            'email_sales_report_enabled' => $bool('email_sales_report_enabled'),
            'email_sales_report_on_shift_close' => $bool('email_sales_report_on_shift_close', true),
            'email_sales_report_daily' => $bool('email_sales_report_daily'),
            'email_sales_report_weekly' => $bool('email_sales_report_weekly'),
            'email_sales_report_monthly' => $bool('email_sales_report_monthly'),
            'email_sales_report_send_time' => (string) $validated['email_sales_report_send_time'],
            'email_sales_report_weekly_day' => (int) $validated['email_sales_report_weekly_day'],
            'email_sales_report_monthly_day' => (int) $validated['email_sales_report_monthly_day'],
            'email_sales_report_recipients' => implode(', ', $reportEmails),
            'email_sales_report_skip_empty' => $bool('email_sales_report_skip_empty', true),
            'email_sales_report_manager_digest' => $bool('email_sales_report_manager_digest', true),
            'email_staff_enabled' => $bool('email_staff_enabled', true),
            'email_debt_enabled' => $bool('email_debt_enabled', true),
            ...$smsTemplates,
        ];
    }
}
