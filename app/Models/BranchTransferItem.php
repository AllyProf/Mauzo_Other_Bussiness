<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchTransferItem extends Model
{
    protected $fillable = [
        'branch_transfer_id',
        'from_item_id',
        'to_item_id',
        'item_packaging_id',
        'unit_name',
        'unit_quantity',
        'quantity_per_unit',
        'quantity',
        'from_stock_before',
        'to_stock_before',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_quantity' => 'float',
        'quantity_per_unit' => 'int',
        'from_stock_before' => 'float',
        'to_stock_before' => 'float',
    ];

    public function transfer()
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    public function fromItem()
    {
        return $this->belongsTo(Item::class, 'from_item_id');
    }

    public function toItem()
    {
        return $this->belongsTo(Item::class, 'to_item_id');
    }

    public function packaging()
    {
        return $this->belongsTo(ItemPackaging::class, 'item_packaging_id');
    }

    public function quantityLabel(): string
    {
        $pieces = fmod($this->quantity, 1.0) === 0.0
            ? (string) (int) $this->quantity
            : number_format($this->quantity, 2);
        $unitQty = $this->unit_quantity ?? $this->quantity;
        $unitName = trim((string) ($this->unit_name ?? ''));
        $qpu = max(1, (int) ($this->quantity_per_unit ?? 1));

        if ($unitName === '' || $qpu <= 1) {
            return $pieces.' pcs';
        }

        $formattedUnit = fmod((float) $unitQty, 1.0) === 0.0
            ? (string) (int) $unitQty
            : number_format((float) $unitQty, 2);

        return $formattedUnit.' '.$unitName.' ('.$pieces.' pcs)';
    }
}
