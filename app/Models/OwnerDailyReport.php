<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnerDailyReport extends Model
{
    protected $fillable = [
        'business_id',
        'day_closing_id',
        'report_date',
        'opening_circulation',
        'gross_sales',
        'cost_of_goods',
        'gross_profit',
        'total_collected',
        'payment_breakdown',
        'outstanding_debt',
        'staff_expenses',
        'owner_expenses',
        'expense_deduct_from',
        'net_profit',
        'opening_profit',
        'closing_profit',
        'closing_circulation',
        'status',
        'finalized_by',
        'finalized_at',
        'owner_notes',
    ];

    protected $casts = [
        'report_date' => 'date',
        'payment_breakdown' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function dayClosing()
    {
        return $this->belongsTo(DayClosing::class);
    }

    public function ownerExpenses()
    {
        return $this->hasMany(BusinessOwnerExpense::class);
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
