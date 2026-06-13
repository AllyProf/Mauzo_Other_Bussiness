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
        'consumables_deducted',
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
        'debt_due_soon_sms_sent_at',
        'debt_due_soon_second_sms_sent_at',
        'debt_due_today_sms_sent_at',
        'debt_overdue_sms_sent_at',
    ];

    protected $casts = [
        'stock_deducted' => 'boolean',
        'consumables_deducted' => 'boolean',
        'due_date' => 'date',
        'debt_due_soon_sms_sent_at' => 'datetime',
        'debt_due_soon_second_sms_sent_at' => 'datetime',
        'debt_due_today_sms_sent_at' => 'datetime',
        'debt_overdue_sms_sent_at' => 'datetime',
    ];

    public function isInvoice(): bool
    {
        return ($this->sale_source ?? 'pos') === 'invoice';
    }

    public function isServicePos(): bool
    {
        return ($this->sale_source ?? 'pos') === 'service_pos';
    }

    public function isServiceInvoice(): bool
    {
        return ($this->sale_source ?? 'pos') === 'service_invoice';
    }

    public function usesServices(): bool
    {
        return $this->isServicePos() || $this->isServiceInvoice();
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

    public function soldItemsSummary(int $previewLimit = 0): string
    {
        $lines = $this->items
            ->map(fn (SaleItem $item) => $item->soldLineDescription())
            ->filter()
            ->values();

        if ($previewLimit <= 0 || $lines->count() <= $previewLimit) {
            return $lines->implode(', ');
        }

        $remaining = $lines->count() - $previewLimit;

        return $lines->take($previewLimit)->implode(', ').' (+ '.$remaining.' more)';
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function businessTypeKeys(): array
    {
        if (! $this->relationLoaded('items')) {
            $this->load('items.item.category');
        }

        return $this->items
            ->map(fn (SaleItem $line) => $line->item?->category?->source_business_type_key ?: 'other')
            ->unique()
            ->values()
            ->all();
    }
}
