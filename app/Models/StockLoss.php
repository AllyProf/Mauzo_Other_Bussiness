<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLoss extends Model
{
    public const REASONS = [
        'lost' => 'Lost / Missing',
        'damaged' => 'Damaged',
        'destroyed' => 'Destroyed / Written Off',
        'expired' => 'Expired',
        'other' => 'Other',
    ];

    protected $fillable = [
        'business_id',
        'user_id',
        'reference_no',
        'loss_date',
        'reason',
        'total_quantity',
        'total_cost_value',
        'notes',
        'status',
    ];

    protected $casts = [
        'loss_date' => 'date',
        'total_quantity' => 'float',
        'total_cost_value' => 'float',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(StockLossItem::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
