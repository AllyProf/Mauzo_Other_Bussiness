<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPackaging;
use Illuminate\Support\Collection;

class ItemPackagingNormalizer
{
    public function normalizeSellingRows(int $receivingPackagingId, array $sellingRows, Collection $packagingTypes): array
    {
        $rows = collect($sellingRows)
            ->filter(fn ($row) => ! empty($row['packaging_id']))
            ->map(function ($row) {
                $normalized = [
                    'packaging_id' => (int) $row['packaging_id'],
                    'quantity_per_unit' => max(1, (int) ($row['quantity_per_unit'] ?? 1)),
                ];

                if (array_key_exists('selling_price', $row) && $row['selling_price'] !== null && $row['selling_price'] !== '') {
                    $normalized['selling_price'] = (float) $row['selling_price'];
                }

                return $normalized;
            })
            ->values();

        if ($rows->count() <= 1) {
            return $rows->all();
        }

        $namesById = $packagingTypes->pluck('name', 'id')->map(fn ($name) => strtolower((string) $name));

        $inversion = $this->detectInversionFromRows($rows, $namesById);
        if (! $inversion) {
            return $rows->all();
        }

        return $rows->map(function ($row) use ($inversion, $receivingPackagingId, $namesById) {
            if ($row['packaging_id'] === $inversion['piece_packaging_id']) {
                $row['quantity_per_unit'] = 1;
            } elseif ($row['packaging_id'] === $inversion['bulk_packaging_id']) {
                $row['quantity_per_unit'] = $inversion['pack_size'];
            } elseif ($row['packaging_id'] === $receivingPackagingId && $inversion['pack_size'] > 1) {
                $row['quantity_per_unit'] = $inversion['pack_size'];
            } elseif ($this->isBulkPackagingName($namesById[$row['packaging_id']] ?? '')) {
                $row['quantity_per_unit'] = $inversion['pack_size'];
            }

            return $row;
        })->all();
    }

    /**
     * @param  Collection<int, ItemPackaging>  $packagings
     * @return Collection<int, array{packaging: ItemPackaging, quantity_per_unit: int}>
     */
    public function normalizeItemPackagings(Item $item, Collection $packagings): Collection
    {
        if ($packagings->count() <= 1) {
            return $packagings->map(fn (ItemPackaging $p) => [
                'packaging' => $p,
                'quantity_per_unit' => max(1, (int) $p->quantity_per_unit),
            ]);
        }

        $packagings->loadMissing('packagingType');
        $namesById = $packagings->mapWithKeys(fn (ItemPackaging $p) => [
            $p->packaging_id => strtolower($p->packagingType->name ?? ''),
        ]);

        $rows = $packagings->map(fn (ItemPackaging $p) => [
            'packaging_id' => (int) $p->packaging_id,
            'quantity_per_unit' => max(1, (int) $p->quantity_per_unit),
        ])->values();

        $inversion = $this->detectInversionFromRows($rows, $namesById);

        return $packagings->map(function (ItemPackaging $p) use ($inversion, $item, $namesById) {
            $qpu = max(1, (int) $p->quantity_per_unit);

            if ($inversion) {
                if ((int) $p->packaging_id === $inversion['piece_packaging_id']) {
                    $qpu = 1;
                } elseif ((int) $p->packaging_id === $inversion['bulk_packaging_id']) {
                    $qpu = $inversion['pack_size'];
                } elseif ((int) $p->packaging_id === (int) $item->receiving_packaging_id && $inversion['pack_size'] > 1) {
                    $qpu = $inversion['pack_size'];
                } elseif ($this->isBulkPackagingName($namesById[(int) $p->packaging_id] ?? '')) {
                    $qpu = $inversion['pack_size'];
                }
            }

            return [
                'packaging' => $p,
                'quantity_per_unit' => $qpu,
            ];
        });
    }

    public function resolveUnitsPerReceivingPack(int $receivingPackagingId, array $sellingRows, Collection $packagingTypes): int
    {
        $rows = $this->normalizeSellingRows($receivingPackagingId, $sellingRows, $packagingTypes);

        $pieces = collect($rows);
        $match = $pieces->firstWhere('packaging_id', $receivingPackagingId);

        return (int) ($match['quantity_per_unit'] ?? $pieces->max('quantity_per_unit') ?? 1);
    }

    public function effectiveUnitsPerReceivingPack(Item $item, Collection $packagings): int
    {
        $stored = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $normalized = $this->normalizeItemPackagings($item, $packagings);
        $maxPack = (int) $normalized->max('quantity_per_unit');

        return max($stored, $maxPack);
    }

    /**
     * @param  Collection<int, ItemPackaging>  $packagings
     */
    public function detectInversion(Item $item, Collection $packagings): ?array
    {
        if ($packagings->count() <= 1) {
            return null;
        }

        $packagings->loadMissing('packagingType');
        $namesById = $packagings->mapWithKeys(fn (ItemPackaging $p) => [
            $p->packaging_id => strtolower($p->packagingType->name ?? ''),
        ]);

        $rows = $packagings->map(fn (ItemPackaging $p) => [
            'packaging_id' => (int) $p->packaging_id,
            'quantity_per_unit' => max(1, (int) $p->quantity_per_unit),
        ])->values();

        return $this->detectInversionFromRows($rows, $namesById);
    }

    /**
     * Correct stock when receiving units were stored as pieces due to inverted packaging setup.
     *
     * @param  Collection<int, ItemPackaging>  $packagings
     */
    public function adjustStockPiecesForInversion(Item $item, Collection $packagings, float $stockPieces): float
    {
        $inversion = $this->detectInversion($item, $packagings);
        if (! $inversion || max(1, (int) $item->units_per_receiving_pack) !== 1) {
            return $stockPieces;
        }

        return $stockPieces * $inversion['pack_size'];
    }

    private function detectInversionFromRows(Collection $rows, Collection $namesById): ?array
    {
        $pieceRows = $rows->filter(fn ($row) => $this->isPiecePackagingName($namesById[$row['packaging_id']] ?? ''));
        $bulkRows = $rows->filter(fn ($row) => $this->isBulkPackagingName($namesById[$row['packaging_id']] ?? ''));

        foreach ($pieceRows as $pieceRow) {
            foreach ($bulkRows as $bulkRow) {
                if ($pieceRow['quantity_per_unit'] > 1 && $bulkRow['quantity_per_unit'] === 1) {
                    return [
                        'piece_packaging_id' => $pieceRow['packaging_id'],
                        'bulk_packaging_id' => $bulkRow['packaging_id'],
                        'pack_size' => $pieceRow['quantity_per_unit'],
                    ];
                }
            }
        }

        return null;
    }

    private function isPiecePackagingName(string $name): bool
    {
        return (bool) preg_match('/\b(piece|pieces|pcs|pc|each|single|unit)\b/i', $name);
    }

    private function isBulkPackagingName(string $name): bool
    {
        return (bool) preg_match('/\b(box|carton|pack|crate|bundle|bag|case|dozen|dz|tray|ream|sachet|can)\b/i', $name);
    }
}
