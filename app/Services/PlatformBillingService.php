<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\PlatformBillingInvoice;
use App\Mail\PlatformBillingInvoiceMail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlatformBillingService
{
    public function __construct(private OwnerDailyReportService $dailyReports)
    {
    }

    public function calculateFee(Business $business, ?Carbon $month = null): array
    {
        $plan = $business->plan;

        if (! $plan) {
            return $this->emptySummary();
        }

        return $business->usesProfitShareBilling()
            ? $this->profitShareFee($business, $month ?? now())
            : $this->fixedFee($business);
    }

    public function monthlyProfit(Business $business, Carbon $month, string $basis = 'net_profit'): float
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $total = 0.0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $built = $this->dailyReports->buildDayEndTotals($business, $date);

            $total += $basis === 'gross_profit'
                ? (float) $built['gross_profit']
                : (float) $built['net_profit'];
        }

        return round($total, 2);
    }

    public function subscriptionOverview(Business $business): array
    {
        $plan = $business->plan;
        $currentFee = $this->calculateFee($business, now());
        $renewalFee = $this->renewalFee($business);

        $invoices = PlatformBillingInvoice::query()
            ->where('business_id', $business->id)
            ->orderByDesc('billing_month')
            ->limit(12)
            ->get();

        return [
            'plan' => $plan,
            'current_fee' => $currentFee,
            'renewal_fee' => $renewalFee,
            'latest_invoice' => $invoices->first(),
            'invoices' => $invoices,
        ];
    }

    public function renewalFee(Business $business): array
    {
        $plan = $business->plan;

        if (! $plan) {
            return $this->emptySummary();
        }

        if ($business->usesProfitShareBilling()) {
            return $this->calculateFee($business, now());
        }

        return $this->fixedFee($business);
    }

    public function issueMonthlyInvoice(Business $business, Carbon $month): ?PlatformBillingInvoice
    {
        $business->loadMissing('plan');
        $plan = $business->plan;

        if (! $plan) {
            return null;
        }

        $billingMonth = $month->copy()->startOfMonth();

        $existing = PlatformBillingInvoice::query()
            ->where('business_id', $business->id)
            ->whereDate('billing_month', $billingMonth->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $fee = $this->calculateFee($business, $billingMonth);

        if ($fee['model'] === 'fixed_monthly') {
            $fee['amount'] = $fee['monthly_equivalent'];
            $fee['detail'] = 'Monthly platform fee (fixed plan)';
        }

        return PlatformBillingInvoice::create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'billing_month' => $billingMonth->toDateString(),
            'invoice_number' => $this->generateInvoiceNumber($business, $billingMonth),
            'billing_model' => $fee['model'],
            'profit_basis' => $fee['profit_basis'],
            'profit_amount' => $fee['profit_amount'],
            'share_percent' => $fee['share_percent'],
            'amount' => $fee['amount'],
            'status' => PlatformBillingInvoice::STATUS_PENDING,
        ]);
    }

    public function sendInvoiceEmail(PlatformBillingInvoice $invoice): bool
    {
        $invoice->loadMissing('business');
        $recipient = trim((string) $invoice->business->email);

        if ($recipient === '') {
            return false;
        }

        if (! trim((string) platform_settings('mail_host'))) {
            Log::warning('Platform billing invoice email skipped: SMTP not configured.', [
                'invoice_id' => $invoice->id,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->send(new PlatformBillingInvoiceMail($invoice));

            $invoice->update([
                'status' => PlatformBillingInvoice::STATUS_NOTIFIED,
                'emailed_at' => now(),
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send platform billing invoice email.', [
                'invoice_id' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function generateInvoiceNumber(Business $business, Carbon $month): string
    {
        return sprintf(
            'INV-%s-B%d',
            $month->format('Ym'),
            $business->id
        );
    }

    private function fixedFee(Business $business): array
    {
        $plan = $business->plan;
        $duration = max(1, (int) ($plan?->duration_months ?? 1));
        $amount = $business->effectiveBillingPrice();
        $monthlyEquivalent = round($amount / $duration, 2);

        return [
            'model' => 'fixed_monthly',
            'amount' => $amount,
            'monthly_equivalent' => $monthlyEquivalent,
            'profit_amount' => null,
            'share_percent' => null,
            'profit_basis' => null,
            'label' => 'TZS '.number_format($amount, 0).' every '.$duration.' month(s)',
            'detail' => 'Renewal fee: TZS '.number_format($amount, 0).' · About TZS '.number_format($monthlyEquivalent, 0).' / month',
        ];
    }

    private function profitShareFee(Business $business, Carbon $month): array
    {
        $basis = $business->effectiveProfitShareBasis();
        $profit = max(0, $this->monthlyProfit($business, $month, $basis));
        $percent = $business->effectiveProfitSharePercent();
        $fee = round($profit * ($percent / 100), 2);
        $minimum = $business->effectiveMinimumMonthlyFee();

        if ($minimum > 0) {
            $fee = max($fee, $minimum);
        }

        $basisLabel = $basis === 'gross_profit' ? 'gross profit' : 'net profit';

        return [
            'model' => 'profit_share',
            'amount' => $fee,
            'monthly_equivalent' => $fee,
            'profit_amount' => $profit,
            'share_percent' => $percent,
            'profit_basis' => $basis,
            'label' => number_format($percent, 1).'% of '.$basisLabel.' (TZS '.number_format($profit, 0).')',
            'detail' => 'Due this month: TZS '.number_format($fee, 0),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'model' => null,
            'amount' => 0,
            'monthly_equivalent' => 0,
            'profit_amount' => null,
            'share_percent' => null,
            'profit_basis' => null,
            'label' => 'No plan assigned',
            'detail' => '—',
        ];
    }
}
