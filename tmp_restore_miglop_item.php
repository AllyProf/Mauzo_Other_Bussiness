<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Supplier;
use App\Services\ItemBarcodeService;
use Illuminate\Support\Facades\DB;

const BUSINESS_ID = 28;
const BRANCH_ID = 35;
const USER_ID = 58;

$itemName = $argv[1] ?? 'Balantine KB';

$rows = require __DIR__.'/database/data/miglop_opening_stock.php';
$row = collect($rows)->firstWhere('name', $itemName);
if (! $row) {
    echo "No seed row for: {$itemName}\n";
    exit(1);
}

if (Item::where('business_id', BUSINESS_ID)->where('name', $itemName)->exists()) {
    echo "Item already exists: {$itemName}\n";
    exit(0);
}

$categoryIds = [
    'soft' => 364, 'energy' => 366, 'beer' => 361, 'whiskey' => 367,
    'gin' => 368, 'spirits' => 363, 'wine' => 362,
];
$packagingIds = ['Crate' => 261, 'Carton' => 262, 'Outer' => 263, 'Piece' => 264];

$recvName = $row['recv'];
$packPrice = (float) $row['pack_price'];
$unitPrice = (float) $row['unit_price'];
$packSize = $recvName === 'Piece' ? 1 : max(1, (int) round($packPrice / $unitPrice));
$recvPackagingId = $packagingIds[$recvName];
$piecePackagingId = $packagingIds['Piece'];
$pieces = (int) $row['bulk'] * $packSize + (int) $row['loose'];

$supplier = Supplier::firstOrCreate(
    ['business_id' => BUSINESS_ID, 'name' => 'Opening Stock'],
    ['branch_id' => BRANCH_ID]
);

DB::beginTransaction();
try {
    $item = Item::create([
        'business_id' => BUSINESS_ID,
        'category_id' => $categoryIds[$row['category']] ?? null,
        'receiving_packaging_id' => $recvPackagingId,
        'units_per_receiving_pack' => $packSize,
        'name' => $row['name'],
        'sku' => 'MG-'.strtoupper(bin2hex(random_bytes(4))),
        'brand' => null,
        'description' => null,
        'current_stock' => 0,
    ]);

    $created = [];
    if ($recvName !== 'Piece') {
        $created[] = ItemPackaging::create([
            'item_id' => $item->id,
            'packaging_id' => $recvPackagingId,
            'quantity_per_unit' => $packSize,
            'cost_price' => round($packPrice * 0.8, 2),
            'selling_price' => $packPrice,
        ]);
    }
    $created[] = ItemPackaging::create([
        'item_id' => $item->id,
        'packaging_id' => $piecePackagingId,
        'quantity_per_unit' => 1,
        'cost_price' => round($unitPrice * 0.8, 2),
        'selling_price' => $unitPrice,
    ]);
    app(ItemBarcodeService::class)->assignMissingBarcodes(BUSINESS_ID, $created);

    if ($pieces > 0) {
        $unitCost = round($unitPrice * 0.8, 2);
        $receiving = Receiving::create([
            'business_id' => BUSINESS_ID,
            'branch_id' => BRANCH_ID,
            'supplier_id' => $supplier->id,
            'user_id' => USER_ID,
            'reference_no' => 'RCV-'.date('Ymd').'-RST',
            'received_date' => now()->toDateString(),
            'total_amount' => round($pieces * $unitCost, 2),
            'notes' => "Restored deleted item: {$itemName}",
            'status' => 'completed',
        ]);

        ReceivingItem::create([
            'receiving_id' => $receiving->id,
            'item_id' => $item->id,
            'quantity' => $pieces,
            'qty_mode' => 'piece',
            'cost_price' => $unitCost,
            'cost_mode' => 'unit',
            'selling_price' => $unitPrice,
            'selling_prices_snapshot' => null,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
        ]);

        $item->update(['current_stock' => $pieces]);
    }

    DB::commit();
    echo "Restored: {$itemName} (id={$item->id}) stock={$pieces}\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED: '.$e->getMessage()."\n";
    exit(1);
}
