<?php

namespace App\Services\Api;

use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use App\Services\PlatformBillingService;
use App\Services\PlatformSettingsService;
use Carbon\Carbon;

class ApiSubscriptionSummaryService
{
    public function __construct(
        private PlatformBillingService $billing,
        private PlatformSettingsService $platformSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $business->loadMissing('plan');
        $plan = $business->plan;

        $expiryDate = $business->expiry_date;
        $daysRemaining = null;
        $expiringSoon = false;
        $threshold = (int) $this->platformSettings->get('expiry_warning_days', 7);

        if ($expiryDate) {
            $expiry = Carbon::parse($expiryDate)->startOfDay();
            $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);
            $expiringSoon = $daysRemaining >= 0 && $daysRemaining <= $threshold;
        }

        $locked = $this->platformSettings->businessIsLocked($business);
        $isActive = (bool) $business->is_active && ! $locked;

        $unpaid = subscription_outstanding_invoice_for($business);

        $currentFee = null;
        if ($plan && ! $business->usesProfitShareBilling()) {
            $fee = $this->billing->calculateFee($business, now());
            $currentFee = [
                'amount' => (float) ($fee['amount'] ?? $fee['monthly_equivalent'] ?? 0),
                'currency' => 'TZS',
            ];
        }

        $blockedReason = null;
        $accountStatus = $business->statusLabel();

        if ($locked) {
            $accountStatus = 'expired';
            $blockedReason = 'subscription_expired';
        } elseif (! $business->is_active) {
            $blockedReason = 'business_suspended';
            $accountStatus = 'suspended';
        }

        return [
            'account_status' => $accountStatus,
            'is_active' => $isActive,
            'expiry_date' => $expiryDate,
            'days_remaining' => $daysRemaining,
            'expiring_soon' => $expiringSoon,
            'expiring_soon_days_threshold' => $threshold,
            'grace_until' => $this->graceUntil($business),
            'blocked_reason' => $blockedReason,
            'plan' => $plan ? [
                'name' => $plan->name,
                'code' => (string) ($plan->slug ?? $plan->name ?? $plan->id),
            ] : null,
            'current_fee' => $currentFee,
            'unpaid_invoice' => $unpaid ? $this->invoicePayload($unpaid) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoicePayload(PlatformBillingInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date?->toDateString()
                ?? $invoice->billing_month?->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function graceUntil(Business $business): ?string
    {
        if (! $business->expiry_date) {
            return null;
        }

        $graceDays = (int) $this->platformSettings->get('grace_period_days', 0);

        return Carbon::parse($business->expiry_date)
            ->addDays($graceDays)
            ->toDateString();
    }
}
