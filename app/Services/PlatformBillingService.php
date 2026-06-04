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
    public function __construct(
        private OwnerDailyReportService $dailyReports,
        private PlatformSmsService $platformSms,
        private PlatformMailService $platformMail,
        private PlatformSettingsService $platformSettings,
    ) {
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

        if (! $this->platformSettings->isMailConfigured()) {
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

            $business = $invoice->business;
            if ($business) {
                $this->platformSms->sendInvoiceIssued(
                    $business,
                    $invoice->invoice_number,
                    number_format((float) $invoice->amount, 0)
                );
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send platform billing invoice email.', [
                'invoice_id' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array{payment_reference?: string|null, payment_notes?: string|null, extend_subscription?: bool|null}  $data
     */
    public function markInvoicePaid(PlatformBillingInvoice $invoice, array $data, \App\Models\User $admin): PlatformBillingInvoice
    {
        $invoice->loadMissing(['business.plan']);

        $invoice->update([
            'status' => PlatformBillingInvoice::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_notes' => $data['payment_notes'] ?? null,
            'marked_paid_by' => $admin->id,
        ]);

        if ($data['extend_subscription'] ?? true) {
            $business = $invoice->business;
            $plan = $business?->plan;

            if ($business && $plan) {
                $base = $business->expiry_date && Carbon::parse($business->expiry_date)->isFuture()
                    ? Carbon::parse($business->expiry_date)
                    : now();

                $newExpiry = $base->copy()->addMonths(max(1, (int) $plan->duration_months));

                $business->update([
                    'expiry_date' => $newExpiry->toDateString(),
                    'is_active' => true,
                ]);

                $this->platformSms->sendPaymentConfirmed(
                    $business->fresh(),
                    $newExpiry->format('d M Y')
                );
                $this->platformMail->sendPaymentConfirmed(
                    $business->fresh(),
                    $newExpiry->format('d M Y')
                );
            }
        }

        return $invoice->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_invoiced: float,
     *     total_paid: float,
     *     total_outstanding: float,
     *     paid_count: int,
     *     pending_count: int,
     *     notified_count: int
     * }
     */
    public function paymentReportSummary(?Carbon $month = null, array $filters = []): array
    {
        $query = PlatformBillingInvoice::query()
            ->when($month, fn ($q) => $q->whereDate('billing_month', $month->toDateString()))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['business_id']), fn ($q) => $q->where('business_id', $filters['business_id']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = trim((string) $filters['search']);
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('business', fn ($business) => $business->where('name', 'like', "%{$search}%"));
                });
            });

        $paidQuery = (clone $query)->where('status', PlatformBillingInvoice::STATUS_PAID);
        $pendingQuery = (clone $query)->where('status', PlatformBillingInvoice::STATUS_PENDING);
        $notifiedQuery = (clone $query)->where('status', PlatformBillingInvoice::STATUS_NOTIFIED);
        $outstandingQuery = (clone $query)->whereIn('status', [
            PlatformBillingInvoice::STATUS_PENDING,
            PlatformBillingInvoice::STATUS_NOTIFIED,
        ]);

        return [
            'total_invoiced' => (float) (clone $query)->sum('amount'),
            'total_paid' => (float) $paidQuery->sum('amount'),
            'total_outstanding' => (float) $outstandingQuery->sum('amount'),
            'paid_count' => (int) $paidQuery->count(),
            'pending_count' => (int) $pendingQuery->count(),
            'notified_count' => (int) $notifiedQuery->count(),
        ];
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
