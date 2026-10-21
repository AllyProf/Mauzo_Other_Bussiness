<?php

namespace App\Services\Api;

use App\Models\Business;
use App\Models\Plan;
use App\Models\PlatformBillingInvoice;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Services\PlatformBillingService;
use Illuminate\Support\Collection;

class ApiSubscriptionUpgradeService
{
    public function __construct(
        private ApiSubscriptionSummaryService $summary,
        private PlatformBillingService $billing,
        private PlanFeatureService $planFeatures,
    ) {
    }

    /**
     * Same data as web GET /subscription/upgrade (for mobile subscription screen).
     *
     * @return array<string, mixed>
     */
    public function payload(Business $business, User $user): array
    {
        $business->loadMissing('plan');
        $overview = $this->billing->subscriptionOverview($business);
        $renewalNotice = subscription_renewal_notice_for($business, true);

        $currentFee = $overview['current_fee'] ?? [];
        $renewalFee = $overview['renewal_fee'] ?? [];
        $plan = $overview['plan'] ?? $business->plan;
        $isFreePlan = $business->isFreeFixedRenewal();

        $monthlyDue = $business->usesProfitShareBilling()
            ? (float) ($currentFee['amount'] ?? 0)
            : (float) ($currentFee['monthly_equivalent'] ?? $currentFee['amount'] ?? 0);

        $disabledKeys = $this->planFeatures->disabledFeaturesForBusiness($business);

        return [
            'subscription' => $this->summary->forBusiness($business),
            'renewal_notice' => $this->renewalNoticePayload($renewalNotice),
            'current_plan' => $this->currentPlanPayload(
                $business,
                $plan,
                $overview,
                $currentFee,
                $isFreePlan,
                $monthlyDue,
                $renewalFee,
                $disabledKeys,
            ),
            'available_plans' => Plan::query()->orderBy('price')->get()->map(
                fn (Plan $p) => $this->planCatalogPayload($p, $business)
            )->values()->all(),
            'invoices' => $this->invoiceCollectionPayload($overview['invoices'] ?? collect()),
            'payment_options' => $this->paymentOptionsPayload(),
            'meta' => [
                'currency' => 'TZS',
                'upgrade_managed_by' => 'platform_admin',
                'contact_after_payment' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentOptionsPayload(): array
    {
        return [
            'payment_intro' => trim((string) platform_settings('invoice_payment_intro')),
            'payment_instructions' => trim((string) platform_settings('payment_instructions')),
            'payment_methods' => platform_invoice_payment_methods(),
            'support_phone' => trim((string) platform_settings('support_phone')),
            'support_whatsapp' => trim((string) platform_settings('support_whatsapp')),
            'support_email' => trim((string) platform_settings('support_email')),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $renewalNotice
     * @return array<string, mixed>|null
     */
    private function renewalNoticePayload(?array $renewalNotice): ?array
    {
        if (! $renewalNotice) {
            return null;
        }

        $payload = [
            'notice_type' => $renewalNotice['notice_type'] ?? 'expiring',
            'days_left' => $renewalNotice['days_left'] ?? null,
            'expiry_date' => $renewalNotice['expiry_date']?->toDateString(),
            'plan_name' => $renewalNotice['plan_name'] ?? null,
            'is_free_renewal' => (bool) ($renewalNotice['is_free_renewal'] ?? false),
            'renewal_amount' => isset($renewalNotice['renewal_amount'])
                ? (float) $renewalNotice['renewal_amount']
                : null,
        ];

        if (! empty($renewalNotice['unpaid_invoice']) && $renewalNotice['unpaid_invoice'] instanceof PlatformBillingInvoice) {
            $payload['unpaid_invoice'] = $this->invoicePayload($renewalNotice['unpaid_invoice']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $currentFee
     * @param  array<string, mixed>  $renewalFee
     * @param  list<string>  $disabledKeys
     * @return array<string, mixed>
     */
    private function currentPlanPayload(
        Business $business,
        ?Plan $plan,
        array $overview,
        array $currentFee,
        bool $isFreePlan,
        float $monthlyDue,
        array $renewalFee,
        array $disabledKeys,
    ): array {
        $latestInvoice = $overview['latest_invoice'] ?? null;

        return [
            'id' => $plan?->id,
            'name' => $plan?->name,
            'is_current' => true,
            'is_free_plan' => $isFreePlan,
            'billing_model' => $plan?->billing_model,
            'billing_model_label' => $plan?->billingModelLabel(),
            'billing_summary' => $plan?->billingSummary(),
            'business_billing_summary' => $business->billingSummary(),
            'expiry_date' => $business->expiry_date?->toDateString(),
            'account_status' => $business->statusLabel(),
            'amount_due' => [
                'monthly' => $monthlyDue,
                'renewal' => (float) ($renewalFee['amount'] ?? 0),
                'monthly_label' => $currentFee['label'] ?? null,
                'renewal_label' => $renewalFee['detail'] ?? $renewalFee['label'] ?? null,
            ],
            'limits' => [
                'max_items' => $plan?->max_items,
                'max_items_label' => $plan?->formatLimit($plan?->max_items),
                'max_users' => $plan?->max_users,
                'max_users_label' => $plan?->formatLimit($plan?->max_users),
                'max_branches' => $plan?->max_branches,
                'max_business_types' => $plan?->max_business_types,
                'storage_label' => $plan?->formatStorageLimit(),
            ],
            'latest_invoice' => $latestInvoice instanceof PlatformBillingInvoice
                ? $this->invoicePayload($latestInvoice)
                : null,
            'disabled_features' => collect($disabledKeys)->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->planFeatures->label($key),
            ])->values()->all(),
            'all_modules_enabled' => $disabledKeys === [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planCatalogPayload(Plan $plan, Business $business): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'is_current' => (int) $business->plan_id === (int) $plan->id,
            'price' => (float) $plan->price,
            'duration_months' => (int) $plan->duration_months,
            'billing_model' => $plan->billing_model,
            'billing_summary' => $plan->billingSummary(),
            'modules_summary' => $this->planFeatures->marketingSummary($plan),
            'modules' => collect($plan->enabledFeatures())->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->planFeatures->label($key),
            ])->values()->all(),
            'limits' => [
                'max_items' => $plan->max_items,
                'max_users' => $plan->max_users,
                'max_branches' => $plan->max_branches,
                'max_business_types' => $plan->max_business_types,
                'storage_label' => $plan->formatStorageLimit(),
            ],
        ];
    }

    /**
     * @param  Collection<int, PlatformBillingInvoice>|iterable  $invoices
     * @return list<array<string, mixed>>
     */
    private function invoiceCollectionPayload(iterable $invoices): array
    {
        return collect($invoices)
            ->map(fn (PlatformBillingInvoice $invoice) => $this->invoicePayload($invoice))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function invoicePayload(PlatformBillingInvoice $invoice): array
    {
        $base = $this->summary->invoicePayload($invoice);

        return array_merge($base, [
            'billing_month' => $invoice->billing_month?->toDateString(),
            'billing_month_label' => $invoice->billingMonthLabel(),
            'status_label' => $invoice->statusLabel(),
            'api_detail_url' => url('/api/v1/subscription/invoices/'.$invoice->id),
            'api_pdf_url' => url('/api/v1/subscription/invoices/'.$invoice->id.'/pdf'),
        ]);
    }
}
