<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\PlatformBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class IssueMonthlyPlatformInvoices extends Command
{
    protected $signature = 'billing:issue-monthly-invoices {--month= : Billing month in YYYY-MM format (defaults to previous month)}';

    protected $description = 'Generate and email monthly platform subscription invoices for all active businesses';

    public function handle(PlatformBillingService $billing): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $autoEmail = (bool) platform_settings('auto_email_billing_invoices', true);
        $issued = 0;
        $emailed = 0;

        Business::query()
            ->where('is_active', true)
            ->whereNotNull('plan_id')
            ->with('plan')
            ->orderBy('id')
            ->chunkById(50, function ($businesses) use ($billing, $month, $autoEmail, &$issued, &$emailed) {
                foreach ($businesses as $business) {
                    $invoice = $billing->issueMonthlyInvoice($business, $month);

                    if (! $invoice) {
                        continue;
                    }

                    $issued++;

                    if ($autoEmail && ! $invoice->emailed_at) {
                        if ($billing->sendInvoiceEmail($invoice)) {
                            $emailed++;
                        }
                    }
                }
            });

        $this->info("Billing month: {$month->format('F Y')}");
        $this->info("Invoices issued/updated: {$issued}");
        $this->info("Invoice emails sent: {$emailed}");

        return self::SUCCESS;
    }
}
