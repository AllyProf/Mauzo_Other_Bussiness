<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Supplier;
use App\Services\ItemBarcodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MiglopOpeningStockSeeder extends Seeder
{
    private const BUSINESS_ID = 28;

    private const BRANCH_ID = 35;

    private const USER_ID = 58;

    /** @var array<string, int> */
    private array $categoryIds = [
        'soft' => 364,
        'energy' => 366,
        'beer' => 361,
        'whiskey' => 367,
        'gin' => 368,
        'spirits' => 363,
        'wine' => 362,
    ];

    /** @var array<string, int> */
    private array $packagingIds = [
        'Crate' => 261,
        'Carton' => 262,
        'Outer' => 263,
        'Piece' => 264,
    ];

    public function run(): void
    {
        $rows = require database_path('data/miglop_opening_stock.php');

        $supplier = Supplier::firstOrCreate(
            ['business_id' => self::BUSINESS_ID, 'name' => 'Opening Stock'],
            ['branch_id' => self::BRANCH_ID]
        );

        $barcodeService = app(ItemBarcodeService::class);
        $receiveLines = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $item = Item::where('business_id', self::BUSINESS_ID)
                    ->where('name', $row['name'])
                    ->first();

                $recvName = $row['recv'];
                $packPrice = (float) $row['pack_price'];
                $unitPrice = (float) $row['unit_price'];
                $packSize = $this->inferPackSize($recvName, $packPrice, $unitPrice);
                $recvPackagingId = $this->packagingIds[$recvName];
                $piecePackagingId = $this->packagingIds['Piece'];

                if (! $item) {
                    $item = Item::create([
                        'business_id' => self::BUSINESS_ID,
                        'category_id' => $this->categoryIds[$row['category']] ?? null,
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
                    $barcodeService->assignMissingBarcodes(self::BUSINESS_ID, $created);
                } else {
                    $item->update([
                        'category_id' => $this->categoryIds[$row['category']] ?? $item->category_id,
                        'receiving_packaging_id' => $recvPackagingId,
                        'units_per_receiving_pack' => $packSize,
                    ]);
                }

                $pieces = (int) $row['bulk'] * $packSize + (int) $row['loose'];
                if ($pieces <= 0) {
                    continue;
                }

                $receiveLines[] = [
                    'item' => $item->fresh(['packagings']),
                    'pieces' => $pieces,
                    'unit_price' => $unitPrice,
                    'pack_size' => $packSize,
                ];
            }

            if ($receiveLines === []) {
                DB::commit();
                $this->command?->warn('No stock lines to receive.');

                return;
            }

            $ref = 'RCV-'.date('Ymd').'-MIG';
            $totalAmount = 0.0;
            foreach ($receiveLines as $line) {
                $totalAmount += $line['pieces'] * $line['unit_price'] * 0.8;
            }

            $receiving = Receiving::create([
                'business_id' => self::BUSINESS_ID,
                'branch_id' => self::BRANCH_ID,
                'supplier_id' => $supplier->id,
                'user_id' => self::USER_ID,
                'reference_no' => $ref,
                'received_date' => now()->toDateString(),
                'total_amount' => round($totalAmount, 2),
                'notes' => 'MIGLOP opening stock import',
                'status' => 'completed',
            ]);

            $stocked = 0;
            foreach ($receiveLines as $line) {
                /** @var Item $item */
                $item = $line['item'];
                $pieces = $line['pieces'];
                $unitSell = $line['unit_price'];
                $unitCost = round($unitSell * 0.8, 2);

                ReceivingItem::create([
                    'receiving_id' => $receiving->id,
                    'item_id' => $item->id,
                    'quantity' => $pieces,
                    'qty_mode' => 'piece',
                    'cost_price' => $unitCost,
                    'cost_mode' => 'unit',
                    'selling_price' => $unitSell,
                    'selling_prices_snapshot' => null,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                ]);

                $item->current_stock = (float) $item->current_stock + $pieces;
                $item->save();

                foreach ($item->packagings as $packaging) {
                    $qpu = max(1, (int) $packaging->quantity_per_unit);
                    $packaging->update([
                        'cost_price' => round($unitCost * $qpu, 2),
                        'selling_price' => round($unitSell * $qpu, 2),
                    ]);
                }

                $stocked++;
            }

            foreach ($rows as $row) {
                $this->syncItemPrices($row);
            }

            DB::commit();

            $this->command?->info("Receiving {$ref}: {$stocked} lines.");
            $this->command?->info('Items: '.Item::where('business_id', self::BUSINESS_ID)->count());
            $this->command?->info('With stock: '.Item::where('business_id', self::BUSINESS_ID)->where('current_stock', '>', 0)->count());
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function inferPackSize(string $recvName, float $packPrice, float $unitPrice): int
    {
        if ($recvName === 'Piece') {
            return 1;
        }
        if ($packPrice <= 0 || $unitPrice <= 0) {
            return 1;
        }

        return max(1, (int) round($packPrice / $unitPrice));
    }

    /**
     * @param  array{name: string, recv: string, pack_price: float, unit_price: float}  $row
     */
    private function syncItemPrices(array $row): void
    {
        $item = Item::where('business_id', self::BUSINESS_ID)
            ->where('name', $row['name'])
            ->with('packagings')
            ->first();

        if (! $item) {
            return;
        }

        $recvId = $this->packagingIds[$row['recv']];
        $pieceId = $this->packagingIds['Piece'];
        $packPrice = (float) $row['pack_price'];
        $unitPrice = (float) $row['unit_price'];

        foreach ($item->packagings as $packaging) {
            if ((int) $packaging->packaging_id === $recvId && $row['recv'] !== 'Piece') {
                $packaging->update([
                    'selling_price' => $packPrice,
                    'cost_price' => round($packPrice * 0.8, 2),
                ]);
            }
            if ((int) $packaging->packaging_id === $pieceId) {
                $packaging->update([
                    'selling_price' => $unitPrice,
                    'cost_price' => round($unitPrice * 0.8, 2),
                ]);
            }
        }
    }
}
