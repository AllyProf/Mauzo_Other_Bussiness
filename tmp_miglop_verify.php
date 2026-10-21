<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Services\ItemStockDisplayService::class);
$names = ['Soda Bonite', 'Maji Kubwa', 'Jack Daniel 700 ml', 'Smirnoff Ice', 'Heineken 330ml', 'Heineken 250ml'];
foreach ($names as $name) {
    $item = App\Models\Item::with('packagings.packagingType')->where('business_id', 28)->where('name', $name)->first();
    if (! $item) {
        echo "MISSING: {$name}\n";
        continue;
    }
    $fmt = $svc->format($item);
    $bulk = $item->packagings->sortByDesc('quantity_per_unit')->first();
    echo "{$name}: stock={$item->current_stock} display={$fmt['stock_display']} pack={$bulk?->packagingType?->name} sell={$bulk?->selling_price}\n";
}

$rcv = App\Models\Receiving::where('business_id', 28)->orderByDesc('id')->first();
echo "Last receiving: {$rcv->reference_no} total={$rcv->total_amount}\n";
