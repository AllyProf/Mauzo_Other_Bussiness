<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMaterial extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'unit_label',
        'current_stock',
        'last_cost_per_unit',
    ];

    protected $casts = [
        'current_stock' => 'decimal:4',
        'last_cost_per_unit' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function receipts()
    {
        return $this->hasMany(ServiceMaterialReceipt::class)->latest('received_date')->latest('id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function stockLabel(): string
    {
        $qty = rtrim(rtrim(number_format((float) $this->current_stock, 2), '0'), '.');

        return $qty.' '.$this->unit_label;
    }
}
