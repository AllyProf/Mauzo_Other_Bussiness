<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'sales',
        'stock',
        'day_closing',
        'targets',
        'customers',
        'system',
    ];

    protected function casts(): array
    {
        return [
            'sales' => 'boolean',
            'stock' => 'boolean',
            'day_closing' => 'boolean',
            'targets' => 'boolean',
            'customers' => 'boolean',
            'system' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{sales: bool, stock: bool, day_closing: bool, targets: bool, customers: bool, system: bool}
     */
    public static function defaults(): array
    {
        return [
            'sales' => true,
            'stock' => true,
            'day_closing' => true,
            'targets' => true,
            'customers' => true,
            'system' => true,
        ];
    }

    /**
     * @return array{sales: bool, stock: bool, day_closing: bool, targets: bool, customers: bool, system: bool}
     */
    public function toFlags(): array
    {
        return [
            'sales' => (bool) $this->sales,
            'stock' => (bool) $this->stock,
            'day_closing' => (bool) $this->day_closing,
            'targets' => (bool) $this->targets,
            'customers' => (bool) $this->customers,
            'system' => (bool) $this->system,
        ];
    }
}
