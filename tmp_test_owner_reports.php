<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Business;
use App\Services\BusinessOwnerReportSmsService;
use App\Services\BusinessSalesReportEmailService;
use Illuminate\Support\Facades\Mail;

$testEmail = 'allyprof7@gmail.com';
$testPhone = '255710490428';

$business = Business::query()
    ->where('is_active', true)
    ->where('pending_approval', false)
    ->with(['plan', 'ownerUser'])
    ->orderBy('id')
    ->first();

if (! $business) {
    echo "No active business found.\n";
    exit(1);
}

echo "Using business #{$business->id} {$business->name}\n";
$owner = $business->resolveOwner();
echo 'Owner: '.($owner?->name ?? 'n/a').' / phone='.($owner?->phone ?? $business->phone ?? 'n/a')."\n\n";

$emailSvc = app(BusinessSalesReportEmailService::class);
$smsSvc = app(BusinessOwnerReportSmsService::class);

echo "1) Building daily PDF report + emailing {$testEmail}...\n";
try {
    $ref = new ReflectionClass($emailSvc);
    $build = $ref->getMethod('buildPeriodReport');
    $build->setAccessible(true);
    $from = now()->subDay()->toDateString();
    $to = $from;
    $periodLabel = 'Daily Sales — '.now()->subDay()->format('d M Y');
    $report = $build->invoke($emailSvc, $business, $from, $to, $periodLabel, 'Daily Sales Summary');

    $received = $report['receivedItems'] ?? [];
    $stock = $report['stockValue'] ?? [];
    echo '   Orders: '.($report['stats']['orders'] ?? 0)
        .' | Profit: '.number_format((float) ($report['stats']['profit'] ?? 0), 0)
        .' | Received lines: '.($received['lines'] ?? 0)
        .' | Stock sell value: '.number_format((float) ($stock['selling_value'] ?? 0), 0)
        ."\n";

    $pdf = $emailSvc->renderPdf($report);
    echo '   PDF bytes: '.strlen($pdf)."\n";

    $dispatch = $ref->getMethod('dispatchEmails');
    $dispatch->setAccessible(true);
    $subject = $business->name.' — TEST '.$periodLabel;
    $body = sprintf(
        "TEST sales report.\n\nOrders: %s\nGross: TZS %s\nCollected: TZS %s\nProfit: TZS %s\nStock value (selling): TZS %s\nReceived lines: %s\n\nSee attached PDF.",
        number_format($report['stats']['orders']),
        number_format($report['stats']['gross'], 0),
        number_format($report['stats']['collected'], 0),
        number_format($report['stats']['profit'], 0),
        number_format((float) ($stock['selling_value'] ?? 0), 0),
        number_format((int) ($received['lines'] ?? 0)),
    );
    $ok = $dispatch->invoke($emailSvc, $business, [$testEmail], $subject, $body, $pdf, 'sales-daily-test-'.str_replace('-', '', $to));
    echo $ok ? "   Email: sent\n" : "   Email: FAILED\n";
} catch (Throwable $e) {
    echo '   EMAIL ERROR: '.$e->getMessage()."\n".$e->getTraceAsString()."\n";
}

echo "\n2) Sending TEST owner daily SMS to {$testPhone}...\n";
try {
    if (! $owner) {
        echo "   SMS skipped: business has no owner user (required for SMS log).\n";
    } else {
        $ok = $smsSvc->sendDailyReport($business, $testPhone);
        echo $ok ? "   SMS: sent\n" : "   SMS: FAILED (plan/quota/gateway — check storage/logs)\n";
    }
} catch (Throwable $e) {
    echo '   SMS ERROR: '.$e->getMessage()."\n";
}

echo "\nDone.\n";
