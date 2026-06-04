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
