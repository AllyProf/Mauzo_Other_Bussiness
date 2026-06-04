<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'login_identifier',
        'ip_address',
        'user_agent',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    public static function record(string $login, ?string $ip, ?string $userAgent): self
    {
        return self::create([
            'login_identifier' => mb_substr($login, 0, 255),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'attempted_at' => now(),
        ]);
    }
}
