<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PlatformBillingInvoice;
use App\Services\Api\ApiSubscriptionSummaryService;
use App\Services\Api\ApiSubscriptionUpgradeService;
use App\Services\PlatformBillingService;
use App\Services\PlatformInvoiceDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends ApiController
{
    public function __construct(
        private ApiSubscriptionSummaryService $summary,
        private ApiSubscriptionUpgradeService $upgrade,
        private PlatformBillingService $billing,
    ) {
    }

    /**
     * Mobile equivalent of web GET /subscription/upgrade.
     */
    public function upgradeScreen(Request $request): JsonResponse
    {
        $business = $this->apiBusiness();
        $user = $request->user();

        if (! $business || ! $user) {
            return $this->error('No business context.', 422);
        }

        if (in_array($user->role, ['super_admin', 'platform_staff'], true)) {
            return $this->forbidden('Platform admin accounts cannot use the mobile API.');
        }

        return $this->success(
            $this->upgrade->payload($business, $user),
            'Subscription upgrade screen'
        );
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
            ->map(fn (PlatformBillingInvoice $invoice) => $this->upgrade->invoicePayload($invoice))
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
            'invoice' => $this->upgrade->invoicePayload($invoice),
            ...$this->upgrade->paymentOptionsPayload(),
        ]);
    }

    public function invoicePdf(
        Request $request,
        PlatformBillingInvoice $invoice,
        PlatformInvoiceDocumentService $documents,
    ): Response {
        $business = $this->apiBusiness();
        if (! $business || (int) $invoice->business_id !== (int) $business->id) {
            return response('Forbidden', 403);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($documents->renderPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$documents->filename($invoice).'"',
        ]);
    }

    public function paymentOptions(Request $request): JsonResponse
    {
        if (! $this->apiBusiness()) {
            return $this->error('No business context.', 422);
        }

        return $this->success($this->upgrade->paymentOptionsPayload());
    }
}
