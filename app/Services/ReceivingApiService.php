<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Item;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceivingApiService
{
    public function __construct(
        private ItemStockDisplayService $stockDisplay,
        private ItemPackagingNormalizer $packagingNormalizer,
        private ReceivingReportService $reportService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCreateForm(User $user, int $businessId, ?int $branchFilterId): array
    {
        $business = Business::findOrFail($businessId);

        $suppliersQuery = Supplier::where('business_id', $businessId)->orderBy('name');
        if ($branchFilterId) {
            $suppliersQuery->where('branch_id', $branchFilterId);
        }
        $suppliers = $suppliersQuery->get();

        $branches = Branch::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultBranchId = $branchFilterId
            ?? ($user->branch_id ? (int) $user->branch_id : null)
            ?? $branches->firstWhere('is_default', true)?->id
            ?? $branches->first()?->id;

        $selectedBranchId = (int) $defaultBranchId;
        $importedTypesByBranch = $this->importedTypesByBranch($business, $branches);
        $businessTypes = $importedTypesByBranch[$selectedBranchId] ?? [];
        $multiBusiness = count($businessTypes) > 1;

        $categories = Category::where('business_id', $businessId)
            ->has('items')
            ->orderBy('name')
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->get();

        $categoryBranchMap = $categories->mapWithKeys(fn ($cat) => [
            (int) $cat->id => (int) $cat->branch_id,
        ])->all();

        $itemsByCategory = Category::where('business_id', $businessId)
            ->has('items')
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->with(['items.packagings.packagingType', 'items.receivingPackaging'])
            ->get()
            ->mapWithKeys(function ($cat) {
                return [(int) $cat->id => $cat->items->map(fn (Item $item) => $this->itemSelectorPayload($item))->values()->all()];
            })
            ->all();

        return [
            'suppliers' => $suppliers->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'email' => $s->email,
                'region' => $s->region,
                'branch_id' => $s->branch_id,
            ])->values()->all(),
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'branch_id' => $c->branch_id,
                'source_business_type_key' => $c->source_business_type_key,
            ])->values()->all(),
            'items_by_category' => $itemsByCategory,
            'category_branch_map' => $categoryBranchMap,
            'business_types' => $businessTypes,
            'imported_types_by_branch' => $importedTypesByBranch,
            'branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_default' => (bool) $b->is_default,
            ])->values()->all(),
            'default_branch_id' => $defaultBranchId,
            'selected_branch_id' => $selectedBranchId,
            'multi_business' => $multiBusiness,
            'defaults' => [
                'received_date' => now()->toDateString(),
                'qty_mode' => 'pkg',
                'cost_mode' => 'pkg',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listReceivings(User $user, int $businessId, ?int $branchFilterId, Request $request): array
    {
        $dateFilter = $this->reportService->parseDateFilter($request);
        $statusFilter = $request->get('status');
        $statusFilter = in_array($statusFilter, ['completed', 'cancelled'], true) ? $statusFilter : null;
        $businessTypeFilter = $request->get('business_type');

        $business = Business::findOrFail($businessId);
        $viewingAllBranches = $user->seesBusinessWideData() && ! $branchFilterId;

        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(fn ($type) => [
                    'key' => (string) ($type['key'] ?? ''),
                    'label' => (string) ($type['label'] ?? $type['key'] ?? ''),
                ])
                ->values()
                ->all();
        } else {
            $businessTypes = collect($business->posBusinessTypesMeta())
                ->map(fn ($type) => [
                    'key' => (string) ($type['key'] ?? ''),
                    'label' => (string) ($type['label'] ?? $type['key'] ?? ''),
                ])
                ->values()
                ->all();
        }

        $query = Receiving::query()
            ->where('business_id', $businessId)
            ->with(['supplier:id,name', 'user:id,name', 'branch:id,name', 'items']);

        if (! $user->seesBusinessWideData()) {
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        } elseif ($branchFilterId) {
            $query->where('branch_id', $branchFilterId);
        }

        if ($dateFilter['from_c']) {
            $query->whereDate('received_date', '>=', $dateFilter['from_c']->toDateString());
        }
        if ($dateFilter['to_c']) {
            $query->whereDate('received_date', '<=', $dateFilter['to_c']->toDateString());
        }

        if ($statusFilter === 'completed') {
            $query->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            });
        } elseif ($statusFilter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        if ($businessTypeFilter && $businessTypeFilter !== 'all') {
            $query->whereHas('items.item.category', fn ($q) => $q->where('source_business_type_key', $businessTypeFilter));
        }

        $receivings = $query->latest('received_date')->latest('id')->paginate(min(50, (int) $request->get('per_page', 20)));

        $completed = collect($receivings->items())->filter(fn ($r) => ($r->status ?? 'completed') !== 'cancelled');
        $cancelled = collect($receivings->items())->filter(fn ($r) => ($r->status ?? 'completed') === 'cancelled');

        return [
            'receivings' => collect($receivings->items())->map(fn (Receiving $r) => $this->receivingSummary($r))->values()->all(),
            'stats' => [
                'total_records' => $receivings->total(),
                'completed' => $completed->count(),
                'cancelled' => $cancelled->count(),
                'total_amount' => (float) $completed->sum('total_amount'),
                'total_items' => (int) $completed->sum(fn ($r) => $r->items->count()),
            ],
            'meta' => [
                'current_page' => $receivings->currentPage(),
                'last_page' => $receivings->lastPage(),
                'per_page' => $receivings->perPage(),
                'total' => $receivings->total(),
                'branch_filter_id' => $branchFilterId,
                'viewing_all_branches' => $viewingAllBranches,
                'multi_business' => count($businessTypes) > 1,
                'business_types' => $businessTypes,
                'date_filter' => [
                    'period' => $dateFilter['period'],
                    'from' => $dateFilter['from'],
                    'to' => $dateFilter['to'],
                    'label' => $dateFilter['label'],
                ],
            ],
        ];
    }

    public function createReceiving(User $user, int $businessId, ?int $tenantBranchId, array $input): Receiving
    {
        $validated = validator($input, [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('business_id', $businessId),
            ],
            'received_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('business_id', $businessId),
            ],
            'items.*.qty' => 'required|integer|min:1',
            'items.*.qty_mode' => 'nullable|in:pkg,piece',
            'items.*.cost' => 'required|numeric|min:0',
            'items.*.cost_mode' => 'nullable|in:pkg,unit',
            'items.*.selling' => 'nullable|numeric|min:0',
            'items.*.selling_prices' => 'nullable|array',
            'items.*.selling_prices.*' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ])->validate();

        $activeItems = array_values(array_filter($validated['items'], fn ($i) => ($i['qty'] ?? 0) > 0));
        if ($activeItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Please enter at least one item with quantity > 0.',
            ]);
        }

        foreach ($activeItems as $i) {
            $item = Item::with('packagings.packagingType')->find($i['id']);
            if ($priceError = $this->validateRetailAgainstBuying($item, $i)) {
                throw ValidationException::withMessages(['items' => $priceError]);
            }
        }

        DB::beginTransaction();

        try {
            $ref = 'RCV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $totalAmount = 0.0;
            foreach ($activeItems as $i) {
                $itemForTotal = Item::find($i['id']);
                $totalAmount += $this->receivingLineNetCost($itemForTotal, $i);
            }

            $branchId = $this->resolveReceivingBranchId($user, $businessId, $tenantBranchId, $validated);

            $receiving = Receiving::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'supplier_id' => $validated['supplier_id'],
                'user_id' => $user->id,
                'reference_no' => $ref,
                'received_date' => $validated['received_date'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
            ]);

            foreach ($activeItems as $i) {
                $item = Item::with('packagings.packagingType')->find($i['id']);
                $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
                $qtyMode = $this->resolveQtyMode($i);
                $pieces = $this->receivingPieces($item, $i);
                $lineGross = $this->receivingLineGross($item, $i);

                $discountAmount = 0.0;
                if (! empty($i['discount_type']) && ! empty($i['discount_value'])) {
                    if ($i['discount_type'] === 'percent') {
                        $discountAmount = $lineGross * ($i['discount_value'] / 100);
                    } else {
                        $discountAmount = (float) $i['discount_value'];
                    }
                }

                $sellingPrices = $i['selling_prices'] ?? [];
                $costMode = ($i['cost_mode'] ?? 'pkg') === 'unit' ? 'unit' : 'pkg';

                ReceivingItem::create([
                    'receiving_id' => $receiving->id,
                    'item_id' => $i['id'],
                    'quantity' => $i['qty'],
                    'qty_mode' => $qtyMode,
                    'cost_price' => $i['cost'],
                    'cost_mode' => $costMode,
                    'selling_price' => $this->resolvePerPieceSellingPrice($item, $i),
                    'selling_prices_snapshot' => ! empty($sellingPrices) ? $sellingPrices : null,
                    'discount_type' => $i['discount_type'] ?? null,
                    'discount_value' => $i['discount_value'] ?? 0,
                    'discount_amount' => $discountAmount,
                ]);

                $item->current_stock += $pieces;
                $item->save();

                $costPerPiece = $costMode === 'unit'
                    ? (float) $i['cost']
                    : (float) $i['cost'] / $unitsPerReceiving;

                foreach ($item->packagings as $packaging) {
                    $qpu = max(1, (int) $packaging->quantity_per_unit);
                    $sellPrice = isset($sellingPrices[$packaging->id])
                        ? (float) $sellingPrices[$packaging->id]
                        : (isset($i['selling'])
                            ? round(((float) $i['selling'] / $unitsPerReceiving) * $qpu, 2)
                            : (float) $packaging->selling_price);

                    $packaging->update([
                        'cost_price' => round($costPerPiece * $qpu, 2),
                        'selling_price' => round(max(0, $sellPrice), 2),
                    ]);
                }
            }

            DB::commit();

            try {
                app(BusinessStaffSmsService::class)->notifyStockReceived(
                    $receiving->fresh(['business.plan', 'supplier', 'user', 'branch', 'items.item.receivingPackaging'])
                );
            } catch (\Throwable $e) {
                Log::warning('Stock received SMS notification failed', [
                    'receiving_id' => $receiving->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $receiving->fresh(['supplier', 'user', 'branch', 'items.item.packagings.packagingType', 'items.item.receivingPackaging']);
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelReceiving(User $user, Receiving $receiving, bool $partialOk): array
    {
        if ($receiving->status === 'cancelled') {
            throw ValidationException::withMessages([
                'receiving' => 'This receiving has already been cancelled.',
            ]);
        }

        $receiving->load(['items.item.packagings']);
        $plan = $this->buildReversalPlan($receiving);

        if ($plan['requires_partial'] && ! $partialOk) {
            throw ValidationException::withMessages([
                'partial_cancel_required' => 'Some stock from this receiving was already sold. Confirm partial cancellation.',
            ]);
        }

        DB::beginTransaction();

        try {
            $soldNotes = [];

            foreach ($plan['items'] as $row) {
                $item = $row['item'];

                if ($row['reversible'] > 0) {
                    $item->current_stock -= $row['reversible'];
                    $item->save();
                }

                if ($row['not_reversible'] > 0) {
                    $soldNotes[] = "{$item->name}: ".$this->formatQty($row['not_reversible']).' already sold';
                }
            }

            $notes = $receiving->notes ?? '';
            if ($plan['requires_partial']) {
                $notes = trim($notes.' [Partially cancelled on '.now()->format('Y-m-d H:i').'. Some stock was already sold.]');
            }

            $receiving->update([
                'status' => 'cancelled',
                'notes' => $notes ?: null,
            ]);

            DB::commit();

            $message = "Receiving ({$receiving->reference_no}) has been cancelled.";
            if (! empty($soldNotes)) {
                $message .= ' '.implode('; ', $soldNotes).'.';
            } else {
                $message .= ' Stock has been reversed.';
            }

            return [
                'message' => $message,
                'partial' => $plan['requires_partial'],
                'sold_notes' => $soldNotes,
                'receiving' => $this->receivingDetail($receiving->fresh(['supplier', 'user', 'branch', 'items.item.packagings.packagingType', 'items.item.receivingPackaging']), $user),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function partialCancelPreview(Receiving $receiving): array
    {
        $receiving->load(['items.item']);
        $plan = $this->buildReversalPlan($receiving);

        return [
            'receiving_id' => $receiving->id,
            'reference_no' => $receiving->reference_no,
            'requires_partial' => $plan['requires_partial'],
            'items' => collect($plan['items'])->map(fn ($row) => [
                'item_id' => $row['item']->id,
                'name' => $row['item']->name,
                'stock_to_remove' => (float) $row['stock_to_remove'],
                'reversible' => (float) $row['reversible'],
                'not_reversible' => (float) $row['not_reversible'],
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receivingDetail(Receiving $receiving, User $user): array
    {
        $receiving->loadMissing(['items.item.packagings.packagingType', 'items.item.receivingPackaging', 'supplier', 'user', 'branch']);

        $lineMetrics = $receiving->items->mapWithKeys(function (ReceivingItem $line) {
            return [$line->id => $this->buildReceivingLineMetrics($line)];
        });

        $document = $this->reportService->showViewData($receiving, $lineMetrics, $user);

        return [
            'receiving' => $this->receivingSummary($receiving, true),
            'lines' => $receiving->items->map(function (ReceivingItem $line) use ($lineMetrics) {
                $metrics = $lineMetrics[$line->id] ?? [];

                return [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item?->name,
                    'sku' => $line->item?->sku,
                    'quantity' => (int) $line->quantity,
                    'qty_mode' => $line->qty_mode ?? 'pkg',
                    'quantity_label' => $metrics['quantity_label'] ?? $line->receivedQuantityLabel($line->item),
                    'total_pieces' => $metrics['total_pieces'] ?? $line->receivedPieces($line->item),
                    'cost_price' => (float) $line->cost_price,
                    'cost_mode' => $line->cost_mode ?? 'pkg',
                    'selling_price' => (float) $line->selling_price,
                    'selling_prices_snapshot' => $line->selling_prices_snapshot,
                    'discount_type' => $line->discount_type,
                    'discount_value' => (float) ($line->discount_value ?? 0),
                    'discount_amount' => (float) ($line->discount_amount ?? 0),
                    'packaging_prices' => $metrics['packaging_prices'] ?? [],
                    'net_cost' => (float) ($metrics['net_cost'] ?? 0),
                    'expected_revenue' => (float) ($metrics['expected_revenue'] ?? 0),
                    'expected_profit' => (float) ($metrics['expected_profit'] ?? 0),
                ];
            })->values()->all(),
            'totals' => [
                'net_cost' => (float) ($document['totals']['net_cost'] ?? 0),
                'expected_revenue' => (float) ($document['totals']['expected_revenue'] ?? 0),
                'expected_profit' => (float) ($document['totals']['expected_profit'] ?? 0),
            ],
            'is_cancelled' => ($receiving->status ?? 'completed') === 'cancelled',
        ];
    }

    private function receivingSummary(Receiving $receiving, bool $detailed = false): array
    {
        $payload = [
            'id' => $receiving->id,
            'reference_no' => $receiving->reference_no,
            'received_date' => $receiving->received_date
                ? (\Illuminate\Support\Carbon::parse($receiving->received_date)->toDateString())
                : null,
            'status' => ($receiving->status ?? 'completed') === 'cancelled' ? 'cancelled' : 'completed',
            'total_amount' => (float) $receiving->total_amount,
            'items_count' => $receiving->relationLoaded('items') ? $receiving->items->count() : (int) $receiving->items()->count(),
            'supplier' => $receiving->supplier ? [
                'id' => $receiving->supplier->id,
                'name' => $receiving->supplier->name,
            ] : null,
            'branch' => $receiving->branch ? [
                'id' => $receiving->branch->id,
                'name' => $receiving->branch->name,
            ] : null,
            'received_by' => $receiving->user ? [
                'id' => $receiving->user->id,
                'name' => $receiving->user->name,
            ] : null,
        ];

        if ($detailed) {
            $payload['notes'] = $receiving->notes;
            $payload['created_at'] = $receiving->created_at?->toIso8601String();
        }

        return $payload;
    }

    private function itemSelectorPayload(Item $item): array
    {
        $packagings = $item->packagings->sortBy('quantity_per_unit')->values();
        $receivingPkg = $packagings->firstWhere('packaging_id', $item->receiving_packaging_id)
            ?? $packagings->sortByDesc('quantity_per_unit')->first();

        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku ?? '',
            'unit' => optional($item->receivingPackaging)->name ?? 'Unit',
            'units_per_receiving_pack' => (int) ($item->units_per_receiving_pack ?? 1),
            'current_stock' => (float) $item->current_stock,
            'remains_display' => $this->stockDisplay->remainsDisplay($item),
            'cost_price' => (float) (optional($receivingPkg)->cost_price ?? $packagings->first()?->cost_price ?? 0),
            'selling_price' => (float) (optional($packagings->first())->selling_price ?? 0),
            'packagings' => $packagings->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->packagingType->name ?? 'Unit',
                'quantity_per_unit' => (int) $p->quantity_per_unit,
                'cost_price' => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
            ])->values()->all(),
        ];
    }

    private function resolveReceivingBranchId(User $user, int $businessId, ?int $tenantBranchId, array $input): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if (! empty($input['branch_id'])) {
            $branchId = (int) $input['branch_id'];

            Branch::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->where('id', $branchId)
                ->firstOrFail();

            return $branchId;
        }

        if ($tenantBranchId) {
            return $tenantBranchId;
        }

        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        return Branch::query()
            ->where('business_id', $businessId)
            ->where('is_default', true)
            ->value('id')
            ?? Branch::query()->where('business_id', $businessId)->value('id');
    }

    private function validateRetailAgainstBuying(Item $item, array $line): ?string
    {
        $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $cost = (float) ($line['cost'] ?? 0);
        $pieces = $this->receivingPieces($item, $line);

        $gross = $this->receivingLineGross($item, $line);
        $discountAmount = 0.0;

        if (! empty($line['discount_type']) && ! empty($line['discount_value'])) {
            if ($line['discount_type'] === 'percent') {
                $discountAmount = $gross * ((float) $line['discount_value'] / 100);
            } else {
                $discountAmount = (float) $line['discount_value'];
            }
        }

        $buyPerPiece = ($pieces > 0 ? max(0, $gross - $discountAmount) / $pieces : (
            ($line['cost_mode'] ?? 'pkg') === 'unit' ? $cost : $cost / $unitsPerReceiving
        ));

        $sellingPrices = $line['selling_prices'] ?? [];
        $packagings = $item->packagings->sortBy('quantity_per_unit')->values();

        if ($packagings->isEmpty()) {
            $sell = (float) ($line['selling'] ?? 0);
            if ($sell > 0 && $sell + 0.009 < $buyPerPiece) {
                return "{$item->name}: retail price (TZS ".number_format($sell, 0).') cannot be lower than buying cost (TZS '.number_format($buyPerPiece, 0).').';
            }

            return null;
        }

        foreach ($packagings as $packaging) {
            $qpu = max(1, (int) $packaging->quantity_per_unit);
            $minSell = round($buyPerPiece * $qpu, 2);
            $sell = isset($sellingPrices[$packaging->id])
                ? (float) $sellingPrices[$packaging->id]
                : ($packagings->count() === 1 ? (float) ($line['selling'] ?? 0) : 0);

            if ($sell <= 0) {
                continue;
            }

            if ($sell + 0.009 < $minSell) {
                $unitName = $packaging->packagingType->name ?? 'unit';

                return "{$item->name} ({$unitName}): retail price (TZS ".number_format($sell, 0).') cannot be lower than buying cost (TZS '.number_format($minSell, 0).').';
            }
        }

        return null;
    }

    private function buildReversalPlan(Receiving $receiving): array
    {
        $items = [];
        $requiresPartial = false;

        foreach ($receiving->items as $receivingItem) {
            $item = $receivingItem->item;
            $unitsPerPack = max(1, (int) ($item->units_per_receiving_pack ?? $item->packagings->first()->quantity_per_unit ?? 1));
            $qtyMode = $receivingItem->qty_mode ?? 'pkg';
            $stockToRemove = $qtyMode === 'piece'
                ? (float) $receivingItem->quantity
                : $receivingItem->quantity * $unitsPerPack;
            $reversible = min((float) $item->current_stock, (float) $stockToRemove);

            if ($reversible < $stockToRemove) {
                $requiresPartial = true;
            }

            $items[] = [
                'item' => $item,
                'stock_to_remove' => $stockToRemove,
                'reversible' => $reversible,
                'not_reversible' => $stockToRemove - $reversible,
            ];
        }

        return [
            'items' => $items,
            'requires_partial' => $requiresPartial,
        ];
    }

    private function formatQty(float $qty): string
    {
        return fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : number_format($qty, 2);
    }

    private function resolvePerPieceSellingPrice(Item $item, array $line): float
    {
        $packagings = $item->packagings->sortBy('quantity_per_unit')->values();
        $sellingPrices = $line['selling_prices'] ?? [];

        if ($packagings->isNotEmpty() && ! empty($sellingPrices)) {
            $primary = $packagings->first();
            $pkgPrice = (float) ($sellingPrices[$primary->id] ?? $line['selling'] ?? 0);

            return round($pkgPrice / max(1, (int) $primary->quantity_per_unit), 2);
        }

        $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $sell = (float) ($line['selling'] ?? 0);

        if ($packagings->count() <= 1 && $unitsPerReceiving > 1) {
            return round($sell / $unitsPerReceiving, 2);
        }

        return round($sell, 2);
    }

    private function buildReceivingLineMetrics(ReceivingItem $line): array
    {
        $item = $line->item;
        $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
        $normalized = $this->packagingNormalizer->normalizeItemPackagings($item, $packagingModels);
        $unitsPerReceiving = $this->packagingNormalizer->effectiveUnitsPerReceivingPack($item, $packagingModels);
        $qtyMode = $line->qty_mode ?? 'pkg';
        $totalPieces = $qtyMode === 'piece'
            ? (int) $line->quantity
            : $line->quantity * $unitsPerReceiving;
        $receivingUnit = $qtyMode === 'piece'
            ? 'Piece'
            : (optional($item->receivingPackaging)->name ?? 'Unit');

        $grossCost = $this->receivingLineGross($item, [
            'qty' => (int) $line->quantity,
            'cost' => (float) $line->cost_price,
            'qty_mode' => $qtyMode,
            'cost_mode' => $line->cost_mode ?? 'pkg',
        ]);
        $discountAmount = (float) ($line->discount_amount ?? 0);
        $netCost = max(0, $grossCost - $discountAmount);

        $snapshot = $line->selling_prices_snapshot ?? [];

        $packagingPrices = [];
        foreach ($normalized as $row) {
            $pkg = $row['packaging'];
            $price = isset($snapshot[$pkg->id])
                ? (float) $snapshot[$pkg->id]
                : (float) $pkg->selling_price;

            $packagingPrices[] = [
                'name' => $pkg->packagingType->name ?? 'Unit',
                'quantity_per_unit' => (int) $row['quantity_per_unit'],
                'selling_price' => $price,
            ];
        }

        $piecePriceRow = collect($packagingPrices)->firstWhere('quantity_per_unit', 1)
            ?? collect($packagingPrices)->sortBy('quantity_per_unit')->first();

        $sellPerPiece = $piecePriceRow
            ? round($piecePriceRow['selling_price'] / max(1, $piecePriceRow['quantity_per_unit']), 2)
            : (float) $line->selling_price;

        $expectedRevenue = round($totalPieces * $sellPerPiece, 2);
        $expectedProfit = round($expectedRevenue - $netCost, 2);

        $quantityLabel = $qtyMode === 'piece'
            ? "{$line->quantity} pcs"
            : ($unitsPerReceiving > 1
                ? "{$line->quantity} {$receivingUnit} ({$totalPieces} pcs)"
                : (string) $line->quantity);

        return [
            'quantity_label' => $quantityLabel,
            'total_pieces' => $totalPieces,
            'receiving_unit' => $receivingUnit,
            'packaging_prices' => $packagingPrices,
            'sell_per_piece' => $sellPerPiece,
            'net_cost' => $netCost,
            'discount_amount' => $discountAmount,
            'expected_revenue' => $expectedRevenue,
            'expected_profit' => $expectedProfit,
        ];
    }

    /**
     * @return array<int, list<array{key: string, label: string, categories: list<string>}>>
     */
    private function importedTypesByBranch(?Business $business, $branches): array
    {
        if (! $business) {
            return [];
        }

        $map = [];

        foreach ($branches as $branch) {
            $map[(int) $branch->id] = $business->importedTypesForBranch((int) $branch->id);
        }

        return $map;
    }

    private function resolveQtyMode(array $line): string
    {
        return ($line['qty_mode'] ?? 'pkg') === 'piece' ? 'piece' : 'pkg';
    }

    private function receivingPieces(Item $item, array $line): int
    {
        $qty = max(0, (int) ($line['qty'] ?? 0));
        $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));

        return $this->resolveQtyMode($line) === 'piece'
            ? $qty
            : $qty * $unitsPerReceiving;
    }

    private function receivingLineGross(Item $item, array $line): float
    {
        $qty = max(0, (int) ($line['qty'] ?? 0));
        $cost = (float) ($line['cost'] ?? 0);
        $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $costMode = ($line['cost_mode'] ?? 'pkg') === 'unit' ? 'unit' : 'pkg';
        $pieces = $this->receivingPieces($item, $line);

        if ($costMode === 'unit') {
            return $pieces * $cost;
        }

        if ($this->resolveQtyMode($line) === 'piece') {
            return ($pieces / $unitsPerReceiving) * $cost;
        }

        return $qty * $cost;
    }

    private function receivingLineNetCost(Item $item, array $line): float
    {
        $subtotal = $this->receivingLineGross($item, $line);

        if (! empty($line['discount_type']) && ! empty($line['discount_value'])) {
            if ($line['discount_type'] === 'percent') {
                $subtotal -= $subtotal * ((float) $line['discount_value'] / 100);
            } else {
                $subtotal -= (float) $line['discount_value'];
            }
        }

        return max(0, $subtotal);
    }
}
