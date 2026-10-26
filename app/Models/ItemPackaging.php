<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPackaging extends Model
{
    protected $fillable = [
        'item_id',
        'packaging_id',
        'quantity_per_unit',
        'cost_price',
        'selling_price',
        'barcode',
        'manufacturer_barcode',
    ];

    public function hasLinkedBarcode(): bool
    {
        if (filled($this->manufacturer_barcode)) {
            return true;
        }

        if ($this->relationLoaded('unitBarcodes')) {
            return $this->unitBarcodes->isNotEmpty();
        }

        return $this->unitBarcodes()->exists();
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function packagingType()
    {
        return $this->belongsTo(Packaging::class, 'packaging_id');
    }

    public function unitBarcodes()
    {
        return $this->hasMany(ItemPackagingUnitBarcode::class);
    }
}
