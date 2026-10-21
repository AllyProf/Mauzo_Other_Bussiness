<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use Illuminate\Support\Facades\DB;

const BUSINESS_ID = 28;

$items = Item::where('business_id', BUSINESS_ID)->get()->keyBy('id');

$receivedByItem = ReceivingItem::query()
    ->join('receivings', 'receivings.id', '=', 'receiving_items.receiving_id')
    ->where('receivings.business_id', BUSINESS_ID)
    ->where('receivings.status', 'completed')
    ->select('receiving_items.item_id', DB::raw('SUM(receiving_items.quantity) as qty'))
    ->groupBy('receiving_items.item_id')
    ->pluck('qty', 'item_id');

$mismatch = [];
foreach ($items as $item) {
    $recv = (float) ($receivedByItem[$item->id] ?? 0);
    $stock = (float) $item->current_stock;
    if (abs($recv - $stock) > 0.01) {
        $mismatch[] = [
            'name' => $item->name,
            'received' => $recv,
            'stock' => $stock,
            'delta' => $stock - $recv,
        ];
    }
}

echo 'Items with stock != sum(receiving): '.count($mismatch)."\n";
foreach ($mismatch as $m) {
    echo "  {$m['name']}: received={$m['received']} stock={$m['stock']} (delta {$m['delta']})\n";
}

$rcv = Receiving::where('business_id', BUSINESS_ID)->where('reference_no', 'like', 'RCV-%MIG')->get();
echo "\nMIG receivings:\n";
foreach ($rcv as $r) {
    echo "  {$r->reference_no} id={$r->id} total={$r->total_amount}\n";
}
