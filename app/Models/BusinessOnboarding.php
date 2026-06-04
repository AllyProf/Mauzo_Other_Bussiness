<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessOnboarding extends Model
{
    protected $table = 'business_onboarding';

    protected $fillable = [
        'business_id',
        'completed_steps',
        'completed_at',
    ];

    protected $casts = [
        'completed_steps' => 'array',
        'completed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
