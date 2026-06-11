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
        'qty_mode',
        'cost_price',
        'cost_mode',
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

    public function isPieceQtyMode(): bool
    {
        return ($this->qty_mode ?? 'pkg') === 'piece';
    }

    public function receivedPieces(?Item $item = null): float
    {
        if ($this->isPieceQtyMode()) {
            return (float) $this->quantity;
        }

        $item ??= $this->relationLoaded('item') ? $this->item : null;
        $unitsPerReceiving = max(1, (int) ($item?->units_per_receiving_pack ?? 1));

        return (float) $this->quantity * $unitsPerReceiving;
    }

    public function receivedQuantityLabel(?Item $item = null): string
    {
        $item ??= $this->relationLoaded('item') ? $this->item : null;
        $unitsPerReceiving = max(1, (int) ($item?->units_per_receiving_pack ?? 1));
        $receivingUnitName = $item?->receivingPackaging?->name ?? 'Unit';
        $qtyLabel = self::formatQty($this->quantity);

        if ($this->isPieceQtyMode()) {
            return $qtyLabel.' pcs';
        }

        if ($unitsPerReceiving > 1) {
            $pieces = (int) $this->quantity * $unitsPerReceiving;

            return $qtyLabel.' '.$receivingUnitName.' ('.$pieces.' pcs)';
        }

        return $qtyLabel.' '.$receivingUnitName;
    }

    public static function receivedPiecesSql(string $quantityColumn = 'receiving_items.quantity', string $qtyModeColumn = 'receiving_items.qty_mode', string $unitsColumn = 'items.units_per_receiving_pack'): string
    {
        return "CASE WHEN {$qtyModeColumn} = 'piece' THEN {$quantityColumn} ELSE {$quantityColumn} * COALESCE({$unitsColumn}, 1) END";
    }

    public static function formatQty(float|int|string $qty): string
    {
        $value = (float) $qty;

        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 2);
    }
}
