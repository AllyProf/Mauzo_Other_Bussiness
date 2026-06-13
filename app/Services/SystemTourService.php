<?php

namespace App\Services;

use App\Models\User;

class SystemTourService
{
    public function resolveTourKey(User $user): ?string
    {
        if ($user->isPlatformAdmin()
            || session()->has('impersonate_original_user')
            || session()->has('impersonate_staff_original_user')) {
            return null;
        }

        if ($user->role === 'staff' && ! $user->hasRolePermissions()) {
            return 'unassigned';
        }

        if ($user->role === 'owner') {
            return 'owner';
        }

        if ($user->requiresOpenShift() && $user->can('process_sales')) {
            return 'sales_officer';
        }

        return 'staff';
    }

    public function shouldShow(User $user): bool
    {
        if (! $this->resolveTourKey($user)) {
            return false;
        }

        if ($user->tour_completed_at || $user->tour_skipped_at) {
            if (! session('replay_system_tour')) {
                return false;
            }
        }

        return session('show_system_tour') || session('replay_system_tour');
    }

    /**
     * @return list<array{element?: string, title: string, intro: string}>
     */
    public function stepsFor(User $user): array
    {
        $key = $this->resolveTourKey($user);

        if (! $key) {
            return [];
        }

        $configName = app()->getLocale() === 'sw' ? 'system_tour_sw' : 'system_tour_en';
        $steps = config("{$configName}.{$key}", config("system_tour.{$key}", []));

        return array_values(array_filter(
            array_map(fn (array $step) => $this->localizeStep($step), $steps),
            fn (array $step) => $this->stepVisible($user, $step)
        ));
    }

    private function localizeStep(array $step): array
    {
        $locale = app()->getLocale();

        foreach (['title', 'intro'] as $field) {
            if (! isset($step[$field]) || ! is_string($step[$field])) {
                continue;
            }

            if (str_contains($step[$field], ' — ')) {
                [$english, $swahili] = array_pad(explode(' — ', $step[$field], 2), 2, null);
                $step[$field] = $locale === 'sw' ? ($swahili ?: $english) : $english;
            }
        }

        return $step;
    }

    public function markCompleted(User $user): void
    {
        $user->update([
            'tour_completed_at' => now(),
            'tour_skipped_at' => null,
        ]);

        session()->forget(['show_system_tour', 'replay_system_tour']);
    }

    public function markSkipped(User $user): void
    {
        $user->update([
            'tour_skipped_at' => now(),
        ]);

        session()->forget(['show_system_tour', 'replay_system_tour']);
    }

    public function queueForNextPage(User $user): void
    {
        if (! $this->resolveTourKey($user)) {
            return;
        }

        session(['show_system_tour' => true]);
    }

    public function queueReplay(User $user): void
    {
        if (! $this->resolveTourKey($user)) {
            return;
        }

        session(['replay_system_tour' => true]);
    }

    private function stepVisible(User $user, array $step): bool
    {
        if (isset($step['role']) && $user->role !== $step['role']) {
            return false;
        }

        if (! empty($step['any_roles']) && ! in_array($user->role, $step['any_roles'], true)) {
            return false;
        }

        if (! empty($step['any_permissions'])) {
            $allowed = false;

            foreach ($step['any_permissions'] as $permission) {
                if ($user->can($permission)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return false;
            }
        }

        if (! empty($step['plan_feature']) && ! plan_feature($step['plan_feature'])) {
            return false;
        }

        if (! empty($step['plan_features']) && ! plan_feature_any($step['plan_features'])) {
            return false;
        }

        if (! empty($step['retail_required']) && ! business_retail_enabled()) {
            return false;
        }

        if (! empty($step['services_required']) && ! business_services_menu_visible()) {
            return false;
        }

        return true;
    }
}
