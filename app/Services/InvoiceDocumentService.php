<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Sale;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceDocumentService
{
    public function viewData(Sale $sale, ?Business $business = null, ?Branch $branch = null): array
    {
        $sale->loadMissing(['items.item', 'items.service', 'user', 'customer', 'business', 'payments']);
        $business = $business ?? $sale->business ?? Business::find($sale->business_id);
        $branch = $branch ?? active_branch_service()->activeBranch();

        $balanceDue = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
        $statusLabel = match ($sale->payment_status) {
            'paid' => 'PAID',
            'partial' => 'PARTIALLY PAID',
            'debt' => 'CREDIT / UNPAID',
            'pending' => 'UNPAID',
            default => strtoupper((string) $sale->payment_status),
        };
        $statusClass = match ($sale->payment_status) {
            'paid' => 'success',
            'partial' => 'info',
            'debt', 'pending' => 'danger',
            default => 'secondary',
        };

        $paymentReceiveDetails = collect($business?->paymentMethodsConfig() ?? [])
            ->filter(fn ($m) => ! empty($m['enabled']))
            ->flatMap(function ($method) {
                return collect($method['provider_accounts'] ?? [])
                    ->filter(fn ($account) => ! empty($account['pay_number']) || ! empty($account['account_name']))
                    ->map(fn ($account) => [
                        'method_key' => $method['key'],
                        'method_label' => $method['label'],
                        'platform' => $account['name'],
                        'pay_number' => $account['pay_number'] ?? '',
                        'account_name' => $account['account_name'] ?? '',
                    ]);
            })
            ->values();

        $vatBreakdown = $business?->invoiceVatBreakdown((float) $sale->total_amount);
        $logoDataUri = $business?->invoiceLogoDataUri();

        return compact(
            'sale',
            'business',
            'branch',
            'balanceDue',
            'statusLabel',
            'statusClass',
            'paymentReceiveDetails',
            'vatBreakdown',
            'logoDataUri',
        );
    }

    public function renderHtml(Sale $sale, ?Business $business = null, ?Branch $branch = null): string
    {
        return view('invoices.document-html', $this->viewData($sale, $business, $branch))->render();
    }

    public function renderPdf(Sale $sale, ?Business $business = null, ?Branch $branch = null): string
    {
        $html = $this->renderHtml($sale, $business, $branch);

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
