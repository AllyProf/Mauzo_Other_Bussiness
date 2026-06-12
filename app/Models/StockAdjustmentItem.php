<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'previous_stock',
        'new_stock',
        'adjustment_qty',
        'line_notes',
    ];

    protected $casts = [
        'previous_stock' => 'float',
        'new_stock' => 'float',
        'adjustment_qty' => 'float',
    ];

    public function stockAdjustment()
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
