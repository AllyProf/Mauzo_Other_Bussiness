<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'supplier_id',
        'branch_transfer_id',
        'user_id',
        'reference_no',
        'received_date',
        'total_amount',
        'notes',
        'status',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branchTransfer()
    {
        return $this->belongsTo(BranchTransfer::class);
    }

    public function isBranchSupply(): bool
    {
        return (int) ($this->branch_transfer_id ?? 0) > 0;
    }

    public function sourceLabel(): string
    {
        if ($this->isBranchSupply()) {
            $this->loadMissing('branchTransfer.fromBranch');

            return __('receivings.branch_supply_from', [
                'branch' => $this->branchTransfer?->fromBranch?->name ?? __('branch_transfers.from'),
            ]);
        }

        return $this->supplier?->name ?? __('tables.misc.not_available');
    }

    /**
     * Cancel is allowed only while none of the received pieces have been sold yet.
     */
    public function canBeCancelled(): bool
    {
        if (($this->status ?? 'completed') === 'cancelled') {
            return false;
        }

        $this->loadMissing(['items.item.packagings']);

        foreach ($this->items as $receivingItem) {
            $item = $receivingItem->item;
            if (! $item) {
                continue;
            }

            $unitsPerPack = max(1, (int) ($item->units_per_receiving_pack ?? $item->packagings->first()?->quantity_per_unit ?? 1));
            $qtyMode = $receivingItem->qty_mode ?? 'pkg';
            $stockToRemove = $qtyMode === 'piece'
                ? (float) $receivingItem->quantity
                : (float) $receivingItem->quantity * $unitsPerPack;

            if ($stockToRemove <= 0) {
                continue;
            }

            if ((float) $item->current_stock + 0.00001 < $stockToRemove) {
                return false;
            }
        }

        return true;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ReceivingItem::class);
    }
}
