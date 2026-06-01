<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'item_id',
        'item_packaging_id',
        'quantity',
        'unit_price',
        'list_unit_price',
        'cost_price',
        'subtotal',
        'adjustment_mode',
        'discount_type',
        'discount_value',
        'discount_amount',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function itemPackaging()
    {
        return $this->belongsTo(ItemPackaging::class);
    }

    public function soldLineDescription(): string
    {
        $qty = (float) $this->quantity;
        $qtyLabel = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2), '0'), '.');
        $unitName = $this->itemPackaging?->packagingType?->name ?? 'Unit';
        $itemName = $this->item?->name ?? 'Item';

        return trim($qtyLabel . ' ' . $unitName . ' ' . $itemName);
    }
}
