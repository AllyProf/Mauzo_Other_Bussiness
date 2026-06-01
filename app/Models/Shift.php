<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'opened_at',
        'closed_at',
        'status',
        'sales_count',
        'gross_sales',
        'amount_collected',
        'opening_variance_count',
        'closing_variance_count',
        'opening_notes',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'gross_sales' => 'float',
            'amount_collected' => 'float',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockChecks(): HasMany
    {
        return $this->hasMany(ShiftStockCheck::class);
    }

    public function openingChecks(): HasMany
    {
        return $this->stockChecks()->where('check_type', 'opening');
    }

    public function openingShortages(): HasMany
    {
        return $this->openingChecks()->where('variance', '<', -0.0001);
    }

    public function closingChecks(): HasMany
    {
        return $this->stockChecks()->where('check_type', 'closing');
    }

    public function dayClosing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DayClosing::class);
    }

    public static function latestClosedAwaitingHandover(int $userId, int $businessId): ?self
    {
        return self::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'closed')
            ->whereDoesntHave('dayClosing')
            ->latest('closed_at')
            ->first();
    }

    public static function openForUser(int $userId, int $businessId): ?self
    {
        return self::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();
    }

    public function refreshTotals(): void
    {
        $sales = $this->sales()->where('payment_status', '!=', 'cancelled');

        $this->update([
            'sales_count' => $sales->count(),
            'gross_sales' => (float) $sales->sum('total_amount'),
            'amount_collected' => (float) $sales->sum('amount_paid'),
        ]);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
