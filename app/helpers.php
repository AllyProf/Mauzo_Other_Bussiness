<?php

if (! function_exists('money')) {
    /**
     * Format amounts as whole TZS (no decimal places).
     */
    function money(float|int|string|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 0, '.', ',');

        return $withCurrency ? 'TZS '.$formatted : $formatted;
    }
}

if (! function_exists('active_business_service')) {
    function active_business_service(): \App\Services\ActiveBusinessService
    {
        return app(\App\Services\ActiveBusinessService::class);
    }
}

if (! function_exists('active_business_id')) {
    function active_business_id(): ?int
    {
        return active_business_service()->activeBusinessId();
    }
}

if (! function_exists('active_business')) {
    function active_business(): ?\App\Models\Business
    {
        return active_business_service()->activeBusiness();
    }
}

if (! function_exists('current_business_id')) {
    function current_business_id(): int
    {
        return (int) (active_business_id() ?? auth()->user()?->business_id);
    }
}

if (! function_exists('active_branch_service')) {
    function active_branch_service(): \App\Services\ActiveBranchService
    {
        return app(\App\Services\ActiveBranchService::class);
    }
}

if (! function_exists('active_branch_id')) {
    function active_branch_id(): ?int
    {
        return active_branch_service()->activeBranchId();
    }
}

if (! function_exists('active_branch')) {
    function active_branch(): ?\App\Models\Branch
    {
        return active_branch_service()->activeBranch();
    }
}

if (! function_exists('database_is_ready')) {
    /**
     * True when the default DB connection can be used (file exists, server reachable, etc.).
     * Safe during composer install / key:generate before migrate.
     */
    function database_is_ready(): bool
    {
        try {
            $default = config('database.default');
            if (! $default) {
                return false;
            }

            $connection = config("database.connections.{$default}");
            if (($connection['driver'] ?? '') === 'sqlite') {
                $database = $connection['database'] ?? '';
                if ($database !== '' && $database !== ':memory:' && ! is_file($database)) {
                    return false;
                }
            }

            $connection = \Illuminate\Support\Facades\DB::connection();
            $connection->getPdo();
            $connection->select('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

if (! function_exists('platform_settings')) {
    function platform_settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(\App\Services\PlatformSettingsService::class);

        if ($key === null) {
            return $service->all();
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('tanzania_regions')) {
    function tanzania_regions(): array
    {
        return array_keys(config('tanzania_locations.regions', []));
    }
}

if (! function_exists('tanzania_districts')) {
    function tanzania_districts(?string $region = null): array
    {
        $regions = config('tanzania_locations.regions', []);

        if ($region === null) {
            return $regions;
        }

        return $regions[$region] ?? [];
    }
}

if (! function_exists('plan_feature')) {
    function plan_feature(string $key): bool
    {
        return app(\App\Services\PlanFeatureService::class)->userHasFeature(auth()->user(), $key);
    }
}

if (! function_exists('plan_feature_any')) {
    function plan_feature_any(array $keys): bool
    {
        return app(\App\Services\PlanFeatureService::class)->userHasAnyFeature(auth()->user(), $keys);
    }
}

if (! function_exists('business_report_catalog')) {
    /**
     * All business report types (plan feature + staff permission per type).
     *
     * @return list<array{key: string, feature: string, permission: string, route: string, label: string}>
     */
    function business_report_catalog(): array
    {
        return [
            [
                'key' => 'daily-report',
                'feature' => 'reports_daily',
                'permission' => 'view_report_daily_report',
                'route' => 'reports.daily-report',
                'label' => __('menu.daily_report'),
            ],
            [
                'key' => 'payment-channels',
                'feature' => 'reports_daily',
                'permission' => 'view_report_payment_channels',
                'route' => 'reports.payment-channels',
                'label' => 'Payment channels',
            ],
            [
                'key' => 'daily-items-sold',
                'feature' => 'reports_daily',
                'permission' => 'view_report_daily_items_sold',
                'route' => 'reports.daily-items-sold',
                'label' => __('menu.daily_items_sold'),
            ],
            [
                'key' => 'circulation-profit',
                'feature' => 'reports_circulation',
                'permission' => 'view_report_circulation_profit',
                'route' => 'reports.circulation-profit',
                'label' => __('menu.circulation_vs_profit'),
            ],
            [
                'key' => 'daily-sales',
                'feature' => 'reports_daily',
                'permission' => 'view_report_daily_sales',
                'route' => 'reports.daily-sales',
                'label' => __('menu.daily_sales'),
            ],
            [
                'key' => 'expenses',
                'feature' => 'reports_expenses',
                'permission' => 'view_report_expenses',
                'route' => 'reports.expenses',
                'label' => __('menu.expenses'),
            ],
            [
                'key' => 'profit',
                'feature' => 'reports_profit',
                'permission' => 'view_report_profit',
                'route' => 'reports.profit',
                'label' => __('menu.profit'),
            ],
            [
                'key' => 'sales-analytics',
                'feature' => 'reports_sales',
                'permission' => 'view_report_sales_analytics',
                'route' => 'reports.sales-analytics',
                'label' => __('menu.sales_analytics'),
            ],
            [
                'key' => 'products',
                'feature' => 'reports_products',
                'permission' => 'view_report_products',
                'route' => 'reports.products',
                'label' => __('menu.products'),
            ],
            [
                'key' => 'debts',
                'feature' => 'reports_debts',
                'permission' => 'view_report_debts',
                'route' => 'reports.debts',
                'label' => __('menu.debt_report'),
            ],
            [
                'key' => 'cashiers',
                'feature' => 'reports_sales',
                'permission' => 'view_report_cashiers',
                'route' => 'reports.cashiers',
                'label' => 'Cashier Performance',
            ],
        ];
    }
}

if (! function_exists('business_report_permission_for_key')) {
    function business_report_permission_for_key(string $reportKey): ?string
    {
        $item = collect(business_report_catalog())->firstWhere('key', $reportKey);

        return $item['permission'] ?? null;
    }
}

if (! function_exists('business_report_permission_keys')) {
    /** @return list<string> */
    function business_report_permission_keys(): array
    {
        return collect(business_report_catalog())
            ->pluck('permission')
            ->merge(['view_report_master_sheet'])
            ->unique()
            ->values()
            ->all();
    }
}

if (! function_exists('business_report_authorize_abilities')) {
    /** @return list<string> */
    function business_report_authorize_abilities(string $reportKey): array
    {
        $permission = business_report_permission_for_key($reportKey);

        return array_values(array_filter(['view_reports', $permission]));
    }
}

if (! function_exists('user_can_business_report')) {
    function user_can_business_report(string $reportKey, ?\App\Models\User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->can('view_reports')) {
            return true;
        }

        $permission = business_report_permission_for_key($reportKey);

        return $permission && $user->can($permission);
    }
}

if (! function_exists('user_can_any_business_report')) {
    function user_can_any_business_report(?\App\Models\User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->can('view_reports')) {
            return true;
        }

        foreach (business_report_permission_keys() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('user_can_master_sheet')) {
    function user_can_master_sheet(?\App\Models\User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->can('view_reports') || $user->can('view_report_master_sheet');
    }
}

if (! function_exists('business_report_menu_options')) {
    /**
     * Report types available on the business Reports hub (plan + permission gated).
     *
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    function business_report_menu_options(?string $activeKey = null): array
    {
        if ($activeKey === null && request()->routeIs('reports.*')) {
            $activeKey = str_replace('reports.', '', (string) request()->route()?->getName());
        }

        $options = [];
        foreach (business_report_catalog() as $item) {
            if (! plan_feature($item['feature'])) {
                continue;
            }

            if (! user_can_business_report($item['key'])) {
                continue;
            }

            $options[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => route($item['route']),
                'active' => $activeKey === $item['key'],
            ];
        }

        return $options;
    }
}

if (! function_exists('business_retail_enabled')) {
    function business_retail_enabled(): bool
    {
        $business = auth()->user()?->business;

        return $business ? $business->isRetailEnabled() : true;
    }
}

if (! function_exists('business_services_menu_visible')) {
    function business_services_menu_visible(): bool
    {
        $business = auth()->user()?->business;

        return $business ? $business->servicesMenuVisible() : false;
    }
}

if (! function_exists('platform_admin_can')) {
    function platform_admin_can(string $permission): bool
    {
        return app(\App\Services\PlatformAdminService::class)->canAccess(auth()->user(), $permission);
    }
}

if (! function_exists('__report_status')) {
    function __report_status(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $key = 'owner_reports.statuses.'.str_replace(' ', '_', $status);
        $translated = __($key);

        return $translated !== $key ? $translated : $status;
    }
}

if (! function_exists('category_templates')) {
    /**
     * Category import templates with locale-aware labels and category names.
     *
     * @return array<string, array{label: string, icon?: string, categories: array<int, string>}>
     */
    function category_templates(): array
    {
        $templates = config('category_templates', []);
        $locale = app()->getLocale();

        if ($locale === 'en') {
            return $templates;
        }

        $langPath = lang_path($locale.'/category_templates.php');
        if (! is_file($langPath)) {
            return $templates;
        }

        /** @var array<string, array{label?: string, categories?: array<int, string>}> $translations */
        $translations = require $langPath;

        foreach ($templates as $key => $template) {
            if (! isset($translations[$key])) {
                continue;
            }

            if (! empty($translations[$key]['label'])) {
                $templates[$key]['label'] = $translations[$key]['label'];
            }

            if (! empty($translations[$key]['categories'])) {
                $templates[$key]['categories'] = $translations[$key]['categories'];
            }
        }

        return $templates;
    }
}

if (! function_exists('packaging_templates')) {
    /**
     * Packaging unit templates with locale-aware unit names.
     *
     * @return array<string, array<int, string>>
     */
    function packaging_templates(): array
    {
        $templates = config('packaging_templates', []);
        $locale = app()->getLocale();

        if ($locale === 'en') {
            return $templates;
        }

        $langPath = lang_path($locale.'/packaging_templates.php');
        if (! is_file($langPath)) {
            return $templates;
        }

        /** @var array<string, array<int, string>> $translations */
        $translations = require $langPath;

        foreach ($templates as $key => $units) {
            if (! empty($translations[$key])) {
                $templates[$key] = $translations[$key];
            }
        }

        return $templates;
    }
}
