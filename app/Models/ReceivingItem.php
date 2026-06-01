<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receiving_id',
        'item_id',
        'quantity',
        'cost_price',
        'selling_price',
        'selling_prices_snapshot',
        'discount_type',
        'discount_value',
        'discount_amount',
    ];

    protected $casts = [
        'selling_prices_snapshot' => 'array',
    ];

    public function receiving()
    {
        return $this->belongsTo(Receiving::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
