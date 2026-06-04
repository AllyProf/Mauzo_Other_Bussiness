<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyShortSettlement extends Model
{
    public const TYPE_CASH_PAYMENT = 'cash_payment';

    public const TYPE_SALARY_DEDUCTION = 'salary_deduction';

    protected $fillable = [
        'business_id',
        'day_closing_id',
        'user_id',
        'settlement_type',
        'amount',
        'payment_method',
        'payment_provider',
        'transaction_reference',
        'settlement_date',
        'notes',
        'recorded_by',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'amount' => 'float',
        'voided_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function dayClosing()
    {
        return $this->belongsTo(DayClosing::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isCashPayment(): bool
    {
        return $this->settlement_type === self::TYPE_CASH_PAYMENT;
    }

    public function typeLabel(): string
    {
        return match ($this->settlement_type) {
            self::TYPE_SALARY_DEDUCTION => 'Salary Deduction',
            default => 'Cash Payment',
        };
    }
}
