<?php

namespace App\Models;

use App\Concerns\ManagesServiceBusinessTypes;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use ManagesServiceBusinessTypes;

    public const OPERATION_RETAIL = 'retail';

    public const OPERATION_SERVICES = 'services';

    public const OPERATION_BOTH = 'both';

    protected $fillable = [
        'owner_user_id',
        'name',
        'address',
        'region',
        'district',
        'phone',
        'email',
        'plan_id',
        'billing_model',
        'billing_price',
        'profit_share_percent',
        'profit_share_basis',
        'minimum_monthly_fee',
        'expiry_date',
        'tin_number',
        'contact_person',
        'logo_path',
        'vat_number',
        'vat_rate',
        'invoice_show_vat',
        'invoice_vat_inclusive',
        'is_active',
        'pending_approval',
        'expense_deduct_from',
        'circulation_balance',
        'automation_settings',
        'payment_methods',
        'category_business_types',
        'service_business_types',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'pending_approval' => 'boolean',
        'billing_price' => 'decimal:2',
        'profit_share_percent' => 'decimal:2',
        'minimum_monthly_fee' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'invoice_show_vat' => 'boolean',
        'invoice_vat_inclusive' => 'boolean',
        'automation_settings' => 'array',
        'payment_methods' => 'array',
        'category_business_types' => 'array',
        'service_business_types' => 'array',
    ];

    public static function defaultPaymentMethods(): array
    {
        return [
            [
                'key' => 'cash',
                'label' => 'Cash',
                'enabled' => true,
                'type' => 'immediate',
                'requires_reference' => false,
                'providers' => [],
            ],
            [
                'key' => 'mobile_money',
                'label' => 'Mobile Money',
                'enabled' => true,
                'type' => 'immediate',
                'requires_reference' => true,
                'provider_accounts' => [
                    ['name' => 'M-Pesa', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'Tigo Pesa', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'Airtel Money', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'Halopesa', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'T-Pesa', 'pay_number' => '', 'account_name' => ''],
                ],
            ],
            [
                'key' => 'bank',
                'label' => 'Bank Transfer',
                'enabled' => true,
                'type' => 'immediate',
                'requires_reference' => true,
                'provider_accounts' => [
                    ['name' => 'CRDB', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'NMB', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'KCB', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'Equity', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'NBC', 'pay_number' => '', 'account_name' => ''],
                    ['name' => 'DTB', 'pay_number' => '', 'account_name' => ''],
                ],
            ],
            [
                'key' => 'debt',
                'label' => 'Pay Later (Credit)',
                'enabled' => true,
                'type' => 'credit',
                'requires_reference' => false,
                'providers' => [],
            ],
        ];
    }

    public function paymentMethodsConfig(): array
    {
        $defaults = collect(self::defaultPaymentMethods())->keyBy('key');
        $stored = collect($this->payment_methods ?? [])->keyBy('key');

        return $defaults->map(function ($default, $key) use ($stored) {
            $saved = $stored->get($key, []);
            $providerAccounts = self::normalizeProviderAccounts(
                $saved['provider_accounts'] ?? null,
                $default['provider_accounts'] ?? [],
                $saved['providers'] ?? null,
                $saved['receive_number'] ?? '',
                $saved['receive_name'] ?? ''
            );

            return array_merge($default, [
                'enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : $default['enabled'],
                'label' => $saved['label'] ?? $default['label'],
                'provider_accounts' => $providerAccounts,
                'providers' => array_column($providerAccounts, 'name'),
            ]);
        })->values()->all();
    }

    public static function normalizeProviderAccounts(
        ?array $savedAccounts,
        array $defaultAccounts,
        ?array $legacyProviders = null,
        string $legacyPayNumber = '',
        string $legacyAccountName = ''
    ): array {
        if (! empty($savedAccounts)) {
            $saved = collect($savedAccounts)
                ->map(fn ($account) => [
                    'name' => trim($account['name'] ?? ''),
                    'pay_number' => trim($account['pay_number'] ?? ''),
                    'account_name' => trim($account['account_name'] ?? ''),
                ])
                ->filter(fn ($account) => $account['name'] !== '')
                ->values()
                ->all();

            return self::mergeProviderAccountLists($defaultAccounts, $saved);
        }

        if (! empty($legacyProviders)) {
            return collect($legacyProviders)
                ->values()
                ->map(fn ($name, $index) => [
                    'name' => trim(is_string($name) ? $name : ($name['name'] ?? '')),
                    'pay_number' => $index === 0 ? trim($legacyPayNumber) : '',
                    'account_name' => $index === 0 ? trim($legacyAccountName) : '',
                ])
                ->filter(fn ($account) => $account['name'] !== '')
                ->values()
                ->all();
        }

        if ($legacyPayNumber !== '' || $legacyAccountName !== '') {
            $defaults = ! empty($defaultAccounts) ? $defaultAccounts : [['name' => 'Default', 'pay_number' => '', 'account_name' => '']];

            return collect($defaults)
                ->values()
                ->map(fn ($account, $index) => [
                    'name' => trim($account['name'] ?? ''),
                    'pay_number' => $index === 0 ? trim($legacyPayNumber) : trim($account['pay_number'] ?? ''),
                    'account_name' => $index === 0 ? trim($legacyAccountName) : trim($account['account_name'] ?? ''),
                ])
                ->filter(fn ($account) => $account['name'] !== '')
                ->values()
                ->all();
        }

        return collect($defaultAccounts)
            ->map(fn ($account) => [
                'name' => trim($account['name'] ?? ''),
                'pay_number' => trim($account['pay_number'] ?? ''),
                'account_name' => trim($account['account_name'] ?? ''),
            ])
            ->filter(fn ($account) => $account['name'] !== '')
            ->values()
            ->all();
    }

    public static function mergeProviderAccountLists(array $defaults, array $saved): array
    {
        $merged = [];

        foreach ($defaults as $account) {
            $name = trim($account['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $merged[strtolower($name)] = [
                'name' => $name,
                'pay_number' => trim($account['pay_number'] ?? ''),
                'account_name' => trim($account['account_name'] ?? ''),
            ];
        }

        foreach ($saved as $account) {
            $name = trim($account['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            $merged[$key] = [
                'name' => $name,
                'pay_number' => trim($account['pay_number'] ?? ($merged[$key]['pay_number'] ?? '')),
                'account_name' => trim($account['account_name'] ?? ($merged[$key]['account_name'] ?? '')),
            ];
        }

        return array_values($merged);
    }

    public function findProviderAccount(string $methodKey, ?string $providerName): ?array
    {
        if (! $providerName) {
            return null;
        }

        $method = collect($this->paymentMethodsConfig())->firstWhere('key', $methodKey);

        if (! $method) {
            return null;
        }

        foreach ($method['provider_accounts'] ?? [] as $account) {
            if (strcasecmp($account['name'] ?? '', $providerName) === 0) {
                return $account;
            }
        }

        return null;
    }

    public function enabledPaymentMethods(): array
    {
        return array_values(array_filter(
            $this->paymentMethodsConfig(),
            fn ($method) => ! empty($method['enabled'])
        ));
    }

    public function enabledPaymentMethodKeys(): array
    {
        return array_column($this->enabledPaymentMethods(), 'key');
    }

    public function findPaymentMethod(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        foreach ($this->paymentMethodsConfig() as $method) {
            if (($method['key'] ?? '') === $key && ! empty($method['enabled'])) {
                return $method;
            }
        }

        return null;
    }

    public function paymentMethodLabel(?string $key): string
    {
        $method = collect($this->paymentMethodsConfig())->firstWhere('key', $key);

        if (! $method) {
            return ucfirst(str_replace('_', ' ', $key ?? ''));
        }

        return $method['label'] ?? $method['key'];
    }

    public static function defaultAutomationSettings(): array
    {
        return [
            'notify_debt_overdue' => true,
            'notify_debt_due_soon' => true,
            'debt_due_reminder_days' => 3,
            'debt_due_reminder_days_second' => 1,
            'debt_reminder_send_time' => '08:00',
            'debt_reminder_frequency' => 'once',
            'default_debt_due_days' => 30,
            'notify_low_stock' => true,
            'low_stock_threshold' => 5,
            'notify_pending_handover' => true,
            'notify_finalize_daily_report' => true,
            'notify_unclosed_shifts' => true,
            'notify_opening_stock_shortages' => true,
            'shift_open_mode' => 'anytime',
            'shift_open_time_from' => '06:00',
            'shift_open_time_to' => '22:00',
            'shift_open_days' => [0, 1, 2, 3, 4, 5, 6],
            'shift_max_open_duration' => 1,
            'shift_max_open_unit' => 'days',
            'shift_enforce_max_duration' => true,
            'sms_staff_enabled' => true,
            'sms_staff_welcome' => true,
            'sms_staff_password_reset' => true,
            'sms_staff_activated' => true,
            'sms_staff_deactivated' => true,
            'sms_staff_handover_submitted_owner' => true,
            'sms_staff_handover_verified_staff' => true,
            'sms_staff_stock_received_owner' => true,
            'sms_staff_stock_received_manager' => true,
            'sms_staff_note_reminder' => true,
            'sms_debt_enabled' => true,
            'sms_debt_due_soon_customer' => true,
            'sms_debt_due_soon_staff' => true,
            'sms_debt_due_today_customer' => true,
            'sms_debt_due_today_staff' => true,
            'sms_debt_overdue_customer' => true,
            'sms_debt_overdue_staff' => true,
            'email_staff_enabled' => true,
            'email_staff_welcome' => true,
            'email_staff_password_reset' => true,
            'email_staff_activated' => true,
            'email_staff_deactivated' => true,
            'email_staff_handover_submitted_owner' => true,
            'email_staff_handover_verified_staff' => true,
            'email_staff_note_reminder' => true,
            'email_debt_enabled' => true,
            'email_debt_due_soon_customer' => true,
            'email_debt_due_soon_staff' => true,
            'email_debt_due_today_customer' => true,
            'email_debt_due_today_staff' => true,
            'email_debt_overdue_customer' => true,
            'email_debt_overdue_staff' => true,
            'sms_invoice_created_enabled' => true,
            'email_invoice_created_enabled' => true,
            'sms_invoice_created_template' => '{business}: Dear {customer}, invoice {reference} for TZS {amount} dated {date} has been created. Please check your email for the attached invoice.',
            'email_invoice_created_subject' => '{business} — Invoice {reference}',
            'email_invoice_created_body' => 'Your invoice {reference} is attached. Open the PDF for the full invoice details.',
            ...self::defaultDebtSmsTemplates(),
            ...self::defaultStaffSmsTemplates(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultStaffSmsTemplates(): array
    {
        return [
            'sms_staff_template_welcome' => '{business}: Your staff account is ready. Email: {email}. Password: {password}',
            'sms_staff_template_password_reset' => '{business}: Your password was reset. Email: {email}. New password: {password}',
            'sms_staff_template_activated' => '{business}: Your account is active again. You can sign in with your email and password.',
            'sms_staff_template_deactivated' => '{business}: Your account has been deactivated. Contact your manager if you need access restored.',
            'sms_staff_template_handover_submitted_owner' => '{business}: {submitter} submitted daily reconciliation for {date}. Handover TZS {amount}. Please verify in Daily Reconciliation.',
            'sms_staff_template_handover_verified_staff' => '{business}: Your reconciliation for {date} was verified by {verifier}.{money_short_note}',
            'sms_staff_template_stock_received_owner' => '{business}: {receiver} received stock {reference} from {supplier} on {date}. {item_count} items ({total_pieces} pcs), cost TZS {total_cost}. {items_summary}',
            'sms_staff_template_stock_received_manager' => '{business}: Stock-in {reference} by {receiver} from {supplier} on {date}. {item_count} items ({total_pieces} pcs), cost TZS {total_cost}. {items_summary}',
            'sms_staff_template_note_reminder' => '{business} Reminder: {title} ({when}). {preview}',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function staffSmsTemplateLabels(): array
    {
        return [
            'sms_staff_template_welcome' => 'New employee welcome',
            'sms_staff_template_password_reset' => 'Password reset',
            'sms_staff_template_activated' => 'Account activated',
            'sms_staff_template_deactivated' => 'Account deactivated',
            'sms_staff_template_handover_submitted_owner' => 'Handover submitted (owner)',
            'sms_staff_template_handover_verified_staff' => 'Handover verified (staff)',
            'sms_staff_template_stock_received_owner' => 'Stock received (owner)',
            'sms_staff_template_stock_received_manager' => 'Stock received (manager)',
            'sms_staff_template_note_reminder' => 'Note reminder',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultSmsTemplates(): array
    {
        return array_merge(
            self::defaultDebtSmsTemplates(),
            self::defaultStaffSmsTemplates(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultDebtSmsTemplates(): array
    {
        return [
            'sms_debt_template_due_soon_customer' => '{business}: Dear {customer}, your balance of TZS {amount} on {reference} is due on {due_date}. Please pay on time.',
            'sms_debt_template_due_soon_2_customer' => '{business}: Final reminder — Dear {customer}, your balance of TZS {amount} on {reference} is due on {due_date}. Please pay soon.',
            'sms_debt_template_due_today_customer' => '{business}: Dear {customer}, your payment of TZS {amount} on {reference} is due TODAY ({due_date}). Please settle today.',
            'sms_debt_template_overdue_customer' => '{business}: Dear {customer}, your payment of TZS {amount} on {reference} was due {due_date}. Please pay as soon as possible.',
            'sms_debt_template_due_soon_staff' => '{business}: Debt reminder — {customer} owes TZS {amount} on {reference}, due {due_date}.',
            'sms_debt_template_due_soon_2_staff' => '{business}: Final debt reminder — {customer} owes TZS {amount} on {reference}, due {due_date}.',
            'sms_debt_template_due_today_staff' => '{business}: Debt due TODAY — {customer} owes TZS {amount} on {reference}. Please follow up.',
            'sms_debt_template_overdue_staff' => '{business}: Overdue debt — {customer} still owes TZS {amount} on {reference} (was due {due_date}). Follow up urgently.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function debtSmsTemplateLabels(): array
    {
        return [
            'sms_debt_template_due_soon_customer' => 'Due soon — customer (1st reminder)',
            'sms_debt_template_due_soon_2_customer' => 'Due soon — customer (2nd reminder)',
            'sms_debt_template_due_today_customer' => 'Due today — customer',
            'sms_debt_template_overdue_customer' => 'Overdue — customer',
            'sms_debt_template_due_soon_staff' => 'Due soon — staff (1st reminder)',
            'sms_debt_template_due_soon_2_staff' => 'Due soon — staff (2nd reminder)',
            'sms_debt_template_due_today_staff' => 'Due today — staff',
            'sms_debt_template_overdue_staff' => 'Overdue — staff',
        ];
    }

    public function automationSettings(): array
    {
        return array_merge(
            self::defaultAutomationSettings(),
            $this->automation_settings ?? []
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_business')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function resolveOwner(): ?User
    {
        if ($this->owner_user_id) {
            return $this->ownerUser;
        }

        return $this->users()->where('role', 'owner')->first();
    }

    /**
     * Active staff whose role name includes "manager" (e.g. Store Manager).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function resolveManagers(): \Illuminate\Support\Collection
    {
        return $this->users()
            ->where('is_active', true)
            ->where('role', '!=', 'owner')
            ->with('role_relation')
            ->get()
            ->filter(function (User $user) {
                return str_contains(strtolower($user->displayRoleName()), 'manager');
            })
            ->values();
    }

    public function isPendingApproval(): bool
    {
        return (bool) $this->pending_approval;
    }

    public function statusLabel(): string
    {
        if ($this->isPendingApproval()) {
            return 'Pending Approval';
        }

        if (! $this->is_active) {
            return 'Suspended';
        }

        if ($this->expiry_date && \Carbon\Carbon::parse($this->expiry_date)->isPast()) {
            return 'Expired';
        }

        return 'Active';
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function usesCustomBilling(): bool
    {
        return filled($this->billing_model);
    }

    public function effectiveBillingModel(): string
    {
        return $this->billing_model
            ?? $this->plan?->billing_model
            ?? Plan::BILLING_FIXED;
    }

    public function usesProfitShareBilling(): bool
    {
        return $this->effectiveBillingModel() === Plan::BILLING_PROFIT_SHARE;
    }

    public function effectiveBillingPrice(): float
    {
        if ($this->billing_price !== null) {
            return (float) $this->billing_price;
        }

        return (float) ($this->plan?->price ?? 0);
    }

    public function effectiveProfitSharePercent(): float
    {
        if ($this->profit_share_percent !== null) {
            return (float) $this->profit_share_percent;
        }

        return (float) ($this->plan?->profit_share_percent ?? 0);
    }

    public function effectiveProfitShareBasis(): string
    {
        return $this->profit_share_basis
            ?? $this->plan?->profit_share_basis
            ?? 'net_profit';
    }

    public function effectiveMinimumMonthlyFee(): float
    {
        if ($this->minimum_monthly_fee !== null) {
            return (float) $this->minimum_monthly_fee;
        }

        return (float) ($this->plan?->minimum_monthly_fee ?? 0);
    }

    public function billingModelLabel(): string
    {
        $label = $this->usesProfitShareBilling() ? 'Profit share' : 'Fixed fee';

        return $this->usesCustomBilling() ? $label.' (custom)' : $label;
    }

    public function billingSummary(): string
    {
        if ($this->usesProfitShareBilling()) {
            $basis = $this->effectiveProfitShareBasis() === 'gross_profit' ? 'gross' : 'net';

            return number_format($this->effectiveProfitSharePercent(), 1).'% of '.$basis.' profit';
        }

        $duration = max(1, (int) ($this->plan?->duration_months ?? 1));

        return 'TZS '.number_format($this->effectiveBillingPrice(), 0).' / '.$duration.' mo';
    }

    public function categoryBusinessTypesList(): array
    {
        return $this->category_business_types ?? [];
    }

    public function maxBusinessTypesAllowed(): ?int
    {
        $max = (int) ($this->plan?->max_business_types ?? 1);

        return $max === 0 ? null : $max;
    }

    public function categoryBusinessTypesUsed(): int
    {
        return count($this->categoryBusinessTypesList());
    }

    public function hasCategoryBusinessType(string $key): bool
    {
        foreach ($this->categoryBusinessTypesList() as $type) {
            if (($type['key'] ?? '') === $key) {
                return true;
            }
        }

        return false;
    }

    public function assertCanAddCategoryBusinessType(string $key): void
    {
        if ($this->hasCategoryBusinessType($key)) {
            return;
        }

        $max = $this->maxBusinessTypesAllowed();

        if ($max !== null && $this->categoryBusinessTypesUsed() >= $max) {
            $planName = $this->plan?->name ?? 'your plan';

            throw new \InvalidArgumentException(
                "Your {$planName} plan allows up to {$max} business type(s). Clear categories or upgrade your plan to add more."
            );
        }
    }

    public function registerCategoryBusinessType(string $key, string $label, array $categoryNames = []): void
    {
        $types = $this->categoryBusinessTypesList();

        foreach ($types as &$type) {
            if (($type['key'] ?? '') === $key) {
                $existing = $type['categories'] ?? [];
                $type['label'] = $label;
                $type['categories'] = array_values(array_unique(array_merge($existing, $categoryNames)));
                $this->update(['category_business_types' => $types]);

                return;
            }
        }
        unset($type);

        $types[] = [
            'key' => $key,
            'label' => $label,
            'categories' => array_values(array_unique($categoryNames)),
        ];

        $this->update(['category_business_types' => $types]);
    }

    public function clearCategoryBusinessTypes(): void
    {
        $this->update(['category_business_types' => null]);
    }

    public function importedTypesForBranch(int $branchId): array
    {
        $categories = Category::query()
            ->where('business_id', $this->id)
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['name', 'source_business_type_key']);

        return $this->importedTypesFromCategories($categories);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>|\Illuminate\Database\Eloquent\Collection<int, Category>  $categories
     * @return list<array{key: string, label: string, categories: list<string>}>
     */
    public function importedTypesFromCategories($categories): array
    {
        $templates = config('category_templates', []);
        $registered = collect($this->categoryBusinessTypesList())->keyBy(fn ($type) => (string) ($type['key'] ?? ''));

        return collect($categories)
            ->groupBy(fn (Category $category) => $category->source_business_type_key ?: 'other')
            ->filter(fn ($group, $key) => $key !== 'other' && $key !== '')
            ->map(function ($group, $key) use ($registered, $templates) {
                $registeredType = $registered->get((string) $key);

                return [
                    'key' => (string) $key,
                    'label' => (string) ($registeredType['label'] ?? $templates[$key]['label'] ?? ucfirst(str_replace('_', ' ', (string) $key))),
                    'categories' => $group->pluck('name')->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function syncCategoryBusinessTypesFromCategories(): void
    {
        $remainingCategories = Category::query()
            ->where('business_id', $this->id)
            ->orderBy('name')
            ->get(['name', 'source_business_type_key']);

        if ($remainingCategories->isEmpty()) {
            $this->clearCategoryBusinessTypes();

            return;
        }

        $templates = config('category_templates', []);
        $existingTypes = collect($this->categoryBusinessTypesList())->keyBy(fn ($type) => (string) ($type['key'] ?? ''));
        $types = [];

        foreach ($remainingCategories->groupBy(fn ($category) => $category->source_business_type_key ?: 'other') as $key => $categories) {
            if ($key === 'other' || $key === '') {
                continue;
            }

            $existing = $existingTypes->get($key);
            $label = (string) ($existing['label'] ?? $templates[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key)));

            $types[] = [
                'key' => $key,
                'label' => $label,
                'categories' => $categories->pluck('name')->unique()->values()->all(),
            ];
        }

        $this->update(['category_business_types' => empty($types) ? null : $types]);
    }

    public function businessTypesLimitLabel(): string
    {
        $max = $this->maxBusinessTypesAllowed();

        if ($max === null) {
            return 'Unlimited';
        }

        return (string) $max;
    }

    /**
     * Business types configured for this shop (for POS / invoice department filters).
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public function posBusinessTypesMeta(): array
    {
        $templates = config('category_templates', []);

        return collect($this->categoryBusinessTypesList())
            ->map(function ($type) use ($templates) {
                $key = (string) ($type['key'] ?? '');

                return [
                    'key' => $key,
                    'label' => (string) ($type['label'] ?? 'Business'),
                    'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Branch-scoped business type tabs (key, label, icon) for POS / reports filters.
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public function branchPosBusinessTypesMeta(int $branchId): array
    {
        $templates = config('category_templates', []);

        return collect($this->importedTypesForBranch($branchId))
            ->map(function ($type) use ($templates) {
                $key = (string) ($type['key'] ?? '');

                return [
                    'key' => $key,
                    'label' => (string) ($type['label'] ?? $key),
                    'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                ];
            })
            ->values()
            ->all();
    }

    public function businessTypeLabel(string $key): string
    {
        foreach ($this->categoryBusinessTypesList() as $type) {
            if (($type['key'] ?? '') === $key) {
                return (string) ($type['label'] ?? $key);
            }
        }

        return $key === 'other' ? 'Other' : $key;
    }

    public function maxBranchesAllowed(): ?int
    {
        $max = (int) ($this->plan?->max_branches ?? 1);

        return $max === 0 ? null : $max;
    }

    public function branchesLimitLabel(): string
    {
        $max = $this->maxBranchesAllowed();

        if ($max === null) {
            return 'Unlimited';
        }

        return (string) $max;
    }

    public function hasPlanFeature(string $key): bool
    {
        return app(\App\Services\PlanFeatureService::class)->businessHasFeature($this, $key);
    }

    public function operationMode(): string
    {
        $mode = $this->operation_mode ?: self::OPERATION_BOTH;

        return in_array($mode, [self::OPERATION_RETAIL, self::OPERATION_SERVICES, self::OPERATION_BOTH], true)
            ? $mode
            : self::OPERATION_BOTH;
    }

    public function isRetailEnabled(): bool
    {
        return in_array($this->operationMode(), [self::OPERATION_RETAIL, self::OPERATION_BOTH], true);
    }

    public function isServicesOperationEnabled(): bool
    {
        return in_array($this->operationMode(), [self::OPERATION_SERVICES, self::OPERATION_BOTH], true);
    }

    public function servicesMenuVisible(): bool
    {
        return $this->hasPlanFeature('services') && $this->isServicesOperationEnabled();
    }

    public function operationModeLabel(): string
    {
        return match ($this->operationMode()) {
            self::OPERATION_SERVICES => 'Services only',
            self::OPERATION_RETAIL => 'Retail / inventory only',
            default => 'Retail & services',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function operationModeOptions(): array
    {
        return [
            self::OPERATION_RETAIL => 'Retail / shop (inventory & store POS)',
            self::OPERATION_SERVICES => 'Services only (no inventory menus)',
            self::OPERATION_BOTH => 'Both retail and services',
        ];
    }

    public function logoUrl(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path);
    }

    public function invoiceLogoDataUri(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($this->logo_path));
    }

    /**
     * @return array{subtotal_excl: float, vat: float, total: float, rate: float, inclusive: bool}|null
     */
    public function invoiceVatBreakdown(float $totalAmount): ?array
    {
        if (! $this->invoice_show_vat || ! $this->vat_rate || (float) $this->vat_rate <= 0) {
            return null;
        }

        $rate = (float) $this->vat_rate;

        if ($this->invoice_vat_inclusive) {
            $vat = round($totalAmount * $rate / (100 + $rate), 2);
            $subtotalExcl = round($totalAmount - $vat, 2);

            return [
                'subtotal_excl' => $subtotalExcl,
                'vat' => $vat,
                'total' => round($totalAmount, 2),
                'rate' => $rate,
                'inclusive' => true,
            ];
        }

        $vat = round($totalAmount * $rate / 100, 2);

        return [
            'subtotal_excl' => round($totalAmount, 2),
            'vat' => $vat,
            'total' => round($totalAmount + $vat, 2),
            'rate' => $rate,
            'inclusive' => false,
        ];
    }
}
