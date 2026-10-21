<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\ItemPackagingUnitBarcode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemBarcodeLinkService
{
    public function __construct(private BarcodeLookupService $barcodeLookup)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{packaging: ItemPackaging, mode: 'primary'|'piece', barcode: string}
     */
    public function link(int $businessId, array $input, ?int $itemIdFromRoute = null): array
    {
        $itemId = (int) ($itemIdFromRoute ?? $input['item_id'] ?? 0);
        $packagingRowId = (int) ($input['item_packaging_id'] ?? $input['packaging_id'] ?? 0);
        $barcode = $this->extractBarcode($input);

        if ($itemId <= 0 || $packagingRowId <= 0 || $barcode === '') {
            throw ValidationException::withMessages([
                'item_id' => $itemId <= 0 ? ['Item is required.'] : [],
                'item_packaging_id' => $packagingRowId <= 0 ? ['Sell packaging is required.'] : [],
                'barcode' => $barcode === '' ? ['Barcode is required.'] : [],
            ]);
        }

        $item = Item::query()
            ->where('business_id', $businessId)
            ->whereKey($itemId)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => ['Item not found in this business.'],
            ]);
        }

        $packaging = ItemPackaging::query()
            ->where('item_id', $item->id)
            ->whereKey($packagingRowId)
            ->with('packagingType')
            ->first();

        if (! $packaging) {
            throw ValidationException::withMessages([
                'item_packaging_id' => ['Sell unit not found for this item.'],
            ]);
        }

        $asPiece = $this->shouldLinkAsPiece($input);

        if ($asPiece) {
            if ($this->barcodeTakenByOtherUnit($businessId, $barcode, $packaging)) {
                throw ValidationException::withMessages([
                    'barcode' => ['This barcode is already linked to another item or sell unit.'],
                ]);
            }
        } elseif ($this->barcodeTakenByOther($businessId, $barcode, $packaging->id)) {
            throw ValidationException::withMessages([
                'barcode' => ['This barcode is already linked to another item or sell unit.'],
            ]);
        }

        return DB::transaction(function () use ($businessId, $item, $packaging, $barcode, $asPiece) {
            if ($asPiece) {
                if ($this->isStoreLabelBarcode($packaging, $barcode)) {
                    return [
                        'packaging' => $packaging->fresh(['packagingType', 'unitBarcodes']),
                        'mode' => 'piece',
                        'barcode' => $barcode,
                        'duplicate' => true,
                    ];
                }

                if ($this->unitBarcodeExists($businessId, (int) $packaging->id, $barcode)) {
                    return [
                        'packaging' => $packaging->fresh(['packagingType', 'unitBarcodes']),
                        'mode' => 'piece',
                        'barcode' => $barcode,
                        'duplicate' => true,
                    ];
                }

                ItemPackagingUnitBarcode::create([
                    'business_id' => $businessId,
                    'item_id' => $item->id,
                    'item_packaging_id' => $packaging->id,
                    'barcode' => $this->canonicalBarcode($barcode),
                ]);

                return [
                    'packaging' => $packaging->fresh(['packagingType', 'unitBarcodes']),
                    'mode' => 'piece',
                    'barcode' => $barcode,
                    'duplicate' => false,
                ];
            }

            if ((string) $packaging->barcode !== $barcode) {
                $packaging->forceFill(['barcode' => $barcode])->save();
            }

            return [
                'packaging' => $packaging->fresh(['packagingType', 'unitBarcodes']),
                'mode' => 'primary',
                'barcode' => $barcode,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function extractBarcode(array $input): string
    {
        foreach (['barcode', 'code', 'manufacturer_barcode'] as $key) {
            if (! empty($input[$key])) {
                return trim((string) $input[$key]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function shouldLinkAsPiece(array $input): bool
    {
        if (($input['link_type'] ?? '') === 'piece') {
            return true;
        }

        if (filter_var($input['unit_barcode'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return false;
    }

    /**
     * Primary (case) barcode — must not collide with other sell units.
     */
    private function barcodeTakenByOther(int $businessId, string $barcode, int $packagingId): bool
    {
        $candidates = $this->barcodeLookup->normalizeCandidates($barcode);
        if ($candidates === []) {
            return false;
        }

        if (ItemPackaging::query()
            ->whereIn('barcode', $candidates)
            ->whereHas('item', fn ($q) => $q->where('business_id', $businessId))
            ->where('id', '!=', $packagingId)
            ->exists()) {
            return true;
        }

        return ItemPackagingUnitBarcode::query()
            ->where('business_id', $businessId)
            ->whereIn('barcode', $candidates)
            ->where('item_packaging_id', '!=', $packagingId)
            ->exists();
    }

    /**
     * Per-piece factory scan — store label on this row may stay; only block other rows.
     */
    private function barcodeTakenByOtherUnit(int $businessId, string $barcode, ItemPackaging $packaging): bool
    {
        $candidates = $this->barcodeLookup->normalizeCandidates($barcode);
        if ($candidates === []) {
            return false;
        }

        if (ItemPackaging::query()
            ->whereIn('barcode', $candidates)
            ->whereHas('item', fn ($q) => $q->where('business_id', $businessId))
            ->where('id', '!=', $packaging->id)
            ->exists()) {
            return true;
        }

        return ItemPackagingUnitBarcode::query()
            ->where('business_id', $businessId)
            ->whereIn('barcode', $candidates)
            ->where('item_packaging_id', '!=', $packaging->id)
            ->exists();
    }

    private function unitBarcodeExists(int $businessId, int $packagingId, string $barcode): bool
    {
        $candidates = $this->barcodeLookup->normalizeCandidates($barcode);
        if ($candidates === []) {
            return false;
        }

        return ItemPackagingUnitBarcode::query()
            ->where('business_id', $businessId)
            ->where('item_packaging_id', $packagingId)
            ->whereIn('barcode', $candidates)
            ->exists();
    }

    private function isStoreLabelBarcode(ItemPackaging $packaging, string $barcode): bool
    {
        $primary = trim((string) $packaging->barcode);
        if ($primary === '') {
            return false;
        }

        foreach ($this->barcodeLookup->normalizeCandidates($barcode) as $candidate) {
            foreach ($this->barcodeLookup->normalizeCandidates($primary) as $primaryCandidate) {
                if ($candidate === $primaryCandidate) {
                    return true;
                }
            }
        }

        return false;
    }

    private function canonicalBarcode(string $barcode): string
    {
        $trimmed = trim($barcode);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits !== '' && strlen($digits) >= 8) {
            return $digits;
        }

        return $trimmed;
    }
}
