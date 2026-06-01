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
