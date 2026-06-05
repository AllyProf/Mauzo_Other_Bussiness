<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSmsLog extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'customer_id',
        'campaign_id',
        'phone',
        'recipient_email',
        'recipient_name',
        'message',
        'channel',
        'purpose',
        'status',
        'provider_response',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CustomerCommunicationCampaign::class, 'campaign_id');
    }

    public function statusLabel(): string
    {
        $key = 'communications.statuses.'.$this->status;
        $translated = __($key);

        return $translated !== $key ? $translated : ucfirst($this->status ?? '');
    }

    public function channelLabel(): string
    {
        $key = 'communications.channels_map.'.$this->channel;
        $translated = __($key);

        return $translated !== $key ? $translated : strtoupper($this->channel ?? '');
    }

    public function purposeLabel(): string
    {
        $key = 'communications.purposes.'.$this->purpose;
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return match ($this->purpose) {
            'new_product' => __('communications.purposes.new_product_short'),
            'promotion' => __('communications.purposes.promotion_short'),
            default => __('communications.purposes.general'),
        };
    }

    public function recipientContact(): string
    {
        if ($this->channel === 'email' && $this->recipient_email) {
            return $this->recipient_email;
        }

        return $this->phone ?? '—';
    }
}

