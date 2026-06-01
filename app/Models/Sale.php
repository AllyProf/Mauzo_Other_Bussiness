<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'customer_id',
        'shift_id',
        'reference_no',
        'sale_source',
        'stock_deducted',
        'sale_date',
        'total_amount',
        'amount_paid',
        'payment_method',
        'notes',
        'payment_status',
        'payment_provider',
        'transaction_reference',
        'customer_name',
        'customer_phone',
        'due_date',
    ];

    protected $casts = [
        'stock_deducted' => 'boolean',
    ];

    public function isInvoice(): bool
    {
        return ($this->sale_source ?? 'pos') === 'invoice';
    }

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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function soldItemsSummary(): string
    {
        return $this->items
            ->map(fn (SaleItem $item) => $item->soldLineDescription())
            ->filter()
            ->implode(', ');
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }
}
