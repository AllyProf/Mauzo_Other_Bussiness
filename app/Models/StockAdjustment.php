<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    public const REASONS = [
        'incorrect_receiving' => 'Incorrect receiving',
        'physical_count' => 'Physical count correction',
        'data_entry_error' => 'Data entry error',
        'other' => 'Other',
    ];

    protected $fillable = [
        'business_id',
        'branch_id',
        'user_id',
        'reference_no',
        'adjustment_date',
        'reason',
        'total_items',
        'net_adjustment',
        'notes',
        'status',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'net_adjustment' => 'float',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
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
