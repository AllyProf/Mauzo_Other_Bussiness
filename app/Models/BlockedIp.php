<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'failed_attempts',
        'blocked_until',
        'cleared_at',
    ];

    protected $casts = [
        'failed_attempts' => 'integer',
        'blocked_until' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }
}
