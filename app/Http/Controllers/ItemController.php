<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Packaging;
use App\Models\ItemPackaging;
use App\Models\ReceivingItem;
use App\Models\SaleItem;
use App\Models\StockLossItem;
use App\Services\ItemPackagingNormalizer;
use App\Services\ItemStockDisplayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('view_inventory');

        $business = Auth::user()->business;
        $businessId = $business->id;

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        $multiBusiness = count($businessTypes) > 1;

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        $itemsQuery = Item::where('business_id', $businessId)
            ->with(['category', 'packagings.packagingType']);

        if ($branchFilterId) {
            $itemsQuery->whereHas('category', fn ($query) => $query->where('branch_id', $branchFilterId));
        }

        $items = $itemsQuery->orderBy('name')->get();

        return view('items.index', compact(
            'items',
            'business',
            'businessTypes',
            'multiBusiness',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
        ));
    }

    public function stock(Request $request)
    {
        $this->authorizeAny(['view_stock_history', 'view_inventory']);

        $business = Auth::user()->business;
        $businessId = $business->id;
        $automation = $business->automationSettings();
        $lowStockThreshold = (int) ($automation['low_stock_threshold'] ?? 5);

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        $multiBusiness = count($businessTypes) > 1;

        $branchReceivedPieces = $branchFilterId
            ? $this->branchReceivedPiecesMap($businessId, $branchFilterId)
            : collect();

        $itemsQuery = Item::where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->whereNotNull('category_id')
            ->with(['category', 'packagings.packagingType', 'receivingPackaging'])
            ->orderBy('name');

        if ($branchFilterId) {
            $itemsQuery->whereHas('category', fn ($query) => $query->where('branch_id', $branchFilterId));
        }

        $items = $itemsQuery->get();

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? Auth::user()->branch?->name ?? 'Branch')
            : null;

        $stockItems = $items->map(function ($item) use ($lowStockThreshold, $business, $branchFilterId, $branchReceivedPieces) {
            $normalizer = app(ItemPackagingNormalizer::class);
            $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
            $normalized = $normalizer->normalizeItemPackagings($item, $packagingModels);

            $packagingPrices = $normalized->map(function ($row) {
                $pkg = $row['packaging'];

                return [
                    'name' => $pkg->packagingType->name ?? 'Unit',
                    'quantity_per_unit' => (int) $row['quantity_per_unit'],
                    'selling_price' => (float) $pkg->selling_price,
                ];
            })->values()->all();

            $defaultRow = collect($packagingPrices)->firstWhere('quantity_per_unit', 1)
                ?? ($packagingPrices[0] ?? null);
            $sellingPrice = (float) ($defaultRow['selling_price'] ?? 0);
            $sellPerPiece = $defaultRow
                ? $defaultRow['selling_price'] / max(1, $defaultRow['quantity_per_unit'])
                : $sellingPrice;

            $pkg = $item->packagings->first();
            $unitName = optional($item->receivingPackaging)->name
                ?? $pkg->packagingType->name
                ?? 'Unit';
            $costPrice = (float) (optional($pkg)->cost_price ?? 0);
            $pieces = (float) $item->current_stock;
            $stockInfo = app(ItemStockDisplayService::class)->format($item, $pieces);
            $categoryName = $item->category->name;
            $businessTypeKey = $item->category->source_business_type_key ?: 'other';
            $isLow = $pieces <= $lowStockThreshold;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'brand' => trim((string) ($item->brand ?? '')),
                'category' => $categoryName,
                'category_slug' => \Illuminate\Support\Str::slug($categoryName),
                'business_type_key' => $businessTypeKey,
                'business_type_label' => $business->businessTypeLabel($businessTypeKey),
                'unit' => $unitName,
                'quantity' => $pieces,
                'stock_pieces' => $pieces,
                'stock_bulk_count' => $stockInfo['bulk_count'],
                'stock_bulk_name' => $stockInfo['bulk_name'],
                'formatted_quantity' => $stockInfo['formatted_pieces'],
                'stock_display' => $stockInfo['stock_display'],
                'has_bulk_stock' => $stockInfo['has_bulk_stock'],
                'packaging_breakdown' => $stockInfo['packaging_breakdown'] ?? [],
                'selling_price' => $sellingPrice,
                'packaging_prices' => $packagingPrices,
                'has_multi_packaging' => count($packagingPrices) > 1,
                'cost_price' => $costPrice,
                'has_price' => collect($packagingPrices)->contains(fn ($p) => $p['selling_price'] > 0) || $costPrice > 0,
                'holding_value' => $pieces * $sellPerPiece,
                'is_low_stock' => $isLow,
                'status_color' => $isLow ? 'warning' : 'success',
                'history_url' => route('items.history', $item->id),
                'branch_received_pieces' => $branchFilterId
                    ? (float) ($branchReceivedPieces[$item->id] ?? 0)
                    : null,
            ];
        });

        $categoryFilters = $stockItems
            ->unique(fn ($item) => $item['category_slug'] . '|' . $item['business_type_key'])
            ->map(fn ($item) => [
                'name' => $item['category'],
                'slug' => $item['category_slug'],
                'business_type_key' => $item['business_type_key'],
            ])
            ->sortBy('name')
            ->values();

        $stats = [
            'total_items' => $stockItems->count(),
            'low_stock' => $stockItems->where('is_low_stock', true)->count(),
        ];

        $totalValue = $stockItems->sum('holding_value');
        $canViewValue = $this->actsAsBusinessWideViewer();

        return view('items.stock', compact(
            'stockItems',
            'categoryFilters',
            'businessTypes',
            'multiBusiness',
            'stats',
            'lowStockThreshold',
            'totalValue',
            'canViewValue',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
        ));
    }

    private function branchReceivedPiecesMap(int $businessId, int $branchId): \Illuminate\Support\Collection
    {
        return ReceivingItem::query()
            ->selectRaw('item_id, SUM(quantity * COALESCE(items.units_per_receiving_pack, 1)) as total_pieces')
            ->join('receivings', 'receiving_items.receiving_id', '=', 'receivings.id')
            ->join('items', 'receiving_items.item_id', '=', 'items.id')
            ->where('receivings.business_id', $businessId)
            ->where('receivings.branch_id', $branchId)
            ->where('receivings.status', '!=', 'cancelled')
            ->groupBy('item_id')
            ->pluck('total_pieces', 'item_id');
    }

    public function history(Item $item)
    {
        $this->authorizeAny(['view_stock_history', 'view_inventory']);

        if ($item->business_id != Auth::user()->business_id) {
            abort(403);
        }

        $item->load(['category', 'packagings.packagingType', 'receivingPackaging']);

        $unitsPerPack = max(1, (int) ($item->units_per_receiving_pack ?? $item->packagings->first()->quantity_per_unit ?? 1));
        $unitName = $item->baseStockUnitName();
        $receivingUnitName = $item->receivingPackaging->name ?? 'Unit';

        $movements = collect();

        $receivingItems = ReceivingItem::where('item_id', $item->id)
            ->whereHas('receiving', fn ($q) => $q->where('business_id', Auth::user()->business_id))
            ->with(['receiving.supplier', 'receiving.user'])
            ->get();

        foreach ($receivingItems as $receivingItem) {
            $receiving = $receivingItem->receiving;
            $stockQty = $receivingItem->quantity * $unitsPerPack;
            $cancelled = ($receiving->status ?? 'completed') === 'cancelled';

            $movements->push([
                'sort_date' => $receiving->received_date . ' ' . ($receiving->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $receiving->received_date,
                'time' => $receiving->created_at?->format('h:i A') ?? '',
                'type' => 'stock_in',
                'type_label' => $cancelled ? 'Stock In (Cancelled)' : 'Stock In',
                'badge' => $cancelled ? 'secondary' : 'success',
                'reference' => $receiving->reference_no,
                'reference_url' => route('receivings.show', $receiving->id),
                'quantity' => $stockQty,
                'quantity_label' => '+' . $this->formatQty($stockQty),
                'quantity_unit' => $unitName,
                'quantity_class' => $cancelled ? 'text-muted' : 'text-success',
                'by' => $receiving->user->name ?? 'N/A',
                'party' => $receiving->supplier->name ?? 'N/A',
                'party_label' => 'Supplier',
                'details' => 'Received ' . $this->formatQty($receivingItem->quantity) . ' ' . $receivingUnitName . '(s)'
                    . ($cancelled ? ' — receiving cancelled, stock reversed' : ''),
                'status' => $cancelled ? 'Cancelled' : 'Completed',
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $saleItems = SaleItem::where('item_id', $item->id)
            ->whereHas('sale', fn ($q) => $q->where('business_id', Auth::user()->business_id))
            ->with(['sale.user', 'itemPackaging.packagingType'])
            ->get();

        foreach ($saleItems as $saleItem) {
            $sale = $saleItem->sale;
            $cancelled = $sale->payment_status === 'cancelled';
            $packaging = $saleItem->itemPackaging;
            $soldUnitName = $packaging?->packagingType?->name ?? $unitName;
            $piecesSold = $item->stockUnitsForPackaging((int) $saleItem->quantity, $packaging);

            $movements->push([
                'sort_date' => $sale->sale_date . ' ' . ($sale->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $sale->sale_date,
                'time' => $sale->created_at?->format('h:i A') ?? '',
                'type' => 'sale',
                'type_label' => $cancelled ? 'Sale (Cancelled)' : 'Sale',
                'badge' => $cancelled ? 'secondary' : 'primary',
                'reference' => $sale->reference_no,
                'reference_url' => route('sales.show', $sale->id),
                'quantity' => $piecesSold,
                'quantity_label' => '-' . $this->formatQty($saleItem->quantity),
                'quantity_unit' => $soldUnitName,
                'quantity_class' => $cancelled ? 'text-muted' : 'text-danger',
                'by' => $sale->user->name ?? 'N/A',
                'party' => $sale->customer_name ?: 'Walk-in Customer',
                'party_label' => 'Customer',
                'details' => 'TZS ' . number_format($saleItem->unit_price, 2) . ' × '
                    . $this->formatQty($saleItem->quantity) . ' ' . $soldUnitName
                    . ($piecesSold !== (float) $saleItem->quantity
                        ? ' (' . $this->formatQty($piecesSold) . ' ' . $unitName . '(s))'
                        : '')
                    . ' = TZS ' . number_format($saleItem->subtotal, 2)
                    . ($cancelled ? ' — sale cancelled, stock restored' : ''),
                'status' => ucfirst(str_replace('_', ' ', $sale->payment_status)),
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $lossItems = StockLossItem::where('item_id', $item->id)
            ->whereHas('stockLoss', fn ($q) => $q->where('business_id', Auth::user()->business_id))
            ->with(['stockLoss.user'])
            ->get();

        foreach ($lossItems as $lossItem) {
            $loss = $lossItem->stockLoss;
            $cancelled = $loss->isCancelled();

            $movements->push([
                'sort_date' => $loss->loss_date->format('Y-m-d') . ' ' . ($loss->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $loss->loss_date->format('Y-m-d'),
                'time' => $loss->created_at?->format('h:i A') ?? '',
                'type' => 'stock_loss',
                'type_label' => $cancelled ? 'Stock Loss (Cancelled)' : 'Stock Loss',
                'badge' => $cancelled ? 'secondary' : 'warning',
                'reference' => $loss->reference_no,
                'reference_url' => route('stock-losses.show', $loss->id),
                'quantity' => $lossItem->quantity,
                'quantity_label' => '-' . $this->formatQty($lossItem->quantity),
                'quantity_unit' => $unitName,
                'quantity_class' => $cancelled ? 'text-muted' : 'text-warning',
                'by' => $loss->user->name ?? 'N/A',
                'party' => $loss->reasonLabel(),
                'party_label' => 'Reason',
                'details' => ($lossItem->line_notes ?: $loss->reasonLabel())
                    . ($cancelled ? ' — record cancelled, stock restored' : ''),
                'status' => $cancelled ? 'Cancelled' : 'Recorded',
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $movements = $movements->sortByDesc('sort_date')->values();

        $stats = [
            'total_received' => $movements->where('type', 'stock_in')->where('counts_toward_totals', true)->sum('quantity'),
            'total_sold' => $movements->where('type', 'sale')->where('counts_toward_totals', true)->sum('quantity'),
            'total_lost' => $movements->where('type', 'stock_loss')->where('counts_toward_totals', true)->sum('quantity'),
            'current_stock' => $item->current_stock,
        ];

        return view('items.history', compact('item', 'movements', 'stats', 'unitName'));
    }

    private function formatQty(float $qty): string
    {
        return fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : number_format($qty, 2);
    }

    public function create()
    {
        \Illuminate\Support\Facades\Gate::authorize('add_items');

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $formContext = $this->itemFormContext($business);

        return view('items.create', $formContext);
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('add_items');

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $maxItems = $business->plan->max_items ?? 0;
        
        if ($maxItems > 0) {
            $currentItemsCount = Item::where('business_id', $business->id)->count();
            if ($currentItemsCount >= $maxItems) {
                return redirect()->route('items.index')->with('error', "You have reached the maximum limit of {$maxItems} items for your current plan. Please upgrade to add more.");
            }
        }

        $typeKeys = $this->branchBusinessTypeKeys($business);

        $rules = [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'receiving_packaging_id' => 'required|exists:packagings,id',
            'units_per_receiving_pack' => 'required|integer|min:1',
            'selling_packagings' => 'required|array|min:1',
            'selling_packagings.*.packaging_id' => 'required|exists:packagings,id',
            'selling_packagings.*.quantity_per_unit' => 'required|integer|min:1',
        ];

        if (count($typeKeys) > 1) {
            $rules['business_type_key'] = 'required|string|in:'.implode(',', $typeKeys);
        }

        $request->validate($rules);

        if ($scopeError = $this->validateItemBusinessTypeScope($request, $business)) {
            return redirect()->back()->withInput()->with('error', $scopeError);
        }

        $sellingRows = $request->input('selling_packagings', []);
        $packagingTypes = Packaging::where('business_id', Auth::user()->business_id)->get();
        $normalizer = app(ItemPackagingNormalizer::class);
        $sellingRows = $normalizer->normalizeSellingRows(
            (int) $request->receiving_packaging_id,
            $sellingRows,
            $packagingTypes
        );
        $unitsPerReceiving = max(1, (int) $request->input('units_per_receiving_pack', 1));
        $sku = 'SP-' . strtoupper(bin2hex(random_bytes(4)));

        $item = Item::create([
            'business_id' => Auth::user()->business_id,
            'category_id' => $request->category_id,
            'receiving_packaging_id' => $request->receiving_packaging_id,
            'units_per_receiving_pack' => $unitsPerReceiving,
            'name' => $request->name,
            'sku' => $sku,
            'brand' => $request->brand,
            'description' => $request->description,
        ]);

        $this->syncSellingPackagings($item, $sellingRows, [
            'cost_price' => 0,
            'selling_price' => 0,
        ]);

        return redirect()->route('items.index')->with('success', 'Item registered successfully.');
    }

    public function show(Item $item)
    {
        \Illuminate\Support\Facades\Gate::authorize('view_inventory');
        if ($item->business_id != Auth::user()->business_id) abort(403);

        $item->load(['category', 'receivingPackaging', 'packagings.packagingType']);
        
        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        \Illuminate\Support\Facades\Gate::authorize('edit_items');
        if ($item->business_id != Auth::user()->business_id) abort(403);

        $item->load(['category', 'packagings']);
        $primaryPackaging = $item->packagings->first();
        $formContext = $this->itemFormContext($this->currentBusiness() ?? Auth::user()->business, $item);

        return view('items.edit', array_merge($formContext, compact('item', 'primaryPackaging')));
    }

    public function update(Request $request, Item $item)
    {
        \Illuminate\Support\Facades\Gate::authorize('edit_items');
        if ($item->business_id != Auth::user()->business_id) abort(403);

        $business = $this->currentBusiness() ?? Auth::user()->business;
        $typeKeys = $this->branchBusinessTypeKeys($business);

        $rules = [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'receiving_packaging_id' => 'required|exists:packagings,id',
            'units_per_receiving_pack' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'selling_packagings' => 'required|array|min:1',
            'selling_packagings.*.packaging_id' => 'required|exists:packagings,id',
            'selling_packagings.*.quantity_per_unit' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ];

        if (count($typeKeys) > 1) {
            $rules['business_type_key'] = 'required|string|in:'.implode(',', $typeKeys);
        }

        $request->validate($rules);

        if ($scopeError = $this->validateItemBusinessTypeScope($request, $business)) {
            return redirect()->back()->withInput()->with('error', $scopeError);
        }

        $sellingRows = $request->input('selling_packagings', []);
        $packagingTypes = Packaging::where('business_id', Auth::user()->business_id)->get();
        $normalizer = app(ItemPackagingNormalizer::class);
        $sellingRows = $normalizer->normalizeSellingRows(
            (int) $request->receiving_packaging_id,
            $sellingRows,
            $packagingTypes
        );
        $unitsPerReceiving = max(1, (int) $request->input('units_per_receiving_pack', 1));

        $oldUnitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
        $stockScale = ($unitsPerReceiving > $oldUnitsPerReceiving && $oldUnitsPerReceiving === 1)
            ? $unitsPerReceiving
            : 1;

        $item->update([
            'category_id' => $request->category_id,
            'receiving_packaging_id' => $request->receiving_packaging_id,
            'units_per_receiving_pack' => $unitsPerReceiving,
            'name' => $request->name,
            'brand' => $request->brand,
            'description' => $request->description,
            'current_stock' => (float) $item->current_stock * $stockScale,
        ]);

        $this->syncSellingPackagings($item, $sellingRows, [
            'cost_price' => $request->input('cost_price'),
            'selling_price' => $request->input('selling_price'),
        ]);

        return redirect()->route('items.show', $item->id)->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete_items');
        if ($item->business_id != Auth::user()->business_id) abort(403);

        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }

    private function syncSellingPackagings(Item $item, array $rows, array $firstPrices = []): void
    {
        $existing = $item->packagings()->get()->keyBy('packaging_id');
        $item->packagings()->delete();
        $isFirst = true;

        foreach ($rows as $row) {
            if (empty($row['packaging_id'])) {
                continue;
            }

            $previous = $existing->get((int) $row['packaging_id']);
            $cost = $isFirst && array_key_exists('cost_price', $firstPrices) && $firstPrices['cost_price'] !== null
                ? (float) $firstPrices['cost_price']
                : (float) ($previous?->cost_price ?? 0);
            $sell = $isFirst && array_key_exists('selling_price', $firstPrices) && $firstPrices['selling_price'] !== null
                ? (float) $firstPrices['selling_price']
                : (float) ($previous?->selling_price ?? 0);

            ItemPackaging::create([
                'item_id' => $item->id,
                'packaging_id' => $row['packaging_id'],
                'quantity_per_unit' => max(1, (int) ($row['quantity_per_unit'] ?? 1)),
                'cost_price' => $cost,
                'selling_price' => $sell,
            ]);

            $isFirst = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function itemFormContext($business, ?Item $item = null): array
    {
        $branchFilterId = $this->itemFormBranchFilterId();
        $templates = config('category_templates', []);

        $categoriesQuery = Category::where('business_id', $business->id)->orderBy('name');
        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }
        $categories = $categoriesQuery->get();

        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        $multiBusiness = count($businessTypes) > 1;
        $branchTypeKeys = collect($businessTypes)->pluck('key')->filter()->values()->all();

        $packagingQuery = Packaging::where('business_id', $business->id)->orderBy('name');
        if ($branchFilterId && ! empty($branchTypeKeys)) {
            $packagingQuery->where(function ($query) use ($branchTypeKeys) {
                $query->whereIn('source_business_type_key', $branchTypeKeys)
                    ->orWhereNull('source_business_type_key')
                    ->orWhere('source_business_type_key', 'other')
                    ->orWhere('source_business_type_key', '');
            });
        }
        $packagingTypes = $packagingQuery->get();

        $defaultBusinessTypeKey = old('business_type_key');

        if (! $defaultBusinessTypeKey && $item?->category?->source_business_type_key) {
            $defaultBusinessTypeKey = $item->category->source_business_type_key;
        }

        if (! $defaultBusinessTypeKey && count($businessTypes) === 1) {
            $defaultBusinessTypeKey = $businessTypes[0]['key'];
        }

        $categoriesPayload = $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'business_type_key' => $category->source_business_type_key ?: 'other',
        ])->values()->all();

        $packagingsPayload = $packagingTypes->map(fn (Packaging $packaging) => [
            'id' => $packaging->id,
            'name' => $packaging->name,
            'business_type_key' => $packaging->source_business_type_key ?: 'other',
        ])->values()->all();

        return compact(
            'categories',
            'packagingTypes',
            'businessTypes',
            'multiBusiness',
            'defaultBusinessTypeKey',
            'categoriesPayload',
            'packagingsPayload',
            'branchFilterId'
        );
    }

    private function itemFormBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer()) {
            $branchId = auth()->user()?->branch_id;

            return $branchId ? (int) $branchId : null;
        }

        return active_branch_id();
    }

    /**
     * @return list<string>
     */
    private function branchBusinessTypeKeys($business): array
    {
        $branchFilterId = $this->itemFormBranchFilterId();

        if ($branchFilterId) {
            return collect($business->importedTypesForBranch($branchFilterId))
                ->pluck('key')
                ->filter()
                ->values()
                ->all();
        }

        return collect($business->posBusinessTypesMeta())->pluck('key')->filter()->values()->all();
    }

    private function validateItemBusinessTypeScope(Request $request, $business): ?string
    {
        $businessTypes = $this->branchBusinessTypeKeys($business);

        if (count($businessTypes) <= 1) {
            return null;
        }

        $typeKey = (string) $request->input('business_type_key');

        if ($typeKey === '') {
            return 'Please select which business type this item belongs to.';
        }

        if (! in_array($typeKey, $businessTypes, true)) {
            return 'The selected business type is not available for the active branch.';
        }

        if ($request->filled('category_id')) {
            $categoryQuery = Category::where('business_id', $business->id)
                ->where('id', $request->category_id);

            if ($branchFilterId = $this->itemFormBranchFilterId()) {
                $categoryQuery->where('branch_id', $branchFilterId);
            }

            $category = $categoryQuery->first();
            $categoryType = $category?->source_business_type_key ?: 'other';

            if (! $category || $categoryType !== $typeKey) {
                return 'The selected category does not belong to the chosen business type.';
            }
        }

        $packagingIds = collect($request->input('selling_packagings', []))
            ->pluck('packaging_id')
            ->filter()
            ->push($request->receiving_packaging_id)
            ->unique()
            ->all();

        $invalidPackaging = Packaging::where('business_id', $business->id)
            ->whereIn('id', $packagingIds)
            ->get()
            ->first(function (Packaging $packaging) use ($typeKey) {
                $packagingType = $packaging->source_business_type_key ?: 'other';

                return $packagingType !== $typeKey;
            });

        if ($invalidPackaging) {
            return 'One or more selected units do not belong to the chosen business type.';
        }

        return null;
    }
}
