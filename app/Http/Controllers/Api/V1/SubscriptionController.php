<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PlatformBillingInvoice;
use App\Services\Api\ApiSubscriptionSummaryService;
use App\Services\PlatformBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function __construct(
        private ApiSubscriptionSummaryService $summary,
        private PlatformBillingService $billing,
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $sub = $this->summary->forBusiness($business);

        return $this->success([
            'is_active' => $sub['is_active'],
            'expiry_date' => $sub['expiry_date'],
            'expiring_soon' => $sub['expiring_soon'],
            'days_remaining' => $sub['days_remaining'],
            'blocked_reason' => $sub['blocked_reason'],
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business) {
            return $this->error('No business context.', 422);
        }

        $overview = $this->billing->subscriptionOverview($business);

        $items = collect($overview['invoices'] ?? [])
            ->map(fn (PlatformBillingInvoice $invoice) => $this->summary->invoicePayload($invoice))
            ->values()
            ->all();

        return $this->success(['invoices' => $items]);
    }

    public function showInvoice(Request $request, PlatformBillingInvoice $invoice): JsonResponse
    {
        $business = $this->apiBusiness();
        if (! $business || (int) $invoice->business_id !== (int) $business->id) {
            return $this->forbidden();
        }

        $settings = platform_settings();

        return $this->success([
            'invoice' => $this->summary->invoicePayload($invoice),
            'payment_instructions' => trim((string) ($settings['billing_payment_instructions'] ?? '')),
            'support_phone' => trim((string) ($settings['support_phone'] ?? '')),
            'support_whatsapp' => trim((string) ($settings['support_whatsapp'] ?? '')),
        ]);
    }
}
