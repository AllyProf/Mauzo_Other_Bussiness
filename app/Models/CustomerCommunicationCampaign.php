<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCommunicationCampaign extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'customer_ids',
        'channels',
        'purpose',
        'subject',
        'message',
        'scheduled_at',
        'status',
        'result_summary',
        'sent_at',
    ];

    protected $casts = [
        'customer_ids' => 'array',
        'channels' => 'array',
        'result_summary' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CustomerSmsLog::class, 'campaign_id');
    }

    public function isDue(): bool
    {
        return $this->status === 'scheduled'
            && $this->scheduled_at !== null
            && $this->scheduled_at->lte(now());
    }

    public function statusLabel(): string
    {
        $key = 'communications.statuses.'.$this->status;
        $translated = __($key);

        return $translated !== $key ? $translated : ucfirst($this->status ?? '');
    }

    public function purposeLabel(): string
    {
        $key = 'communications.purposes.'.$this->purpose;
        $translated = __($key);

        return $translated !== $key ? $translated : ucfirst(str_replace('_', ' ', $this->purpose ?? ''));
    }

    public function channelsLabel(): string
    {
        $labels = [];

        foreach ($this->channels ?? [] as $channel) {
            $key = 'communications.channels_map.'.$channel;
            $translated = __($key);
            $labels[] = $translated !== $key ? $translated : strtoupper($channel);
        }

        return $labels === [] ? '—' : implode(' + ', $labels);
    }
}
