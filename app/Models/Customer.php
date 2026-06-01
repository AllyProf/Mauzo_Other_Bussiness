<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'address',
        'region',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        $digits = ltrim($digits, '0');

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        }

        return $digits ? '+255'.$digits : null;
    }

    public function displayPhone(): string
    {
        return $this->phone ?: '—';
    }
}
