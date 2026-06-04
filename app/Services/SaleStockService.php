<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\SaleItem;

class SaleStockService
{
    public function deductForSale(Sale $sale): void
    {
        if ($sale->stock_deducted) {
            app(ServiceConsumableService::class)->deductForSale($sale);

            return;
        }

        $sale->load(['items.item', 'items.itemPackaging']);

        foreach ($sale->items as $saleItem) {
            if ($saleItem->service_id) {
                continue;
            }

            $item = $saleItem->item;
            if (! $item) {
                continue;
            }

            $deduct = $item->stockUnitsForPackaging(
                (int) $saleItem->quantity,
                $saleItem->itemPackaging
            );

            $item->current_stock = max(0, (float) $item->current_stock - $deduct);
            $item->save();
        }

        $sale->update(['stock_deducted' => true]);
        app(ServiceConsumableService::class)->deductForSale($sale->fresh());
        $this->refreshShiftTotals($sale);
    }

    public function restoreForSale(Sale $sale): void
    {
        if (! $sale->stock_deducted) {
            return;
        }

        $sale->load(['items.item', 'items.itemPackaging']);

        foreach ($sale->items as $saleItem) {
            if ($saleItem->service_id) {
                continue;
            }

            $item = $saleItem->item;
            if (! $item) {
                continue;
            }

            $restore = $item->stockUnitsForPackaging(
                (int) $saleItem->quantity,
                $saleItem->itemPackaging
            );

            $item->current_stock += $restore;
            $item->save();
        }

        $sale->update(['stock_deducted' => false]);
        app(ServiceConsumableService::class)->restoreForSale($sale->fresh());
        $this->refreshShiftTotals($sale);
    }

    public function deductInvoiceIfPaid(Sale $sale): void
    {
        $source = $sale->sale_source ?? 'pos';
        if (! in_array($source, ['invoice', 'service_invoice'], true)) {
            return;
        }

        if ((float) $sale->amount_paid <= 0 && in_array($sale->payment_status, ['pending', 'debt'], true)) {
            return;
        }

        if ((float) $sale->amount_paid > 0 || $sale->payment_status === 'paid') {
            $this->deductForSale($sale);
            app(ServiceConsumableService::class)->deductForSale($sale->fresh());
        }
    }

    public function assertInvoiceStockAvailable(Sale $sale, ?Shift $shift = null): void
    {
        if ($sale->stock_deducted) {
            return;
        }

        $sale->load(['items.item', 'items.itemPackaging']);
        $stockContext = $this->shiftStockContext($shift);

        foreach ($sale->items as $saleItem) {
            if ($saleItem->service_id) {
                continue;
            }

            $item = $saleItem->item;
            if (! $item) {
                continue;
            }

            $stockNeeded = $item->stockUnitsForPackaging(
                (int) $saleItem->quantity,
                $saleItem->itemPackaging
            );
            $available = $this->availableStockForShift($item, $shift, $stockContext);
            if ($stockNeeded > $available) {
                throw new \InvalidArgumentException(
                    "Not enough stock for {$item->name}. Available: {$available} pieces."
                );
            }
        }
    }

    public function shiftStockContext(?Shift $openShift): array
    {
        if (! $openShift) {
            return ['opening' => [], 'sold' => []];
        }

        $opening = ShiftStockCheck::where('shift_id', $openShift->id)
            ->where('check_type', 'opening')
            ->pluck('counted_stock', 'item_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $sold = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('shift_id', $openShift->id)->where('payment_status', '!=', 'cancelled'))
            ->leftJoin('item_packagings', 'sale_items.item_packaging_id', '=', 'item_packagings.id')
            ->selectRaw('sale_items.item_id, SUM(sale_items.quantity * COALESCE(NULLIF(item_packagings.quantity_per_unit, 0), 1)) as total_pieces')
            ->groupBy('sale_items.item_id')
            ->pluck('total_pieces', 'item_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return ['opening' => $opening, 'sold' => $sold];
    }

    public function availableStockForShift(Item $item, ?Shift $openShift, array $stockContext): float
    {
        $current = (float) $item->current_stock;

        if (! $openShift || ! isset($stockContext['opening'][$item->id])) {
            return max(0, $current);
        }

        $opening = (float) $stockContext['opening'][$item->id];
        $sold = (float) ($stockContext['sold'][$item->id] ?? 0);
        $shiftBased = max(0, $opening - $sold);

        // Honour receivings/adjustments during the shift — never cap below live system stock.
        return max($shiftBased, max(0, $current));
    }

    private function refreshShiftTotals(Sale $sale): void
    {
        if ($sale->shift_id) {
            Shift::find($sale->shift_id)?->refreshTotals();
        }
    }
}
