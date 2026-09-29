<?php

namespace App\Services;

use App\Models\PlatformBillingInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PlatformInvoiceDocumentService
{
    public function __construct(private PlatformSettingsService $settings) {}

    public function renderHtml(PlatformBillingInvoice $invoice): string
    {
        $invoice->loadMissing(['business', 'plan']);

        return view('admin.payments.invoice-document', $this->viewData($invoice))->render();
    }

    public function renderPdf(PlatformBillingInvoice $invoice): string
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->renderHtml($invoice));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(PlatformBillingInvoice $invoice): string
    {
        return 'Invoice-'.preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $invoice->invoice_number).'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(PlatformBillingInvoice $invoice): array
    {
        $s = $this->settings->all();
        $platform = (string) ($s['platform_name'] ?? 'Mauzo Link');
        $business = $invoice->business;

        $amount = (float) $invoice->amount;
        $vatPercent = (float) ($s['invoice_vat_percent'] ?? 0);
        $vatAmount = ! empty($s['invoice_charge_vat']) ? round($amount * $vatPercent / 100, 2) : 0.0;

        $description = $invoice->billingMonthLabel();
        $quantity = 1;
        $unitPrice = $amount;
        if ($invoice->is_manual) {
            $quantity = max(1, (int) $invoice->quantity);
            $unitPrice = $invoice->unit_price !== null ? (float) $invoice->unit_price : $amount / $quantity;
            $description = $invoice->description
                ?: trim(($invoice->plan?->name ? $invoice->plan->name.' plan' : $platform).' subscription — '.$quantity.' '.($quantity === 1 ? 'month' : 'months'));
        } elseif ($invoice->billing_model === 'profit_share') {
            $description .= ' — '.number_format((float) $invoice->share_percent, 1).'% of TZS '
                .number_format((float) $invoice->profit_amount, 0).' '
                .($invoice->profit_basis === 'gross_profit' ? 'gross profit' : 'net profit');
        } elseif ($invoice->plan?->name) {
            $description .= ' — '.$invoice->plan->name.' plan';
        }

        $recipientLines = array_values(array_filter([
            $business?->contact_person,
            $business?->name,
            preg_match('/^\d+$/', trim((string) $business?->address)) ? 'P.O.BOX '.trim($business->address).',' : $business?->address,
        ]));

        return [
            'invoice' => $invoice,
            'business' => $business,
            'company' => [
                'name' => (string) ($s['invoice_company_name'] ?? ''),
                'address_lines' => $this->lines($s['invoice_company_address'] ?? ''),
                'phone' => (string) ($s['invoice_company_phone'] ?? ''),
                'tin' => (string) ($s['invoice_company_tin'] ?? ''),
                'website' => (string) ($s['invoice_company_website'] ?? ''),
                'logo' => $this->logoDataUri((string) ($s['invoice_logo'] ?? '')),
                'watermark' => $this->watermarkDataUri((string) ($s['invoice_logo'] ?? '')),
            ],
            'payment' => [
                'intro' => (string) ($s['invoice_payment_intro'] ?? ''),
                'methods' => $this->paymentMethods($s),
            ],
            'recipientLines' => $recipientLines,
            'recipientRegion' => $business?->region ? mb_strtoupper($business->region).'.' : null,
            'purpose' => str_replace('{platform_name}', $platform, (string) ($s['invoice_purpose'] ?? '')),
            'platform' => $platform,
            'lines' => [[
                'description' => $description,
                'qty' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $amount,
            ]],
            'subtotal' => $amount,
            'vatPercent' => $vatPercent,
            'vatAmount' => $vatAmount,
            'total' => $amount + $vatAmount,
            'footerLines' => $this->lines($s['invoice_footer'] ?? ''),
            'invoiceDate' => ($invoice->created_at ?? now())->format('d-m-Y'),
        ];
    }

    /**
     * @return list<string>
     */
    private function lines(mixed $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text) ?: []), fn ($line) => $line !== ''));
    }

    /**
     * @return list<array{provider: string, account_name: string, number_label: string, number: string}>
     */
    private function paymentMethods(array $s): array
    {
        $methods = is_array($s['invoice_payment_methods'] ?? null) ? $s['invoice_payment_methods'] : [];

        if ($methods === [] && (! empty($s['invoice_account_name']) || ! empty($s['invoice_account_number']))) {
            $methods = [[
                'provider' => '',
                'account_name' => $s['invoice_account_name'] ?? '',
                'number_label' => 'A/C no.',
                'number' => $s['invoice_account_number'] ?? '',
            ]];
        }

        return array_values(array_map(fn ($method) => [
            'provider' => trim((string) ($method['provider'] ?? '')),
            'account_name' => trim((string) ($method['account_name'] ?? '')),
            'number_label' => trim((string) ($method['number_label'] ?? '')),
            'number' => trim((string) ($method['number'] ?? '')),
        ], $methods));
    }

    /**
     * Dompdf barely renders CSS opacity on transparent PNGs, so the faded logo is baked into the image.
     */
    private function watermarkDataUri(string $path, float $strength = 0.10): ?string
    {
        if ($path === '' || ! function_exists('imagecreatefromstring') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $source = @imagecreatefromstring(Storage::disk('public')->get($path));
        if (! $source) {
            return null;
        }

        $source = $this->cropFrame($source);
        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min(600, $width);
        $targetHeight = max(1, (int) round($height * $targetWidth / $width));

        $image = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        for ($x = 0; $x < $targetWidth; $x++) {
            for ($y = 0; $y < $targetHeight; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($alpha >= 127 || ($r > 245 && $g > 245 && $b > 245)) {
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, 255, 255, 255, 127));
                    continue;
                }

                $newAlpha = (int) round(127 - (127 - $alpha) * $strength);
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $newAlpha));
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Crops a thin border drawn around the logo so only the artwork remains in the watermark.
     */
    private function cropFrame(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $isInk = function (int $x, int $y) use ($image): bool {
            $rgba = imagecolorat($image, $x, $y);
            if ((($rgba >> 24) & 0x7F) > 100) {
                return false;
            }

            return ((($rgba >> 16) & 0xFF) + (($rgba >> 8) & 0xFF) + ($rgba & 0xFF)) < 600;
        };
        $columnIsLine = function (int $x) use ($height, $isInk): bool {
            $ink = 0;
            for ($y = 0; $y < $height; $y++) {
                $ink += $isInk($x, $y) ? 1 : 0;
            }

            return $ink >= $height * 0.7;
        };
        $rowIsLine = function (int $y) use ($width, $isInk): bool {
            $ink = 0;
            for ($x = 0; $x < $width; $x++) {
                $ink += $isInk($x, $y) ? 1 : 0;
            }

            return $ink >= $width * 0.7;
        };

        $scanX = (int) max(1, $width * 0.15);
        $scanY = (int) max(1, $height * 0.15);
        $left = 0;
        $right = $width - 1;
        $top = 0;
        $bottom = $height - 1;

        for ($x = 0; $x < $scanX; $x++) {
            if ($columnIsLine($x)) {
                $left = $x + 1;
            }
        }
        for ($x = $width - 1; $x >= $width - $scanX; $x--) {
            if ($columnIsLine($x)) {
                $right = $x - 1;
            }
        }
        for ($y = 0; $y < $scanY; $y++) {
            if ($rowIsLine($y)) {
                $top = $y + 1;
            }
        }
        for ($y = $height - 1; $y >= $height - $scanY; $y--) {
            if ($rowIsLine($y)) {
                $bottom = $y - 1;
            }
        }

        if ($left === 0 && $top === 0 && $right === $width - 1 && $bottom === $height - 1) {
            return $image;
        }

        $pad = 2;
        $cropped = imagecrop($image, [
            'x' => min($width - 1, $left + $pad),
            'y' => min($height - 1, $top + $pad),
            'width' => max(1, $right - $left - $pad * 2),
            'height' => max(1, $bottom - $top - $pad * 2),
        ]);

        return $cropped ?: $image;
    }

    private function logoDataUri(string $path): ?string
    {
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
