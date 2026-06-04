<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BusinessNote extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'title',
        'body',
        'remind_at',
        'reminder_sms_sent_at',
        'completed_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'reminder_sms_sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now());
    }

    public function scopeDueForSms(Builder $query): Builder
    {
        return $query->due()->whereNull('reminder_sms_sent_at');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('remind_at')
            ->where('remind_at', '>', now());
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isDue(): bool
    {
        return ! $this->isCompleted()
            && $this->remind_at !== null
            && $this->remind_at->lte(now());
    }

    public function displayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $preview = trim(strip_tags($this->body));

        return $preview !== '' ? \Illuminate\Support\Str::limit($preview, 60) : 'Untitled note';
    }

    public function statusLabel(): string
    {
        if ($this->isCompleted()) {
            return 'Completed';
        }

        if ($this->remind_at === null) {
            return 'No reminder';
        }

        if ($this->isDue()) {
            return 'Due now';
        }

        return 'Scheduled';
    }

    public function statusBadgeClass(): string
    {
        if ($this->isCompleted()) {
            return 'secondary';
        }

        if ($this->isDue()) {
            return 'danger';
        }

        if ($this->remind_at !== null) {
            return 'info';
        }

        return 'light';
    }
}
