<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'service_category_id',
        'name',
        'code',
        'unit_label',
        'price',
        'description',
        'is_active',
        'consumable_item_id',
        'consumable_units_per_unit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'consumable_units_per_unit' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function consumableItem()
    {
        return $this->belongsTo(Item::class, 'consumable_item_id');
    }

    public function priceLabel(): string
    {
        if ((float) $this->price <= 0) {
            return 'Price not set';
        }

        return 'TZS '.number_format((float) $this->price, 0).' '.$this->unit_label;
    }
}
