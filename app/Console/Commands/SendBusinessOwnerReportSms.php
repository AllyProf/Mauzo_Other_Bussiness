<?php

namespace App\Console\Commands;

use App\Services\BusinessOwnerReportSmsService;
use App\Services\BusinessSalesReportEmailService;
use Illuminate\Console\Command;

class SendBusinessOwnerReportSms extends Command
{
    protected $signature = 'reports:send-sms';

    protected $description = 'Send scheduled daily/weekly owner sales report SMS and email PDF reports';

    public function handle(
        BusinessOwnerReportSmsService $smsReports,
        BusinessSalesReportEmailService $emailReports,
    ): int {
        $smsCounts = $smsReports->sendDueReports();
        $emailCounts = $emailReports->sendDueScheduledReports();

        $this->info(sprintf(
            'Owner report SMS — daily: %d, weekly: %d, branch_compare: %d, receiving: %d, skipped: %d, failed: %d.',
            $smsCounts['daily'],
            $smsCounts['weekly'],
            $smsCounts['branch_compare'] ?? 0,
            $smsCounts['receiving'] ?? 0,
            $smsCounts['skipped'],
            $smsCounts['failed'],
        ));

        $this->info(sprintf(
            'Sales report email — daily: %d, weekly: %d, monthly: %d, skipped: %d, failed: %d, retried: %d.',
            $emailCounts['daily'],
            $emailCounts['weekly'],
            $emailCounts['monthly'],
            $emailCounts['skipped'],
            $emailCounts['failed'],
            $emailCounts['retried'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
