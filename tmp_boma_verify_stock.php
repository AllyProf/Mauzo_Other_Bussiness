<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;

$csv = __DIR__.'/tmp_boma_receive.csv';
$fh = fopen($csv, 'r');
fgetcsv($fh);

$mismatches = [];
$checked = 0;
$zeroOk = 0;
$stockOk = 0;

while (($r = fgetcsv($fh)) !== false) {
    $name = trim($r[0]);
    $qty = (int) $r[2];
    $buyPack = (float) $r[3];
    $costPiece = (float) $r[4];
    $sellPiece = (float) $r[5];

    $item = Item::with(['packagings' => fn ($q) => $q->orderBy('quantity_per_unit')])
        ->where('business_id', 15)
        ->where('name', $name)
        ->first();

    if (! $item) {
        $mismatches[] = "MISSING ITEM: {$name}";
        continue;
    }

    $upr = max(1, (int) $item->units_per_receiving_pack);
    $expectedPieces = $qty * $upr;
    $stock = (float) $item->current_stock;
    $checked++;

    if ($qty <= 0) {
        if ($stock != 0.0) {
            $mismatches[] = "{$name}: expected stock 0, got {$stock}";
        } else {
            $zeroOk++;
        }
        continue;
    }

    if (abs($stock - $expectedPieces) > 0.01) {
        $mismatches[] = "{$name}: stock {$stock} != expected {$expectedPieces} (qty {$qty} × {$upr})";
    } else {
        $stockOk++;
    }

    $piecePkg = $item->packagings->firstWhere('quantity_per_unit', 1)
        ?? $item->packagings->sortBy('quantity_per_unit')->first();
    $recvPkg = $item->packagings->firstWhere('quantity_per_unit', $upr)
        ?? $item->packagings->sortByDesc('quantity_per_unit')->first();

    if ($piecePkg) {
        $sell = round((float) $piecePkg->selling_price, 2);
        if (abs($sell - $sellPiece) > 0.5) {
            $mismatches[] = "{$name}: sell piece {$sell} != {$sellPiece}";
        }
        $cost = round((float) $piecePkg->cost_price, 2);
        $expectedCost = round($costPiece, 2);
        // allow small rounding from buy_pack/upr
        if (abs($cost - $expectedCost) > 1.0 && abs($cost - round($buyPack / $upr, 2)) > 1.0) {
            $mismatches[] = "{$name}: cost piece {$cost} != {$expectedCost} (or buy/upr ".round($buyPack/$upr,2).')';
        }
    }

    if ($recvPkg && $upr > 1) {
        $packSell = round((float) $recvPkg->selling_price, 2);
        $expectedPackSell = round($sellPiece * $upr, 2);
        if (abs($packSell - $expectedPackSell) > 0.5) {
            $mismatches[] = "{$name}: pack sell {$packSell} != {$expectedPackSell}";
        }
    }
}
fclose($fh);

echo "checked={$checked} stock_ok={$stockOk} zero_ok={$zeroOk} mismatches=".count($mismatches).PHP_EOL;
foreach (array_slice($mismatches, 0, 40) as $m) {
    echo $m.PHP_EOL;
}

// Spot checks from user paste
$spots = [
    'ANDALIO LA SOMO' => [132, 3000],
    'BAHASHA A4' => [228000, 200],
    'BAHASHA A3' => [0, 400],
    'BALLON (DIFF. COLOUR) PC' => [18800, 200],
    'SPRNG FILLE' => [4464, 20000],
    'SHORTHAND NOTE BOOK' => [19392, 1000],
    'WEDDING S/S' => [30, 600],
    'WEDDING M/S' => [240, 1000],
    'MARKER PEN (Dozen)' => [2736, 500],
    'OBAM/KIF/BAL/SKO-LUE/ACK/RED (BOX)' => [70500, 200],
];
echo "\nSpot checks:\n";
foreach ($spots as $name => [$pcs, $sell]) {
    $item = Item::with('packagings')->where('business_id', 15)->where('name', $name)->first();
    if (! $item) {
        echo "  FAIL missing {$name}\n";
        continue;
    }
    $piece = $item->packagings->sortBy('quantity_per_unit')->first();
    $okStock = abs((float) $item->current_stock - $pcs) < 0.01;
    $okSell = $piece && abs((float) $piece->selling_price - $sell) < 0.5;
    echo '  '.($okStock && $okSell ? 'OK' : 'FAIL')." {$name} stock={$item->current_stock} sell=".($piece->selling_price ?? '?')."\n";
}
