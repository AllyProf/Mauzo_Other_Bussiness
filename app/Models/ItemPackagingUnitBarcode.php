<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPackagingUnitBarcode extends Model
{
    protected $fillable = [
        'business_id',
        'item_id',
        'item_packaging_id',
        'barcode',
    ];

    public function itemPackaging(): BelongsTo
    {
        return $this->belongsTo(ItemPackaging::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
