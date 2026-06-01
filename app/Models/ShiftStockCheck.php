<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftStockCheck extends Model
{
    protected $fillable = [
        'shift_id',
        'item_id',
        'check_type',
        'system_stock',
        'counted_stock',
        'variance',
        'notes',
        'recorded_by',
        'recorded_at',
        'verified_by',
        'verified_at',
        'owner_notes',
    ];

    protected function casts(): array
    {
        return [
            'system_stock' => 'float',
            'counted_stock' => 'float',
            'variance' => 'float',
            'recorded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isShort(): bool
    {
        return (float) $this->variance < -0.0001;
    }

    public function isOver(): bool
    {
        return (float) $this->variance > 0.0001;
    }

    public function shortageAmount(): float
    {
        return $this->isShort() ? abs((float) $this->variance) : 0;
    }

    public function scopeShortages($query)
    {
        return $query->where('variance', '<', -0.0001);
    }

    public function scopePendingVerification($query)
    {
        return $query->whereNull('verified_at');
    }
}
