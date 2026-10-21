<?php

namespace App\Services;

use App\Models\ItemPackaging;
use App\Models\ItemPackagingUnitBarcode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BarcodeLookupService
{
    /**
     * @return list<string>
     */
    public function normalizeCandidates(string $raw): array
    {
        $code = trim($raw);
        if ($code === '') {
            return [];
        }

        $candidates = [$code];
        $digits = preg_replace('/\D+/', '', $code) ?? '';

        if ($digits !== '') {
            $candidates[] = $digits;
            if (strlen($digits) === 12) {
                $candidates[] = '0'.$digits;
            }
            if (strlen($digits) === 13 && str_starts_with($digits, '0')) {
                $candidates[] = substr($digits, 1);
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @param  callable(Builder): void  $scopeItem
     * @return array{packaging: ItemPackaging|null, source: string|null, scanned_barcode: string|null, candidates: Collection<int, ItemPackaging>}
     */
    public function resolve(string $raw, int $businessId, callable $scopeItem): array
    {
        $candidates = $this->normalizeCandidates($raw);
        if ($candidates === []) {
            return ['packaging' => null, 'source' => null, 'scanned_barcode' => null, 'candidates' => collect()];
        }

        $query = ItemPackaging::query()
            ->whereIn('barcode', $candidates)
            ->whereHas('item', function ($q) use ($businessId, $scopeItem) {
                $q->where('business_id', $businessId);
                $scopeItem($q);
            })
            ->with(['packagingType', 'item.category', 'item.receivingPackaging', 'item.packagings.packagingType']);

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return $this->resolveFromUnitBarcodes($candidates, $businessId, $scopeItem);
        }

        if ($matches->count() > 1) {
            return ['packaging' => null, 'source' => null, 'scanned_barcode' => null, 'candidates' => $matches];
        }

        $packaging = $matches->first();
        $source = $this->detectSource($packaging, $candidates);

        return [
            'packaging' => $packaging,
            'source' => $source,
            'scanned_barcode' => (string) $packaging->barcode,
            'candidates' => collect(),
        ];
    }

    /**
     * @param  list<string>  $candidates
     * @param  callable(Builder): void  $scopeItem
     * @return array{packaging: ItemPackaging|null, source: string|null, scanned_barcode: string|null, candidates: Collection<int, ItemPackaging>}
     */
    private function resolveFromUnitBarcodes(array $candidates, int $businessId, callable $scopeItem): array
    {
        $allowedPackagingIds = ItemPackaging::query()
            ->whereHas('item', function ($q) use ($businessId, $scopeItem) {
                $q->where('business_id', $businessId);
                $scopeItem($q);
            })
            ->pluck('id');

        $unitRows = ItemPackagingUnitBarcode::query()
            ->where('business_id', $businessId)
            ->whereIn('barcode', $candidates)
            ->whereIn('item_packaging_id', $allowedPackagingIds)
            ->with(['itemPackaging.packagingType', 'itemPackaging.item.category', 'itemPackaging.item.receivingPackaging', 'itemPackaging.item.packagings.packagingType'])
            ->get();

        if ($unitRows->isEmpty()) {
            return ['packaging' => null, 'source' => null, 'scanned_barcode' => null, 'candidates' => collect()];
        }

        $packagings = $unitRows->map(fn (ItemPackagingUnitBarcode $row) => $row->itemPackaging)->filter()->unique('id')->values();

        if ($packagings->count() > 1) {
            return ['packaging' => null, 'source' => null, 'scanned_barcode' => null, 'candidates' => $packagings];
        }

        $unit = $unitRows->first();
        $packaging = $unit->itemPackaging;

        return [
            'packaging' => $packaging,
            'source' => 'unit',
            'scanned_barcode' => (string) $unit->barcode,
            'candidates' => collect(),
        ];
    }

    /**
     * @param  list<string>  $candidates
     */
    private function detectSource(ItemPackaging $packaging, array $candidates): string
    {
        $barcode = (string) $packaging->barcode;
        if (strlen(preg_replace('/\D+/', '', $barcode) ?? '') >= 8) {
            return 'manufacturer';
        }

        return 'internal';
    }
}
