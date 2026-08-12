<?php

namespace App\Services;

use App\Models\ItemPackaging;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ItemBarcodeService
{
    /**
     * Stable scannable code for a selling packaging.
     * Format: ML{businessId 4}{itemPackagingId 8} e.g. ML001000000123
     */
    public function codeFor(ItemPackaging $packaging, int $businessId): string
    {
        return sprintf('ML%04d%08d', $businessId, (int) $packaging->id);
    }

    public function ensureBarcode(ItemPackaging $packaging, int $businessId): string
    {
        if (filled($packaging->barcode)) {
            return (string) $packaging->barcode;
        }

        $code = $this->codeFor($packaging, $businessId);
        $packaging->forceFill(['barcode' => $code])->save();

        return $code;
    }

    /**
     * Ensure every packaging on the item has a barcode (preserves existing codes when packaging_id matches).
     *
     * @param  \Illuminate\Support\Collection<int, ItemPackaging>|iterable<ItemPackaging>  $previousByPackagingId
     */
    public function assignMissingBarcodes(int $businessId, iterable $packagings, $previousByPackagingId = []): void
    {
        $previous = collect($previousByPackagingId);

        foreach ($packagings as $packaging) {
            if (filled($packaging->barcode)) {
                continue;
            }

            $prior = $previous->get((int) $packaging->packaging_id);
            if ($prior && filled($prior->barcode ?? null)) {
                $packaging->forceFill(['barcode' => $prior->barcode])->save();
                continue;
            }

            $this->ensureBarcode($packaging, $businessId);
        }
    }

    public function pngBase64(string $barcode, int $widthFactor = 2, int $height = 60): string
    {
        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, $widthFactor, $height);

        return base64_encode($png);
    }

    public function qrPngBase64(string $barcode, int $size = 140): string
    {
        $scale = max(3, min(12, (int) round($size / 25)));

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => $scale,
            'outputBase64' => false,
        ]);

        $png = (new QRCode($options))->render($barcode);

        return base64_encode($png);
    }
}
