<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformLead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'message',
        'source',
        'status',
        'ip_address',
    ];

    public function statusLabel(): string
    {
        return match ($this->status) {
            'contacted' => 'Contacted',
            'converted' => 'Converted',
            'closed' => 'Closed',
            default => 'New',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'contacted' => 'info',
            'converted' => 'success',
            'closed' => 'secondary',
            default => 'warning',
        };
    }
}
