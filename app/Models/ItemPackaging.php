<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPackaging extends Model
{
    protected $fillable = ['item_id', 'packaging_id', 'quantity_per_unit', 'cost_price', 'selling_price'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function packagingType()
    {
        return $this->belongsTo(Packaging::class, 'packaging_id');
    }
}
