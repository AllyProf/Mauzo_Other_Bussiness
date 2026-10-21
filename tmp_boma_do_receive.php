<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Business;
use App\Models\Item;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$businessId = 15;
$branchId = 19;
$userId = 35;
$csvPath = __DIR__.'/tmp_boma_receive.csv';

$business = Business::findOrFail($businessId);
$user = User::findOrFail($userId);

if ((int) $user->business_id !== $businessId) {
    throw new RuntimeException("User {$userId} is not on business {$businessId}");
}

$supplier = Supplier::firstOrCreate(
    ['business_id' => $businessId, 'name' => 'Opening Stock'],
    ['contact_person' => null, 'phone' => null, 'email' => null, 'address' => null, 'region' => null]
);

$itemsByName = Item::with('packagings.packagingType')
    ->where('business_id', $businessId)
    ->get()
    ->keyBy('name');

$fh = fopen($csvPath, 'r');
if (! $fh) {
    throw new RuntimeException("Cannot open {$csvPath}");
}
$header = fgetcsv($fh);

$lines = [];
$skippedZero = 0;
$missing = [];

while (($row = fgetcsv($fh)) !== false) {
    $name = trim((string) ($row[0] ?? ''));
    $qty = (int) ($row[2] ?? 0);
    $buyPack = (float) ($row[3] ?? 0);
    $sellPiece = (float) ($row[5] ?? 0);

    if ($name === '') {
        continue;
    }
    if ($qty <= 0) {
        $skippedZero++;
        continue;
    }

    $item = $itemsByName->get($name);
    if (! $item) {
        $missing[] = $name;
        continue;
    }

    $lines[] = [
        'item' => $item,
        'qty' => $qty,
        'cost' => $buyPack,
        'selling' => $sellPiece,
        'qty_mode' => 'pkg',
        'cost_mode' => 'pkg',
    ];
}
fclose($fh);

if (! empty($missing)) {
    echo "Missing items:\n".implode("\n", $missing)."\n";
    exit(1);
}

if (empty($lines)) {
    echo "No lines with qty > 0\n";
    exit(1);
}

function receivingPieces(Item $item, int $qty, string $qtyMode): int
{
    $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));

    return $qtyMode === 'piece' ? $qty : $qty * $units;
}

function lineGross(Item $item, int $qty, float $cost, string $qtyMode, string $costMode): float
{
    $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));
    $pieces = receivingPieces($item, $qty, $qtyMode);

    if ($costMode === 'unit') {
        return $pieces * $cost;
    }
    if ($qtyMode === 'piece') {
        return ($pieces / $units) * $cost;
    }

    return $qty * $cost;
}

DB::beginTransaction();
try {
    $total = 0.0;
    foreach ($lines as $line) {
        $total += lineGross($line['item'], $line['qty'], $line['cost'], $line['qty_mode'], $line['cost_mode']);
    }

    $ref = 'RCV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

    $receiving = Receiving::create([
        'business_id' => $businessId,
        'branch_id' => $branchId,
        'supplier_id' => $supplier->id,
        'user_id' => $userId,
        'reference_no' => $ref,
        'received_date' => date('Y-m-d'),
        'total_amount' => $total,
        'notes' => 'BOMA STATIONERY opening stock import',
        'status' => 'completed',
    ]);

    $stocked = 0;
    $piecesTotal = 0;

    foreach ($lines as $line) {
        /** @var Item $item */
        $item = $line['item'];
        $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $pieces = receivingPieces($item, $line['qty'], $line['qty_mode']);

        ReceivingItem::create([
            'receiving_id' => $receiving->id,
            'item_id' => $item->id,
            'quantity' => $line['qty'],
            'qty_mode' => $line['qty_mode'],
            'cost_price' => $line['cost'],
            'cost_mode' => $line['cost_mode'],
            'selling_price' => $line['selling'],
            'selling_prices_snapshot' => null,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
        ]);

        $item->current_stock = (float) $item->current_stock + $pieces;
        $item->save();

        $costPerPiece = $line['cost'] / $units;
        foreach ($item->packagings as $packaging) {
            $qpu = max(1, (int) $packaging->quantity_per_unit);
            $sellPrice = round($line['selling'] * $qpu, 2);
            $packaging->update([
                'cost_price' => round($costPerPiece * $qpu, 2),
                'selling_price' => max(0, $sellPrice),
            ]);
        }

        $stocked++;
        $piecesTotal += $pieces;
    }

    DB::commit();

    echo "OK ref={$ref}\n";
    echo "supplier={$supplier->id} {$supplier->name}\n";
    echo "lines={$stocked} skipped_zero={$skippedZero}\n";
    echo "pieces_added={$piecesTotal}\n";
    echo "total_amount=".number_format($total, 2)."\n";
    echo "items_with_stock=".Item::where('business_id', $businessId)->where('current_stock', '>', 0)->count()."\n";
    echo "items_zero_stock=".Item::where('business_id', $businessId)->where('current_stock', '<=', 0)->count()."\n";

    $samples = Item::where('business_id', $businessId)
        ->whereIn('name', ['ANDALIO LA SOMO', 'BAHASHA A4', 'BALLON (DIFF. COLOUR) PKT', 'BIBLIA L/S'])
        ->get(['name', 'current_stock', 'units_per_receiving_pack']);
    foreach ($samples as $s) {
        echo "sample: {$s->name} stock={$s->current_stock} upr={$s->units_per_receiving_pack}\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED: '.$e->getMessage()."\n".$e->getTraceAsString()."\n";
    exit(1);
}
