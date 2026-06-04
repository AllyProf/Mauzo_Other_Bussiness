<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'source_service_type_key',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class)->orderBy('name');
    }

    public function activeServices()
    {
        return $this->hasMany(Service::class)->where('is_active', true)->orderBy('name');
    }
}
