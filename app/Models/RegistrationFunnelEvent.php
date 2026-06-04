<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationFunnelEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'event',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
