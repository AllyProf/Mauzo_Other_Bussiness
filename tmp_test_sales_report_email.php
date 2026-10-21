<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Business;
use App\Services\BusinessSalesReportEmailService;

$svc = app(BusinessSalesReportEmailService::class);

$businesses = Business::query()
    ->where('is_active', true)
    ->orderBy('id')
    ->get();

echo "Checking businesses for email report settings...\n\n";

$targets = [];
foreach ($businesses as $b) {
    $s = $b->automationSettings();
    $enabled = (bool) ($s['email_sales_report_enabled'] ?? false);
    $recipients = $svc->recipientEmails($s, $b);
    echo sprintf(
        "#%d %s | enabled=%s | shift_close=%s | daily=%s | weekly=%s | monthly=%s | recipients=%s\n",
        $b->id,
        $b->name,
        $enabled ? 'yes' : 'no',
        !empty($s['email_sales_report_on_shift_close']) ? 'yes' : 'no',
        !empty($s['email_sales_report_daily']) ? 'yes' : 'no',
        !empty($s['email_sales_report_weekly']) ? 'yes' : 'no',
        !empty($s['email_sales_report_monthly']) ? 'yes' : 'no',
        $recipients === [] ? '(none)' : implode(', ', $recipients)
    );
    if ($enabled && $recipients !== []) {
        $targets[] = $b;
    }
}

if ($targets === []) {
    echo "\nNo business has email reports enabled with recipients. Enable settings first.\n";
    exit(1);
}

echo "\nSending TEST daily sales report email...\n";

foreach ($targets as $b) {
    $s = $b->automationSettings();
    $recipients = $svc->recipientEmails($s, $b);
    echo "→ {$b->name} → ".implode(', ', $recipients)."\n";
    try {
        $result = $svc->sendPeriodReport($b, 'daily', $recipients);
        echo match ($result) {
            'sent' => "  OK sent\n",
            'skipped' => "  SKIPPED (no sales)\n",
            default => "  FAILED (see logs)\n",
        };
    } catch (Throwable $e) {
        echo '  ERROR: '.$e->getMessage()."\n";
    }
}

echo "\nDone. Check the inbox (and spam).\n";
