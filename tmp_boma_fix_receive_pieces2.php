<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

$businessId = 15;
$branchId = 19;
$userId = 35;
$csvPath = __DIR__.'/tmp_boma_receive.csv';

function lineGross(Item $item, int $qtyPieces, float $buyPack): float
{
    $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));

    return ($qtyPieces / $units) * $buyPack;
}

$itemsByName = Item::with('packagings.packagingType')
    ->where('business_id', $businessId)
    ->get()
    ->keyBy('name');

$fh = fopen($csvPath, 'r');
fgetcsv($fh);
$lines = [];
while (($row = fgetcsv($fh)) !== false) {
    $name = trim((string) ($row[0] ?? ''));
    $qty = (int) ($row[2] ?? 0);
    if ($name === '') {
        continue;
    }
    $item = $itemsByName->get($name);
    if (! $item) {
        throw new RuntimeException("Missing item: {$name}");
    }
    $lines[] = [
        'item' => $item,
        'qty' => $qty,
        'cost' => (float) $row[3],
        'selling' => (float) $row[5],
    ];
}
fclose($fh);

$supplier = Supplier::where('business_id', $businessId)->firstOrFail();

DB::beginTransaction();
try {
    // Hard reset stock for this business
    Item::where('business_id', $businessId)->update(['current_stock' => 0]);

    // Cancel any completed receivings for this business (opening import only)
    Receiving::where('business_id', $businessId)
        ->where('status', 'completed')
        ->update(['status' => 'cancelled', 'notes' => DB::raw("CONCAT(COALESCE(notes,''), ' [Superseded by piece-qty correction]')")]);

    $activeLines = array_values(array_filter($lines, fn ($l) => $l['qty'] > 0));
    $total = 0.0;
    foreach ($activeLines as $line) {
        $total += lineGross($line['item'], $line['qty'], $line['cost']);
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
        'notes' => 'BOMA opening stock — QTY counted in pieces',
        'status' => 'completed',
    ]);

    $piecesTotal = 0;
    foreach ($lines as $line) {
        $item = $line['item'];
        $qty = $line['qty'];
        $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));

        if ($qty > 0) {
            ReceivingItem::create([
                'receiving_id' => $receiving->id,
                'item_id' => $item->id,
                'quantity' => $qty,
                'qty_mode' => 'piece',
                'cost_price' => $line['cost'],
                'cost_mode' => 'pkg',
                'selling_price' => $line['selling'],
                'selling_prices_snapshot' => null,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
            ]);
        }

        $item->current_stock = $qty;
        $item->save();

        $costPerPiece = $line['cost'] / $units;
        foreach ($item->packagings as $packaging) {
            $qpu = max(1, (int) $packaging->quantity_per_unit);
            $packaging->update([
                'cost_price' => round($costPerPiece * $qpu, 2),
                'selling_price' => max(0, round($line['selling'] * $qpu, 2)),
            ]);
        }

        if ($qty > 0) {
            $piecesTotal += $qty;
        }
    }

    DB::commit();

    echo "OK ref={$ref}\n";
    echo 'active_lines='.count($activeLines)."\n";
    echo "pieces_total={$piecesTotal}\n";
    echo 'total_amount='.number_format($total, 2)."\n";

    foreach (['ANDALIO LA SOMO', 'BAHASHA A4', 'BALLON (DIFF. COLOUR) PC', 'ATENDANCE REGISTER', 'SHORTHAND NOTE BOOK'] as $name) {
        $i = Item::where('business_id', $businessId)->where('name', $name)->first();
        echo "  {$name}: {$i->current_stock} pcs\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED: '.$e->getMessage()."\n";
    exit(1);
}
