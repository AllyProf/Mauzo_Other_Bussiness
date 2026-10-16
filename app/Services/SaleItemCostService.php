<?php

namespace App\Services;

use App\Models\Item;
use App\Models\SaleItem;

class SaleItemCostService
{
    /**
     * Normalized cost per base stock piece for a packaging row (stock reports, valuations).
     */
    public function costPerPiece(Item $item, ?\App\Models\ItemPackaging $packaging = null, float $sellValueHint = 0): float
    {
        $item->loadMissing(['packagings.packagingType']);

        $packaging = $packaging ?? $item->packagings->sortBy('quantity_per_unit')->first();
        if (! $packaging) {
            return 0.0;
        }

        $qpu = max(1, (int) $packaging->quantity_per_unit);
        $packCost = (float) ($packaging->cost_price ?? 0);
        if ($packCost <= 0) {
            return 0.0;
        }

        return $this->resolveCostPerPiece($item, $packaging, $packCost, $qpu, 1.0, $sellValueHint);
    }

    /**
     * Cost of goods for one sale line (packaging-aware, piece-normalized when bulk cost is on a piece line).
     */
    public function lineCostOfGoods(SaleItem $line): float
    {
        if ($line->service_id) {
            return 0.0;
        }

        $qty = (float) $line->quantity;
        if ($qty <= 0) {
            return 0.0;
        }

        $line->loadMissing(['item.packagings.packagingType', 'itemPackaging.packagingType']);

        $item = $line->item;
        if (! $item instanceof Item) {
            return 0.0;
        }

        $packaging = $line->itemPackaging;
        if (! $packaging) {
            $packaging = $item->packagings->sortBy('quantity_per_unit')->first();
        }

        $qpu = max(1, (int) ($packaging?->quantity_per_unit ?? 1));
        $packCost = (float) ($line->cost_price ?? $packaging?->cost_price ?? 0);
        if ($packCost <= 0) {
            $fallback = $item->packagings->sortBy('quantity_per_unit')->first();
            $packCost = (float) ($fallback?->cost_price ?? 0);
            $packaging = $packaging ?? $fallback;
            $qpu = max(1, (int) ($packaging?->quantity_per_unit ?? 1));
        }

        if ($packCost <= 0) {
            return 0.0;
        }

        $pieces = (float) $item->stockUnitsForPackaging((int) $line->quantity, $packaging);
        $costPerPiece = $this->resolveCostPerPiece($item, $packaging, $packCost, $qpu, $pieces, (float) ($line->subtotal ?? 0));

        return round($costPerPiece * $pieces, 2);
    }

    private function resolveCostPerPiece(
        Item $item,
        $packaging,
        float $packCost,
        int $qpu,
        float $pieces,
        float $lineSubtotal
    ): float {
        $bulkPack = $item->packagings->sortByDesc('quantity_per_unit')->first();
        $bulkQpu = max(1, (int) ($bulkPack?->quantity_per_unit ?? 1));
        $bulkCost = (float) ($bulkPack?->cost_price ?? 0);

        if ($bulkQpu > 1 && $bulkCost > 0 && $qpu === 1 && $packCost >= $bulkCost * 0.95) {
            return $bulkCost / $bulkQpu;
        }

        if ($qpu > 1) {
            return $packCost / $qpu;
        }

        if ($pieces > 1.0001 && $lineSubtotal > 0) {
            $avgSell = $lineSubtotal / max($pieces, 1);
            if ($packCost > $lineSubtotal && $packCost > $avgSell * 2) {
                if ($bulkQpu > 1 && $bulkCost > 0) {
                    return $bulkCost / $bulkQpu;
                }

                return $lineSubtotal / $pieces;
            }
        }

        return $packCost;
    }
}
