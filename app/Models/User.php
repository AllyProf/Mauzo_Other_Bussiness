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
        'password',
        'business_id',
        'branch_id',
        'role_id',
        'role',
        'is_active',
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

    public function displayRoleName(): string
    {
        if ($this->role === 'super_admin') {
            return 'Software Owner';
        }

        if ($this->role === 'owner') {
            return 'Business Owner';
        }

        $this->loadMissing('role_relation');

        return $this->role_relation?->name
            ?? ucfirst(str_replace('_', ' ', $this->role ?? 'Staff'));
    }

    public function sidebarDesignation(): string
    {
        return $this->displayRoleName();
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
        return $this->needsShiftOpened()
            ? route('shifts.create')
            : url('/home');
    }
}
