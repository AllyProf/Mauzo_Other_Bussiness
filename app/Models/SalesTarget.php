<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    protected $fillable = [
        'business_id',
        'period_type',
        'period_start',
        'period_end',
        'target_amount',
        'branch_id',
        'business_type_key',
        'user_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'target_amount' => 'float',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periodLabel(): string
    {
        return match ($this->period_type) {
            'daily' => $this->period_start->format('D, M j, Y'),
            'weekly' => 'Week of '.$this->period_start->format('M j').' – '.$this->period_end->format('M j, Y'),
            default => $this->period_start->format('F Y'),
        };
    }

    public function displayTitle(Business $business): string
    {
        $parts = [ucfirst($this->period_type).' target'];

        if ($this->branch) {
            $parts[] = $this->branch->name;
        }

        if ($this->business_type_key) {
            $parts[] = $business->businessTypeLabel($this->business_type_key);
        }

        if ($this->user) {
            $parts[] = $this->user->name;
        }

        if (! $this->branch && ! $this->business_type_key && ! $this->user) {
            $parts[] = 'Whole shop';
        }

        return implode(' · ', $parts);
    }
}
