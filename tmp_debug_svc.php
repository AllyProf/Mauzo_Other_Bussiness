<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bizId = 10;
$branches = \App\Models\Branch::query()
    ->where('is_active', true)
    ->where(function ($query) use ($bizId) {
        $query->whereHas('businesses', fn ($q) => $q->where('businesses.id', $bizId))
            ->orWhere('business_id', $bizId);
    })->get(['id','name']);
echo "Branches for business {$bizId}: " . $branches->count() . "\n";
foreach ($branches as $b) echo "  {$b->id} {$b->name}\n";

$business = \App\Models\Business::find($bizId);
if ($business && $branches->isNotEmpty()) {
    $branchId = (int) $branches->first()->id;
    try {
        $labels = app(\App\Services\ServiceTemplateImportService::class)
            ->importForBranch($business, $branchId, ['stationery_print']);
        echo "Imported: " . implode(', ', $labels) . "\n";
        echo "Categories now: " . \App\Models\ServiceCategory::where('business_id', $bizId)->count() . "\n";
        echo "Services now: " . \App\Models\Service::where('business_id', $bizId)->count() . "\n";
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
