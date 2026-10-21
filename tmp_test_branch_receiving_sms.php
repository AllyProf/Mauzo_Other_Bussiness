<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Business;
use App\Models\Receiving;
use App\Services\BusinessOwnerReportSmsService;
use ReflectionClass;

$testPhone = '255710490428';
$svc = app(BusinessOwnerReportSmsService::class);

$business = Business::query()
    ->where('is_active', true)
    ->where('pending_approval', false)
    ->with(['plan', 'ownerUser'])
    ->withCount('branches')
    ->orderByDesc('branches_count')
    ->orderBy('id')
    ->first();

if (! $business || ! $business->resolveOwner()) {
    echo "No suitable business/owner.\n";
    exit(1);
}

echo "Business #{$business->id} {$business->name} (branches={$business->branches_count})\n\n";

echo "1) Branch comparison weekly SMS → {$testPhone}\n";
$r1 = $svc->sendBranchCompareWeeklyReport($business, $testPhone);
echo "   Result: {$r1}\n";

// For receiving: if yesterday empty, build a preview from latest receive day then still try send (may skip).
$latestRecvDate = Receiving::query()
    ->where('business_id', $business->id)
    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
    ->max('received_date');

echo "\n2) Daily purchase SMS → {$testPhone}\n";
echo '   Latest receiving date in DB: '.($latestRecvDate ?: 'none')."\n";
$r2 = $svc->sendReceivingDailyReport($business, $testPhone);
echo "   Result: {$r2}\n";

if ($r2 === 'skipped' && $latestRecvDate) {
    echo "   Yesterday had no receives — sending a one-off summary for {$latestRecvDate}...\n";
    $ref = new ReflectionClass($svc);
    $summaryMethod = $ref->getMethod('receivingSummary');
    $summaryMethod->setAccessible(true);
    $summary = $summaryMethod->invoke($svc, $business->id, $latestRecvDate, $latestRecvDate);

    $top = '';
    if ($summary['top_suppliers']->isNotEmpty()) {
        $top = ' Top: '.$summary['top_suppliers']
            ->take(2)
            ->map(fn ($row) => $row['name'].' '.number_format($row['amount'], 0))
            ->implode(', ').'.';
    }

    $message = sprintf(
        '%s Purchases %s (TEST): TZS %s · %s receipts · %s suppliers.%s For more information, visit your email.',
        $business->name,
        \Carbon\Carbon::parse($latestRecvDate)->format('d M Y'),
        number_format($summary['total_amount'], 0),
        number_format($summary['receipts'], 0),
        number_format($summary['suppliers'], 0),
        $top,
    );

    $dispatch = $ref->getMethod('dispatchToOwner');
    $dispatch->setAccessible(true);
    $ok = $dispatch->invoke($svc, $business, $message, 'owner_receiving_daily_test', $testPhone);
    echo $ok ? "   TEST purchase SMS: sent\n" : "   TEST purchase SMS: failed\n";
}

echo "\nDone.\n";
