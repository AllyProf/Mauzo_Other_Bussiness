<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_image',
        'password',
        'business_id',
        'branch_id',
        'business_type_key',
        'business_type_keys',
        'role_id',
        'role',
        'platform_admin_role',
        'platform_admin_role_id',
        'is_active',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'business_type_keys' => 'array',
            'first_login_at' => 'datetime',
            'tour_completed_at' => 'datetime',
            'tour_skipped_at' => 'datetime',
        ];
    }

    public static function generateRandomPassword(int $length = 12): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $symbols = '!@#$%&*';
        $all = $upper.$lower.$numbers.$symbols;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ownedBusinesses()
    {
        return $this->hasMany(Business::class, 'owner_user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role_relation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function seesBusinessWideData(): bool
    {
        if (in_array($this->role, ['owner', 'super_admin'], true)) {
            return true;
        }

        return $this->can('view_reports');
    }

    public function assignedBusinessTypeKeys(): array
    {
        $keys = collect($this->business_type_keys ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        if ($keys !== []) {
            return $keys;
        }

        return $this->business_type_key ? [(string) $this->business_type_key] : [];
    }

    public function syncBusinessTypeAssignments(array $keys): void
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => is_string($key) && $key !== '')));
        $this->business_type_keys = $keys;
        $this->business_type_key = $keys[0] ?? null;
    }

    public function displayBusinessTypeLabels(): ?string
    {
        $keys = $this->assignedBusinessTypeKeys();
        if ($keys === []) {
            return null;
        }

        $this->loadMissing('business');

        return collect($keys)
            ->map(fn (string $key) => $this->business?->businessTypeLabel($key) ?? $key)
            ->implode(', ');
    }

    public function displayBusinessTypeLabel(): ?string
    {
        return $this->displayBusinessTypeLabels();
    }

    public function displayRoleName(): string
    {
        if ($this->role === 'super_admin') {
            return __('roles.super_admin');
        }

        if ($this->role === 'owner') {
            return __('roles.owner');
        }

        if ($this->role === 'platform_staff') {
            return __('roles.platform_staff');
        }

        $this->loadMissing('role_relation');

        return $this->role_relation?->name
            ?? ucfirst(str_replace('_', ' ', $this->role ?? 'Staff'));
    }

    public function sidebarDesignation(): string
    {
        return $this->displayRoleName();
    }

    public function hasRolePermissions(): bool
    {
        if (in_array($this->role, ['owner', 'super_admin'], true)) {
            return true;
        }

        $this->loadMissing('role_relation');

        if (! $this->role_id || ! $this->role_relation) {
            return false;
        }

        return count($this->role_relation->permissions ?? []) > 0;
    }

    public function requiresOpenShift(): bool
    {
        return ! in_array($this->role, ['owner', 'super_admin'], true);
    }

    public function needsShiftOpened(): bool
    {
        if (! $this->requiresOpenShift()) {
            return false;
        }

        if (! $this->can('open_shift') && ! $this->can('process_sales')) {
            return false;
        }

        return ! Shift::openForUser($this->id, (int) $this->business_id);
    }

    public function defaultLandingUrl(): string
    {
        if (in_array($this->role, ['super_admin', 'platform_staff'], true)) {
            return route('admin.dashboard');
        }

        return $this->needsShiftOpened()
            ? route('shifts.create')
            : url('/home');
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'platform_staff'], true);
    }

    public function platformAdminRole()
    {
        return $this->belongsTo(PlatformAdminRole::class, 'platform_admin_role_id');
    }
}
