<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public static function instance(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['settings' => []]
        );
    }
}
