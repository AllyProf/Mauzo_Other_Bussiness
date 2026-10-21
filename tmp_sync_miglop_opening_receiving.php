<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use Illuminate\Support\Facades\DB;

const BUSINESS_ID = 28;
const RECEIVING_REF = 'RCV-20261013-MIG';

$targets = [
    'Kilimanjaro Ndogo' => 236,
    'Castle Lite' => 168,
];

$receiving = Receiving::where('business_id', BUSINESS_ID)
    ->where('reference_no', RECEIVING_REF)
    ->first();

if (! $receiving) {
    echo "Receiving not found: ".RECEIVING_REF."\n";
    exit(1);
}

DB::beginTransaction();
try {
    foreach ($targets as $name => $pieces) {
        $item = Item::where('business_id', BUSINESS_ID)->where('name', $name)->first();
        if (! $item) {
            throw new RuntimeException("Item missing: {$name}");
        }

        $line = ReceivingItem::where('receiving_id', $receiving->id)
            ->where('item_id', $item->id)
            ->first();

        if (! $line) {
            throw new RuntimeException("Receiving line missing: {$name}");
        }

        $before = (float) $line->quantity;
        if (abs($before - $pieces) < 0.01) {
            echo "{$name}: receiving qty already {$pieces}\n";
            continue;
        }

        $line->quantity = $pieces;
        $line->save();
        echo "{$name}: receiving {$before} -> {$pieces}\n";

        $stock = (float) $item->current_stock;
        if (abs($stock - $pieces) > 0.01) {
            $item->current_stock = $pieces;
            $item->save();
            echo "  (stock synced {$stock} -> {$pieces})\n";
        }
    }

    $total = (float) ReceivingItem::where('receiving_id', $receiving->id)
        ->selectRaw('SUM(quantity * cost_price) as t')
        ->value('t');

    $receiving->total_amount = round($total, 2);
    $receiving->save();

    DB::commit();
    echo "Receiving total_amount: {$receiving->total_amount}\n";
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}
