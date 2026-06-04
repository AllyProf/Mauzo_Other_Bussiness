<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'owner_user_id',
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

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'branch_business')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function servesBusiness(int $businessId): bool
    {
        if ($this->businesses()->where('businesses.id', $businessId)->exists()) {
            return true;
        }

        return (int) $this->business_id === $businessId;
    }

    public function scopeForOwner($query, int $ownerUserId)
    {
        return $query->where('owner_user_id', $ownerUserId);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function createDefaultForBusiness(Business $business, ?string $name = null): self
    {
        $branch = self::create([
            'owner_user_id' => $business->owner_user_id,
            'business_id' => $business->id,
            'name' => $name ?: 'Main Branch',
            'address' => $business->address,
            'phone' => $business->phone,
            'is_active' => true,
            'is_default' => true,
        ]);

        $branch->businesses()->syncWithoutDetaching([
            $business->id => ['is_default' => true],
        ]);

        return $branch;
    }
}
