<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Item;
use App\Http\Controllers\PriceListController;
use App\Services\ItemPackagingNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

const BUSINESS_ID = 28;

$rows = require __DIR__.'/database/data/miglop_opening_stock.php';
$normalizer = app(ItemPackagingNormalizer::class);

$issues = [];
$ok = 0;

foreach ($rows as $row) {
    $item = Item::where('business_id', BUSINESS_ID)->where('name', $row['name'])->with('packagings.packagingType')->first();
    if (! $item) {
        $issues[] = ['name' => $row['name'], 'problem' => 'missing item'];
        continue;
    }

    $recv = $row['recv'];
    $unitPrice = (float) $row['unit_price'];
    $packPrice = (float) $row['pack_price'];
    $expectedPackSize = $recv === 'Piece' ? 1 : max(1, (int) round($packPrice / $unitPrice));

    $normalized = $normalizer->normalizeItemPackagings($item, $item->packagings);
    $listRows = $normalized->map(function ($r) {
        $packaging = $r['packaging'];

        return [
            'label' => strtolower($packaging->packagingType?->name ?? ''),
            'qpu' => (int) $r['quantity_per_unit'],
            'sell' => (float) $packaging->selling_price,
        ];
    })->filter(fn ($r) => $r['sell'] > 0)->values();

    if ($recv === 'Piece') {
        if ($listRows->count() !== 1) {
            $issues[] = ['name' => $row['name'], 'problem' => 'expected 1 price line, got '.$listRows->count()];
            continue;
        }
        $line = $listRows->first();
        if (abs($line['sell'] - $unitPrice) > 0.01 || $line['qpu'] !== 1) {
            $issues[] = ['name' => $row['name'], 'problem' => "piece line sell={$line['sell']} qpu={$line['qpu']} expected {$unitPrice}/1"];
            continue;
        }
        $ok++;
        continue;
    }

    $pieceLine = $listRows->first(fn ($r) => preg_match('/piece|pcs|pc|each|unit/i', $r['label']));
    $bulkLine = $listRows->first(fn ($r) => str_contains($r['label'], strtolower($recv)));

    if (! $pieceLine) {
        $issues[] = ['name' => $row['name'], 'problem' => 'no piece price line on list'];
        continue;
    }
    if (! $bulkLine) {
        $issues[] = ['name' => $row['name'], 'problem' => "no {$recv} price line on list"];
        continue;
    }
    if (abs($pieceLine['sell'] - $unitPrice) > 0.01) {
        $issues[] = ['name' => $row['name'], 'problem' => "unit {$pieceLine['sell']} != {$unitPrice}"];
        continue;
    }
    if (abs($bulkLine['sell'] - $packPrice) > 0.01) {
        $issues[] = ['name' => $row['name'], 'problem' => "pack {$bulkLine['sell']} != {$packPrice}"];
        continue;
    }
    if ($bulkLine['qpu'] !== $expectedPackSize) {
        $issues[] = ['name' => $row['name'], 'problem' => "pack size {$bulkLine['qpu']} != {$expectedPackSize}"];
        continue;
    }

    $ok++;
}

echo "Compared to miglop_opening_stock.php (source of import prices)\n";
echo "OK: {$ok} / ".count($rows)."\n";
echo "Issues: ".count($issues)."\n";
foreach ($issues as $i) {
    echo "  {$i['name']}: {$i['problem']}\n";
}

// Duplicate redundant lines (same qpu + price)
$dupes = [];
foreach ($rows as $row) {
    $item = Item::where('business_id', BUSINESS_ID)->where('name', $row['name'])->with('packagings.packagingType')->first();
    if (! $item) {
        continue;
    }
    $normalized = $normalizer->normalizeItemPackagings($item, $item->packagings);
    $keys = $normalized->map(fn ($r) => (int) $r['quantity_per_unit'].'|'.(float) $r['packaging']->selling_price)->all();
    if (count($keys) !== count(array_unique($keys))) {
        $dupes[] = $row['name'];
    }
}

echo "\nRedundant duplicate price lines (same size+price): ".count($dupes)."\n";
foreach ($dupes as $d) {
    echo "  {$d}\n";
}
