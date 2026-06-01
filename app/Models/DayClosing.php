<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayClosing extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'shift_id',
        'closing_date',
        'status',
        'sales_count',
        'gross_sales',
        'amount_collected',
        'outstanding_sales',
        'payments_received',
        'cash_received',
        'mobile_received',
        'bank_received',
        'payment_breakdown',
        'cancelled_sales',
        'total_expenses',
        'net_amount',
        'report_notes',
        'submitted_at',
        'verified_by',
        'verified_at',
        'dispute_reason',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'payment_breakdown' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function expenses()
    {
        return $this->hasMany(DayClosingExpense::class);
    }
}
