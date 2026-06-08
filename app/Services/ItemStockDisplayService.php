<?php

namespace App\Services;

use App\Models\Item;

class ItemStockDisplayService
{
    public function __construct(private ItemPackagingNormalizer $normalizer) {}

    public function format(Item $item, ?float $pieces = null): array
    {
        $item->loadMissing(['packagings.packagingType', 'receivingPackaging']);

        $pieces = $pieces ?? (float) $item->current_stock;
        $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
        $normalized = $this->normalizer->normalizeItemPackagings($item, $packagingModels);

        $packagingPrices = $normalized->map(function ($row) {
            $pkg = $row['packaging'];

            return [
                'name' => $pkg->packagingType->name ?? 'Unit',
                'quantity_per_unit' => (int) $row['quantity_per_unit'],
            ];
        })->values()->all();

        $pkg = $item->packagings->first();
        $unitName = optional($item->receivingPackaging)->name
            ?? $pkg->packagingType->name
            ?? 'Unit';

        $formattedPieces = fmod($pieces, 1.0) === 0.0
            ? (string) (int) $pieces
            : number_format($pieces, 2);

        $bulkPack = collect($packagingPrices)->sortByDesc('quantity_per_unit')->first();
        $packSize = max(1, (int) ($bulkPack['quantity_per_unit'] ?? 1));
        $bulkName = $bulkPack['name'] ?? $unitName;

        if ($packSize <= 1 && (int) ($item->units_per_receiving_pack ?? 1) > 1) {
            $packSize = (int) $item->units_per_receiving_pack;
            $bulkName = $unitName;
        }

        $hasBulkStock = $packSize > 1;
        $bulkCount = $hasBulkStock ? (int) floor($pieces / $packSize) : 0;

        if ($hasBulkStock) {
            $stockDisplay = $formattedPieces . ' pcs · ' . $bulkCount . ' ' . $bulkName;
        } else {
            $stockDisplay = trim($formattedPieces . ' ' . ($pieces === 1.0 ? rtrim($unitName, 's') : $unitName));
        }

        $packagingBreakdown = collect($packagingPrices)
            ->sortBy('quantity_per_unit')
            ->map(function ($pkg) use ($pieces) {
                return $this->buildPackagingStockRow($pkg['name'], (int) $pkg['quantity_per_unit'], $pieces);
            })
            ->values();

        $hasBulkPackaging = $packagingBreakdown->contains(fn ($row) => ($row['quantity_per_unit'] ?? 1) > 1);

        if ($hasBulkPackaging) {
            $packagingBreakdown = $packagingBreakdown
                ->reject(fn ($row) => ($row['quantity_per_unit'] ?? 1) === 1)
                ->values();
        }

        $packagingBreakdown = $packagingBreakdown->all();

        return [
            'pieces' => $pieces,
            'formatted_pieces' => $formattedPieces,
            'unit_name' => $unitName,
            'stock_display' => $stockDisplay,
            'has_bulk_stock' => $hasBulkStock,
            'bulk_count' => $bulkCount,
            'bulk_name' => $bulkName,
            'pack_size' => $packSize,
            'packaging_breakdown' => $packagingBreakdown,
        ];
    }

    private function buildPackagingStockRow(string $name, int $quantityPerUnit, float $pieces): array
    {
        $qpu = max(1, $quantityPerUnit);
        $count = (int) floor($pieces / $qpu);
        $remainder = $qpu > 1 ? (int) round(fmod($pieces, $qpu)) : 0;

        return [
            'name' => $name,
            'quantity_per_unit' => $qpu,
            'count' => $count,
            'remainder_pieces' => $remainder,
            'formatted_count' => $this->formatPackagingCount($name, $count, $qpu, $remainder),
        ];
    }

    private function formatPackagingCount(string $name, int $count, int $quantityPerUnit, int $remainder): string
    {
        if ($quantityPerUnit <= 1) {
            return (string) $count;
        }

        if ($count > 0 && $remainder > 0) {
            return $count . ' ' . $name . ' · ' . $remainder . ' pcs';
        }

        if ($count > 0) {
            return $count . ' ' . $name;
        }

        if ($remainder > 0) {
            return '0 ' . $name . ' · ' . $remainder . ' pcs';
        }

        return '0 ' . $name;
    }
}
