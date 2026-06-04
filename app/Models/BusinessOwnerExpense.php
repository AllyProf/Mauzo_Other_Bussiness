<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOwnerExpense extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'business_type_key',
        'owner_daily_report_id',
        'expense_date',
        'description',
        'amount',
        'category',
        'fund_source',
        'recorded_by',
        'issued_to_user_id',
    ];

    public const CATEGORIES = [
        'restock' => 'Restock / Supply',
        'payment' => 'General Payment',
        'salary' => 'Salary / Wages',
        'operational' => 'Operational',
        'other' => 'Other',
    ];

    public const FUND_SOURCES = [
        'circulation' => 'Money in Circulation',
        'profit' => 'Profit',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function fundSourceLabel(): string
    {
        return self::FUND_SOURCES[$this->fund_source ?? 'circulation'] ?? ucfirst($this->fund_source);
    }

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function businessTypeLabel(Business $business): string
    {
        if (! $this->business_type_key) {
            return 'All / General';
        }

        return $business->businessTypeLabel($this->business_type_key);
    }

    public function report()
    {
        return $this->belongsTo(OwnerDailyReport::class, 'owner_daily_report_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }
}
