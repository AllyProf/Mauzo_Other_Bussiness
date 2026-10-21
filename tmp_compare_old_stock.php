<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** Old system: bulk + loose (from user paste). Piece-only: loose only. */
$old = [
    'Soda Bonite' => ['bulk' => 39, 'loose' => 7, 'recv' => 'Crate'],
    'Maji Kubwa' => ['bulk' => 16, 'loose' => 2, 'recv' => 'Outer'],
    'Maji Ndogo' => ['bulk' => 18, 'loose' => 0, 'recv' => 'Carton'],
    'Grand Malt' => ['bulk' => 5, 'loose' => 2, 'recv' => 'Outer'],
    'Redbull' => ['bulk' => 9, 'loose' => 3, 'recv' => 'Outer'],
    'Kilimanjaro Kubwa' => ['bulk' => 10, 'loose' => 4, 'recv' => 'Crate'],
    'Kilimanjaro Ndogo' => ['bulk' => 11, 'loose' => 16, 'recv' => 'Crate'],
    'Safari Kubwa' => ['bulk' => 14, 'loose' => 4, 'recv' => 'Crate'],
    'Safari Ndogo' => ['bulk' => 8, 'loose' => 19, 'recv' => 'Crate'],
    'Serengeti Lager Kubwa' => ['bulk' => 6, 'loose' => 8, 'recv' => 'Crate'],
    'Serengeti Lager Ndogo' => ['bulk' => 4, 'loose' => 3, 'recv' => 'Crate'],
    'Serengeti Lemoni' => ['bulk' => 8, 'loose' => 10, 'recv' => 'Crate'],
    'Serengeti Lite' => ['bulk' => 5, 'loose' => 4, 'recv' => 'Crate'],
    'Castle Milk Stout' => ['bulk' => 2, 'loose' => 6, 'recv' => 'Crate'],
    'Castle Lite' => ['bulk' => 8, 'loose' => 8, 'recv' => 'Crate'],
    'Castle Lite Cane' => ['bulk' => 2, 'loose' => 0, 'recv' => 'Outer'],
    'Flying Fish' => ['bulk' => 8, 'loose' => 5, 'recv' => 'Crate'],
    'Flying Fish Cane' => ['bulk' => 3, 'loose' => 3, 'recv' => 'Outer'],
    'Guinness Kubwa' => ['bulk' => 1, 'loose' => 3, 'recv' => 'Crate'],
    'Windhoek' => ['bulk' => 3, 'loose' => 1, 'recv' => 'Outer'],
    'Heineken 330ml' => ['bulk' => 8, 'loose' => 2, 'recv' => 'Outer'],
    'Konyagi Beer' => ['bulk' => 1, 'loose' => 17, 'recv' => 'Crate'],
    'Reds' => ['bulk' => 3, 'loose' => 5, 'recv' => 'Crate'],
    'Serengeti Apple' => ['bulk' => 2, 'loose' => 0, 'recv' => 'Crate'],
    'Heineken 250ml' => ['bulk' => 8, 'loose' => 0, 'recv' => 'Outer'],
    'Smirnoff Ice' => ['bulk' => 0, 'loose' => 23, 'recv' => 'Crate'],
    'Eagle' => ['bulk' => 7, 'loose' => 0, 'recv' => 'Crate'],
    'Beer Smirnoff Ice Can' => ['bulk' => 0, 'loose' => 1, 'recv' => 'Outer'],
    'Soda Kopo (Takeaway)' => ['bulk' => 2, 'loose' => 2, 'recv' => 'Carton'],
    'Maji Kati' => ['bulk' => 1, 'loose' => 0, 'recv' => 'Carton'],
    'Azam Energy' => ['bulk' => 0, 'loose' => 15, 'recv' => 'Carton'],
    'BALTIKA' => ['bulk' => 1, 'loose' => 5, 'recv' => 'Outer'],
    'Brutal Fruit' => ['bulk' => 4, 'loose' => 0, 'recv' => 'Outer'],
    'Savanna' => ['bulk' => 2, 'loose' => 2, 'recv' => 'Outer'],
    'Jack Daniel 700 ml' => ['loose' => 2],
    'Jack Daniel 350 ML' => ['loose' => 2],
    'Black & White KB' => ['loose' => 2],
    'Black & White NDG' => ['loose' => 18],
    'J & B KB' => ['loose' => 2],
    'J&B NDG' => ['loose' => 31],
    'Grants KB' => ['loose' => 1],
    'Grants NDG' => ['loose' => 3],
    'Viceroy KB' => ['loose' => 13],
    'Viceroy NDG' => ['loose' => 7],
    'Balantine KB' => ['loose' => 2],
    'Konyagi KB' => ['loose' => 16],
    'Konyagi KT' => ['loose' => 14],
    'Konyagi NDG' => ['loose' => 30],
    'K. Vant KB' => ['loose' => 18],
    'K. Vant NDG' => ['loose' => 42],
    'Gilbey\'s' => ['loose' => 20],
    'Gordons NDG' => ['loose' => 8],
    'Campari NDG' => ['loose' => 4],
    'Gilbey\'s KB' => ['loose' => 3],
    'Campari KB' => ['loose' => 1],
    'Hanson Choice NDOGO' => ['loose' => 43],
    'Smirnoff Vodka KB' => ['loose' => 5],
    'Smirnoff Vodka NDG' => ['loose' => 32],
    'Absolut 750ML' => ['loose' => 1],
    'Absolut 200ML' => ['loose' => 3],
    'Captain Morgan KB' => ['loose' => 4],
    'Captain Morgan NDG' => ['loose' => 34],
    'Zanzi 750ML' => ['loose' => 25],
    'Amarula NDG' => ['loose' => 8],
    'Tzee KB' => ['loose' => 7],
    'Tzee NDG' => ['loose' => 22],
    'Imagi KT' => ['loose' => 27],
    'Amarula KT' => ['loose' => 1],
    'Imagi NDG' => ['loose' => 9],
    'CHR OME 200ml' => ['loose' => 6],
    'Wine Dumu' => ['loose' => 4],
    'St. Anna' => ['loose' => 9],
    'Dompo 750ML' => ['loose' => 9],
    'altar wine' => ['loose' => 5],
    'CHR OME 750ml' => ['loose' => 4],
    'ROBERTSON WINERY' => ['loose' => 6],
    'Saint MARY' => ['loose' => 1],
    'orijin kb' => ['loose' => 2],
    'rendez vous' => ['loose' => 6],
];

$svc = app(App\Services\ItemStockDisplayService::class);
$items = App\Models\Item::where('business_id', 28)->with('packagings.packagingType', 'receivingPackaging')->get()->keyBy('name');

$packIds = ['Crate' => 261, 'Carton' => 262, 'Outer' => 263, 'Piece' => 264];
$seed = collect(require __DIR__.'/database/data/miglop_opening_stock.php')->keyBy('name');

$mismatch = [];
$missing = [];

foreach ($old as $name => $row) {
    $item = $items->get($name);
    if (! $item) {
        $missing[] = $name;
        continue;
    }
    $seedRow = $seed->get($name);
    $packPrice = (float) ($seedRow['pack_price'] ?? 0);
    $unitPrice = (float) ($seedRow['unit_price'] ?? 1);
    $recv = $row['recv'] ?? ($seedRow['recv'] ?? 'Piece');
    $packSize = $recv === 'Piece' ? 1 : max(1, (int) round($packPrice / $unitPrice));
    $bulk = (int) ($row['bulk'] ?? 0);
    $loose = (int) ($row['loose'] ?? 0);
    $expectedPieces = isset($row['loose']) && ! isset($row['bulk']) ? $loose : ($bulk * $packSize + $loose);

    $actual = (float) $item->current_stock;
    $info = $svc->format($item);
    if (abs($actual - $expectedPieces) > 0.01) {
        $mismatch[] = [
            'name' => $name,
            'old' => "{$bulk} bulk + {$loose} loose = {$expectedPieces} pcs (pack {$packSize})",
            'db' => (int) $actual.' ('.$info['stock_display'].')',
        ];
    }
}

echo 'Compared: '.count($old).PHP_EOL;
echo 'Missing in DB: '.count($missing).PHP_EOL;
foreach ($missing as $n) {
    echo "  MISSING: {$n}\n";
}
echo 'Mismatches: '.count($mismatch).PHP_EOL;
foreach ($mismatch as $m) {
    echo "  {$m['name']}\n    Old: {$m['old']}\n    DB:  {$m['db']}\n";
}
