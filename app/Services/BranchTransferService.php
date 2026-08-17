<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\Business;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchTransferService
{
    public function __construct(
        private ItemStockDisplayService $stockDisplay,
        private ItemBarcodeService $barcodes,
        private ItemPackagingNormalizer $packagingNormalizer
    ) {
    }

    public function mainBranch(int $businessId): ?Branch
    {
        return Branch::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function destinationBranches(int $businessId, int $fromBranchId): Collection
    {
        return Branch::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->where('id', '!=', $fromBranchId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(Business $business, Branch $fromBranch, Branch $toBranch): array
    {
        $threshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);

        $fromItems = $this->itemsForBranch((int) $business->id, (int) $fromBranch->id);
        $toItems = $this->itemsForBranch((int) $business->id, (int) $toBranch->id);
        $toByKey = $toItems->groupBy(fn (Item $item) => $this->matchKey($item));

        $rows = [];

        foreach ($fromItems as $fromItem) {
            $matches = $toByKey->get($this->matchKey($fromItem), collect());
            $toItem = $matches->first();

            $fromStock = (float) $fromItem->current_stock;
            $toStock = $toItem ? (float) $toItem->current_stock : 0.0;
            $isOut = $toItem && $toStock <= 0;
            $isLow = $toItem && $toStock > 0 && $toStock <= $threshold;
            $canTransfer = $fromStock > 0;
            $packagings = $this->packagingOptions($fromItem, $fromStock);

            $rows[] = [
                'from_item_id' => $fromItem->id,
                'to_item_id' => $toItem?->id,
                'name' => $fromItem->name,
                'brand' => $fromItem->brand,
                'sku' => $fromItem->sku,
                'category' => $fromItem->category?->name,
                'category_slug' => $fromItem->category
                    ? \Illuminate\Support\Str::slug($fromItem->category->name)
                    : 'uncategorized',
                'from_stock' => $fromStock,
                'from_stock_display' => $this->stockDisplay->remainsDisplay($fromItem),
                'to_stock' => $toStock,
                'to_stock_display' => $toItem ? $this->stockDisplay->remainsDisplay($toItem) : '—',
                'registered_on_destination' => $toItem !== null,
                'is_out' => $isOut,
                'is_low' => $isLow,
                'needs_supply' => $toItem && ($isOut || $isLow),
                'can_transfer' => $canTransfer,
                'max_qty' => (int) floor($fromStock),
                'packagings' => $packagings,
                'default_packaging_id' => $this->defaultPackagingId($packagings),
            ];
        }

        usort($rows, function (array $a, array $b) {
            $available = static fn (array $row) => $row['from_stock'] > 0 ? 0 : 1;
            $diff = $available($a) <=> $available($b);
            if ($diff !== 0) {
                return $diff;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function transfer(
        User $user,
        Business $business,
        int $fromBranchId,
        int $toBranchId,
        string $transferDate,
        array $lines,
        ?string $notes = null
    ): BranchTransfer {
        $fromBranch = Branch::query()
            ->where('business_id', $business->id)
            ->where('id', $fromBranchId)
            ->firstOrFail();
        $toBranch = Branch::query()
            ->where('business_id', $business->id)
            ->where('id', $toBranchId)
            ->firstOrFail();

        if ((int) $fromBranch->id === (int) $toBranch->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Choose a different branch to supply.',
            ]);
        }

        $prepared = [];
        foreach ($lines as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $prepared[] = [
                'from_item_id' => (int) ($line['from_item_id'] ?? 0),
                'item_packaging_id' => (int) ($line['item_packaging_id'] ?? 0),
                'qty' => $qty,
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'items' => 'Enter quantity for at least one item.',
            ]);
        }

        return DB::transaction(function () use ($user, $business, $fromBranch, $toBranch, $transferDate, $prepared, $notes) {
            $toItems = $this->itemsForBranch((int) $business->id, (int) $toBranch->id);
            $toByKey = $toItems->groupBy(fn (Item $item) => $this->matchKey($item));

            $records = [];
            $totalPieces = 0;

            foreach ($prepared as $row) {
                $fromItem = Item::query()
                    ->with(['category', 'packagings.packagingType'])
                    ->where('business_id', $business->id)
                    ->where('id', $row['from_item_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $fromItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected items was not found.',
                    ]);
                }

                $fromItem->loadMissing('category');
                if ((int) ($fromItem->category?->branch_id ?? 0) !== (int) $fromBranch->id) {
                    throw ValidationException::withMessages([
                        'items' => $fromItem->name.' is not stocked on the main branch.',
                    ]);
                }

                $unitQty = (float) $row['qty'];
                $packaging = $this->resolveTransferPackaging($fromItem, (int) ($row['item_packaging_id'] ?? 0));
                $pieces = $unitQty * $packaging['quantity_per_unit'];

                if ($pieces > (float) $fromItem->current_stock) {
                    throw ValidationException::withMessages([
                        'items' => $fromItem->name.' — main branch only has '.(int) $fromItem->current_stock.' pcs. '
                            .$unitQty.' '.$packaging['name'].' = '.(int) $pieces.' pcs.',
                    ]);
                }

                $toItem = $toByKey->get($this->matchKey($fromItem), collect())->first();
                if (! $toItem) {
                    $toItem = $this->cloneItemToBranch($fromItem, $toBranch);
                    $toByKey[$this->matchKey($fromItem)] = collect([$toItem]);
                }

                $toItem = Item::query()->where('id', $toItem->id)->lockForUpdate()->firstOrFail();

                $records[] = [
                    'from_item' => $fromItem,
                    'to_item' => $toItem,
                    'qty' => $pieces,
                    'item_packaging_id' => $packaging['id'] ?: null,
                    'unit_name' => $packaging['name'],
                    'unit_quantity' => $unitQty,
                    'quantity_per_unit' => $packaging['quantity_per_unit'],
                    'from_before' => (float) $fromItem->current_stock,
                    'to_before' => (float) $toItem->current_stock,
                ];
                $totalPieces += $pieces;
            }

            $ref = 'TRF-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $transfer = BranchTransfer::create([
                'business_id' => $business->id,
                'from_branch_id' => $fromBranch->id,
                'to_branch_id' => $toBranch->id,
                'user_id' => $user->id,
                'reference_no' => $ref,
                'transfer_date' => $transferDate,
                'total_items' => count($records),
                'total_pieces' => $totalPieces,
                'notes' => $notes,
                'status' => 'pending',
            ]);

            foreach ($records as $record) {
                BranchTransferItem::create([
                    'branch_transfer_id' => $transfer->id,
                    'from_item_id' => $record['from_item']->id,
                    'to_item_id' => $record['to_item']->id,
                    'item_packaging_id' => $record['item_packaging_id'],
                    'unit_name' => $record['unit_name'],
                    'unit_quantity' => $record['unit_quantity'],
                    'quantity_per_unit' => $record['quantity_per_unit'],
                    'quantity' => $record['qty'],
                    'from_stock_before' => $record['from_before'],
                    'to_stock_before' => $record['to_before'],
                ]);

                $record['from_item']->update([
                    'current_stock' => $record['from_before'] - $record['qty'],
                ]);
            }

            AuditLog::log(
                'BRANCH_TRANSFER',
                "Sent {$transfer->total_items} item(s) ({$transfer->total_pieces} pcs) from {$fromBranch->name} to {$toBranch->name} — {$ref}. Awaiting receive.",
                (int) $business->id
            );

            return $transfer->fresh(['fromBranch', 'toBranch', 'items']);
        });
    }

    public function receive(BranchTransfer $transfer, User $user): void
    {
        if ($transfer->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This supply was cancelled and cannot be received.',
            ]);
        }

        if ($transfer->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'This supply has already been received into stock.',
            ]);
        }

        DB::transaction(function () use ($transfer, $user) {
            $transfer->load(['items.toItem', 'fromBranch', 'toBranch']);

            foreach ($transfer->items as $line) {
                $toItem = Item::query()->where('id', $line->to_item_id)->lockForUpdate()->first();
                if (! $toItem) {
                    throw ValidationException::withMessages([
                        'status' => 'An item on this supply is missing on the destination branch.',
                    ]);
                }

                $toBefore = (float) $toItem->current_stock;
                $toItem->update(['current_stock' => $toBefore + (float) $line->quantity]);
                $line->update(['to_stock_before' => $toBefore]);
            }

            $transfer->update([
                'status' => 'completed',
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            AuditLog::log(
                'RECEIVE_BRANCH_TRANSFER',
                "Received supply {$transfer->reference_no} into {$transfer->toBranch?->name} ({$transfer->total_pieces} pcs)",
                (int) $transfer->business_id
            );
        });
    }

    public function cancel(BranchTransfer $transfer): void
    {
        if ($transfer->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This supply is already cancelled.',
            ]);
        }

        DB::transaction(function () use ($transfer) {
            $transfer->load(['items.fromItem', 'items.toItem', 'fromBranch', 'toBranch']);
            $wasReceived = $transfer->isCompleted();

            foreach ($transfer->items as $line) {
                $fromItem = Item::query()->where('id', $line->from_item_id)->lockForUpdate()->first();
                $toItem = Item::query()->where('id', $line->to_item_id)->lockForUpdate()->first();

                if ($wasReceived) {
                    if ($toItem && (float) $toItem->current_stock < (float) $line->quantity) {
                        throw ValidationException::withMessages([
                            'status' => ($toItem->name ?? 'An item').' on the destination branch no longer has enough stock to reverse this supply.',
                        ]);
                    }

                    if ($toItem) {
                        $toItem->update(['current_stock' => (float) $toItem->current_stock - (float) $line->quantity]);
                    }
                }

                if ($fromItem) {
                    $fromItem->update(['current_stock' => (float) $fromItem->current_stock + (float) $line->quantity]);
                }
            }

            $transfer->update(['status' => 'cancelled']);

            AuditLog::log(
                'CANCEL_BRANCH_TRANSFER',
                ($wasReceived ? 'Undid received supply' : 'Recalled pending supply')." {$transfer->reference_no} ({$transfer->fromBranch?->name} → {$transfer->toBranch?->name})",
                (int) $transfer->business_id
            );
        });
    }

    /**
     * @return Collection<int, Item>
     */
    private function itemsForBranch(int $businessId, int $branchId): Collection
    {
        return Item::query()
            ->where('business_id', $businessId)
            ->whereHas('category', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['category', 'packagings.packagingType', 'receivingPackaging'])
            ->orderBy('name')
            ->get();
    }

    private function cloneItemToBranch(Item $fromItem, Branch $toBranch): Item
    {
        $fromItem->loadMissing(['category', 'packagings']);

        $item = Item::create([
            'business_id' => $fromItem->business_id,
            'category_id' => $this->destinationCategoryId($fromItem->category, $toBranch),
            'receiving_packaging_id' => $fromItem->receiving_packaging_id,
            'units_per_receiving_pack' => $fromItem->units_per_receiving_pack,
            'name' => $fromItem->name,
            'sku' => 'SP-'.strtoupper(bin2hex(random_bytes(4))),
            'brand' => $fromItem->brand,
            'description' => $fromItem->description,
            'current_stock' => 0,
        ]);

        $created = [];
        foreach ($fromItem->packagings as $packaging) {
            $created[] = ItemPackaging::create([
                'item_id' => $item->id,
                'packaging_id' => $packaging->packaging_id,
                'quantity_per_unit' => $packaging->quantity_per_unit,
                'cost_price' => $packaging->cost_price,
                'selling_price' => $packaging->selling_price,
            ]);
        }

        $this->barcodes->assignMissingBarcodes((int) $item->business_id, $created);

        return $item;
    }

    private function destinationCategoryId(?Category $fromCategory, Branch $toBranch): ?int
    {
        if (! $fromCategory) {
            return null;
        }

        $query = Category::query()
            ->where('business_id', $fromCategory->business_id)
            ->where('branch_id', $toBranch->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $fromCategory->name))]);

        $typeKey = trim((string) ($fromCategory->source_business_type_key ?? ''));
        if ($typeKey !== '') {
            $query->where('source_business_type_key', $typeKey);
        }

        $existing = $query->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) Category::create([
            'business_id' => $fromCategory->business_id,
            'branch_id' => $toBranch->id,
            'name' => $fromCategory->name,
            'source_business_type_key' => $fromCategory->source_business_type_key,
        ])->id;
    }

    /**
     * @return list<array{id: int, name: string, quantity_per_unit: int, max_qty: int}>
     */
    private function packagingOptions(Item $item, float $fromStock): array
    {
        $item->loadMissing(['packagings.packagingType']);
        $normalized = $this->packagingNormalizer->normalizeItemPackagings($item, $item->packagings);

        $options = $normalized
            ->map(function (array $row) use ($fromStock) {
                $pkg = $row['packaging'];
                $qpu = max(1, (int) $row['quantity_per_unit']);

                return [
                    'id' => (int) $pkg->id,
                    'name' => $pkg->packagingType->name ?? 'Unit',
                    'quantity_per_unit' => $qpu,
                    'max_qty' => (int) floor($fromStock / $qpu),
                ];
            })
            ->unique(fn (array $option) => strtolower($option['name']).'|'.$option['quantity_per_unit'])
            ->sortBy('quantity_per_unit')
            ->values();

        if (! $options->contains(fn (array $option) => $option['quantity_per_unit'] === 1)) {
            $options->prepend([
                'id' => 0,
                'name' => 'Piece',
                'quantity_per_unit' => 1,
                'max_qty' => (int) floor($fromStock),
            ]);
        }

        return $options->values()->all();
    }

    /**
     * @param  list<array{id: int, name: string, quantity_per_unit: int, max_qty: int}>  $options
     */
    private function defaultPackagingId(array $options): int
    {
        $piece = collect($options)->firstWhere('quantity_per_unit', 1);

        return (int) ($piece['id'] ?? ($options[0]['id'] ?? 0));
    }

    /**
     * @return array{id: int, name: string, quantity_per_unit: int}
     */
    private function resolveTransferPackaging(Item $item, int $itemPackagingId): array
    {
        $item->loadMissing(['packagings.packagingType']);

        if ($itemPackagingId > 0) {
            $pkg = $item->packagings->firstWhere('id', $itemPackagingId);
            if ($pkg) {
                return [
                    'id' => (int) $pkg->id,
                    'name' => $pkg->packagingType->name ?? 'Unit',
                    'quantity_per_unit' => $item->effectiveQuantityPerUnit($pkg),
                ];
            }
        }

        return [
            'id' => 0,
            'name' => 'Piece',
            'quantity_per_unit' => 1,
        ];
    }

    private function matchKey(Item $item): string
    {
        return mb_strtolower(trim((string) $item->name)).'|'.mb_strtolower(trim((string) ($item->brand ?? '')));
    }
}
