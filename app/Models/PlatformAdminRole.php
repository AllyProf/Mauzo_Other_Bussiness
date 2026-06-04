<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformAdminRole extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'platform_admin_role_id');
    }

    public function hasFullAccess(): bool
    {
        return in_array('*', $this->permissions ?? [], true);
    }

    public function grants(string $permission): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    public function permissionSummary(int $limit = 4): string
    {
        if ($this->hasFullAccess()) {
            return 'All platform areas';
        }

        $labels = collect(config('platform_admin.permission_groups', []))
            ->flatMap(fn ($group) => $group)
            ->only($this->permissions ?? [])
            ->values();

        if ($labels->isEmpty()) {
            return 'No permissions';
        }

        $summary = $labels->take($limit)->implode(', ');

        if ($labels->count() > $limit) {
            $summary .= ' +' . ($labels->count() - $limit) . ' more';
        }

        return $summary;
    }
}
