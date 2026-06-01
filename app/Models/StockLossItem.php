<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLossItem extends Model
{
    protected $fillable = [
        'stock_loss_id',
        'item_id',
        'quantity',
        'unit_cost',
        'cost_value',
        'line_notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'cost_value' => 'float',
    ];

    public function stockLoss()
    {
        return $this->belongsTo(StockLoss::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
