<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceMaterial;

class ServiceConsumableService
{
    public function deductForSale(Sale $sale): void
    {
        if ($sale->consumables_deducted) {
            return;
        }

        $sale->load(['items.service']);

        foreach ($sale->items as $line) {
            $this->deductLine($line);
        }

        $sale->update(['consumables_deducted' => true]);
    }

    public function restoreForSale(Sale $sale): void
    {
        if (! $sale->consumables_deducted) {
            return;
        }

        $sale->load(['items.service']);

        foreach ($sale->items as $line) {
            $this->restoreLine($line);
        }

        $sale->update(['consumables_deducted' => false]);
    }

    private function deductLine(SaleItem $line): void
    {
        $service = $line->service;
        if (! $service) {
            return;
        }

        $unitsPerSale = (float) $service->consumable_units_per_unit;
        if ($unitsPerSale <= 0) {
            return;
        }

        $deduct = (float) $line->quantity * $unitsPerSale;

        if ($service->service_material_id) {
            $material = ServiceMaterial::find($service->service_material_id);
            if ($material) {
                $material->current_stock = max(0, (float) $material->current_stock - $deduct);
                $material->save();
            }

            return;
        }

        if (! $service->consumable_item_id) {
            return;
        }

        $item = Item::find($service->consumable_item_id);
        if (! $item) {
            return;
        }

        $item->current_stock = max(0, (float) $item->current_stock - $deduct);
        $item->save();
    }

    private function restoreLine(SaleItem $line): void
    {
        $service = $line->service;
        if (! $service) {
            return;
        }

        $unitsPerSale = (float) $service->consumable_units_per_unit;
        if ($unitsPerSale <= 0) {
            return;
        }

        $restore = (float) $line->quantity * $unitsPerSale;

        if ($service->service_material_id) {
            $material = ServiceMaterial::find($service->service_material_id);
            if ($material) {
                $material->current_stock = (float) $material->current_stock + $restore;
                $material->save();
            }

            return;
        }

        if (! $service->consumable_item_id) {
            return;
        }

        $item = Item::find($service->consumable_item_id);
        if (! $item) {
            return;
        }

        $item->current_stock += $restore;
        $item->save();
    }
}
