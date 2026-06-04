<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;

class PlanFeatureService
{
    public function allKeys(): array
    {
        return collect(config('plan_features.groups', []))
            ->flatMap(fn ($group) => array_keys($group))
            ->values()
            ->all();
    }

    public function groups(): array
    {
        return config('plan_features.groups', []);
    }

    public function label(string $key): string
    {
        foreach ($this->groups() as $features) {
            if (isset($features[$key])) {
                return $features[$key];
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    public function defaultEnabled(): array
    {
        return $this->allKeys();
    }

    public function normalizeSelection(?array $selected): array
    {
        $allowed = $this->allKeys();

        return array_values(array_intersect($allowed, $selected ?? []));
    }

    public function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach (config('plan_features.exempt_routes', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return null;
            }
        }

        foreach (config('plan_features.routes', []) as $feature => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $feature;
                }
            }
        }

        return null;
    }

    public function userHasFeature(?User $user, string $key): bool
    {
        if (! $user || $user->role === 'super_admin') {
            return true;
        }

        $business = $user->business;

        if (! $business) {
            return false;
        }

        return $this->businessHasFeature($business, $key);
    }

    public function businessHasFeature(Business $business, string $key): bool
    {
        $business->loadMissing('plan');

        if (! $business->plan) {
            return true;
        }

        return $business->plan->hasFeature($key);
    }

    public function userHasAnyFeature(?User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->userHasFeature($user, $key)) {
                return true;
            }
        }

        return false;
    }

    public function reportFeatureKeys(): array
    {
        return [
            'reports_daily',
            'reports_expenses',
            'reports_sales',
            'reports_products',
            'reports_debts',
            'reports_profit',
            'reports_circulation',
            'master_sheet',
        ];
    }

    public function disabledFeaturesForBusiness(Business $business): array
    {
        $business->loadMissing('plan');

        if (! $business->plan) {
            return [];
        }

        return array_values(array_diff($this->allKeys(), $business->plan->enabledFeatures()));
    }

    public function marketingSummary(Plan $plan): string
    {
        $enabled = $plan->enabledFeatures();

        if ($enabled === []) {
            return 'Core POS only';
        }

        return collect($enabled)
            ->map(fn (string $key) => $this->label($key))
            ->take(4)
            ->implode(', ');
    }
}
