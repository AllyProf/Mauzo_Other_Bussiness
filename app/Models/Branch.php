<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'address',
        'location',
        'leader_name',
        'leader_phone',
        'leader_email',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function createDefaultForBusiness(Business $business, ?string $name = null): self
    {
        return self::create([
            'business_id' => $business->id,
            'name' => $name ?: 'Main Branch',
            'address' => $business->address,
            'phone' => $business->phone,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
