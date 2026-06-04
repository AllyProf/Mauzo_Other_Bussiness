<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformBillingInvoice extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_NOTIFIED = 'notified';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'business_id',
        'plan_id',
        'billing_month',
        'invoice_number',
        'billing_model',
        'profit_basis',
        'profit_amount',
        'share_percent',
        'amount',
        'status',
        'emailed_at',
        'expiry_reminder_sent_at',
        'payment_reminder_sent_at',
        'paid_at',
        'payment_reference',
        'payment_notes',
        'marked_paid_by',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'profit_amount' => 'decimal:2',
        'share_percent' => 'decimal:2',
        'amount' => 'decimal:2',
        'emailed_at' => 'datetime',
        'expiry_reminder_sent_at' => 'datetime',
        'payment_reminder_sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function markedPaidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_paid_by');
    }

    public function billingMonthLabel(): string
    {
        return $this->billing_month->format('F Y');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_NOTIFIED => 'Invoice Sent',
            default => 'Pending Payment',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_NOTIFIED => 'info',
            default => 'warning',
        };
    }

    public function billingModelLabel(): string
    {
        return $this->billing_model === 'profit_share' ? 'Profit Share' : 'Fixed Fee';
    }
}
