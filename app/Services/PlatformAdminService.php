<?php

namespace App\Services;

use App\Models\PlatformAdminRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlatformAdminService
{
    public function isPlatformAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, ['super_admin', 'platform_staff'], true);
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(?User $user): array
    {
        if (! $this->isPlatformAdmin($user)) {
            return [];
        }

        if ($user->role === 'super_admin') {
            return ['*'];
        }

        $user->loadMissing('platformAdminRole');

        if ($user->platformAdminRole) {
            return $user->platformAdminRole->permissions ?? [];
        }

        $legacySlug = $user->platform_admin_role ?: 'readonly';

        return config("platform_admin.roles.{$legacySlug}", []);
    }

    public function canAccess(?User $user, string $permission): bool
    {
        $permissions = $this->permissionsFor($user);

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function canAccessRoute(?User $user, string $routeName): bool
    {
        if (! $this->isPlatformAdmin($user)) {
            return false;
        }

        foreach (config('platform_admin.route_permissions', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $this->canAccess($user, $permission);
            }
        }

        return $this->canAccess($user, 'settings');
    }

    public function ipAllowed(Request $request): bool
    {
        $allowlist = $this->normalizedIpAllowlist();

        if ($allowlist === []) {
            return true;
        }

        $clientIp = $request->ip();

        foreach ($allowlist as $allowed) {
            if ($allowed === $clientIp) {
                return true;
            }

            if (str_contains($allowed, '/') && $this->ipInCidr($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function normalizedIpAllowlist(): array
    {
        $raw = platform_settings('admin_ip_allowlist', '');

        if (is_array($raw)) {
            $lines = $raw;
        } else {
            $lines = preg_split('/[\r\n,]+/', (string) $raw) ?: [];
        }

        return array_values(array_filter(array_map('trim', $lines)));
    }

    public function unreadTicketsCount(): int
    {
        return Ticket::query()
            ->where('status', 'open')
            ->whereNull('admin_read_at')
            ->count();
    }

    public function assignableRoles(): Collection
    {
        return PlatformAdminRole::query()
            ->where('slug', '!=', 'full')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function allPermissionKeys(): array
    {
        return collect(config('platform_admin.permission_groups', []))
            ->flatMap(fn ($group) => array_keys($group))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $submitted
     * @return list<string>
     */
    public function normalizePermissions(?array $submitted): array
    {
        $allowed = $this->allPermissionKeys();

        return array_values(array_intersect($allowed, $submitted ?? []));
    }

    private function ipInCidr(?string $ip, string $cidr): bool
    {
        if (! $ip || ! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $mask = (int) $mask;
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }
}
