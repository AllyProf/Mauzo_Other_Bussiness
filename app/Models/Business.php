<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
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
        'is_active',
        'pending_approval',
        'expense_deduct_from',
        'circulation_balance',
        'automation_settings',
        'payment_methods',
        'category_business_types',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'pending_approval' => 'boolean',
        'billing_price' => 'decimal:2',
        'profit_share_percent' => 'decimal:2',
        'minimum_monthly_fee' => 'decimal:2',
        'automation_settings' => 'array',
        'payment_methods' => 'array',
        'category_business_types' => 'array',
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
            return collect($savedAccounts)
                ->map(fn ($account) => [
                    'name' => trim($account['name'] ?? ''),
                    'pay_number' => trim($account['pay_number'] ?? ''),
                    'account_name' => trim($account['account_name'] ?? ''),
                ])
                ->filter(fn ($account) => $account['name'] !== '')
                ->values()
                ->all();
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

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'owner');
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

    public function branches()
    {
        return $this->hasMany(Branch::class);
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
}
