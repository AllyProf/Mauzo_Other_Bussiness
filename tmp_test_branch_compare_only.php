<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Business;
use App\Services\BusinessOwnerReportSmsService;

$testPhone = '255710490428';

$business = Business::query()
    ->where('is_active', true)
    ->where('pending_approval', false)
    ->with(['plan', 'ownerUser'])
    ->withCount('branches')
    ->having('branches_count', '>=', 2)
    ->orderBy('id')
    ->first();

if (! $business) {
    echo "No multi-branch business found.\n";
    exit(0);
}

echo "Multi-branch #{$business->id} {$business->name} (branches={$business->branches_count})\n";
if (! $business->resolveOwner()) {
    echo "No owner — cannot SMS.\n";
    exit(1);
}

$result = app(BusinessOwnerReportSmsService::class)->sendBranchCompareWeeklyReport($business, $testPhone);
echo "Branch compare SMS result: {$result}\n";
