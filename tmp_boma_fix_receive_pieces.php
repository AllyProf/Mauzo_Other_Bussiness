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
$oldRef = 'RCV-20260722-098D';

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

$old = Receiving::with(['items.item.packagings'])
    ->where('business_id', $businessId)
    ->where('reference_no', $oldRef)
    ->first();

if (! $old) {
    throw new RuntimeException("Receiving {$oldRef} not found");
}

if ($old->status === 'cancelled') {
    echo "Old receiving already cancelled — will create new piece-based receiving only.\n";
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
    if ($name === '' || $qty <= 0) {
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
        'qty_mode' => 'piece',
        'cost_mode' => 'pkg',
    ];
}
fclose($fh);

$supplier = Supplier::where('business_id', $businessId)->firstOrFail();

DB::beginTransaction();
try {
    if ($old->status !== 'cancelled') {
        foreach ($old->items as $receivingItem) {
            $item = $receivingItem->item;
            $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));
            $qtyMode = $receivingItem->qty_mode ?? 'pkg';
            $remove = $qtyMode === 'piece'
                ? (float) $receivingItem->quantity
                : (float) $receivingItem->quantity * $units;
            $item->current_stock = max(0, (float) $item->current_stock - $remove);
            $item->save();
        }
        $old->update([
            'status' => 'cancelled',
            'notes' => trim(($old->notes ?? '').' [Cancelled: qty was imported as packs; corrected to pieces on '.now()->format('Y-m-d H:i').']'),
        ]);
        echo "Cancelled {$oldRef}\n";
    }

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
        'notes' => 'BOMA opening stock — QTY in pieces',
        'status' => 'completed',
    ]);

    $piecesTotal = 0;
    foreach ($lines as $line) {
        $item = $line['item'];
        $units = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $pieces = receivingPieces($item, $line['qty'], $line['qty_mode']);

        ReceivingItem::create([
            'receiving_id' => $receiving->id,
            'item_id' => $item->id,
            'quantity' => $line['qty'],
            'qty_mode' => 'piece',
            'cost_price' => $line['cost'],
            'cost_mode' => 'pkg',
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
            $packaging->update([
                'cost_price' => round($costPerPiece * $qpu, 2),
                'selling_price' => max(0, round($line['selling'] * $qpu, 2)),
            ]);
        }
        $piecesTotal += $pieces;
    }

    DB::commit();

    echo "NEW ref={$ref}\n";
    echo 'lines='.count($lines)."\n";
    echo "pieces_total={$piecesTotal}\n";
    echo 'total_amount='.number_format($total, 2)."\n";
    echo 'items_with_stock='.Item::where('business_id', $businessId)->where('current_stock', '>', 0)->count()."\n";

    $spots = ['ANDALIO LA SOMO', 'BAHASHA A4', 'BALLON (DIFF. COLOUR) PC', 'SHORTHAND NOTE BOOK', 'MARKER PEN (Dozen)'];
    foreach ($spots as $name) {
        $i = Item::where('business_id', $businessId)->where('name', $name)->first();
        echo "  {$name}: {$i->current_stock} pcs\n";
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo 'FAILED: '.$e->getMessage()."\n";
    exit(1);
}
