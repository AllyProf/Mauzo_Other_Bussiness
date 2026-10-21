<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Services\ItemStockDisplayService;

const BUSINESS_ID = 28;

$fixes = [
    'Kilimanjaro Ndogo' => 236,
    'Castle Lite' => 168,
];

$svc = app(ItemStockDisplayService::class);

foreach ($fixes as $name => $targetPieces) {
    $item = Item::where('business_id', BUSINESS_ID)->where('name', $name)->first();
    if (! $item) {
        echo "Missing: {$name}\n";
        continue;
    }
    $before = (float) $item->current_stock;
    if (abs($before - $targetPieces) < 0.01) {
        echo "{$name}: already {$targetPieces}\n";
        continue;
    }
    $item->current_stock = $targetPieces;
    $item->save();
    $info = $svc->format($item->fresh());
    echo "{$name}: {$before} -> {$targetPieces} ({$info['stock_display']})\n";
}
