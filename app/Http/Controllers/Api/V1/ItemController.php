<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPackaging;
use App\Models\Packaging;
use App\Services\ItemPackagingNormalizer;
use App\Services\ItemStockApiService;
use App\Services\ItemStockDisplayService;
use App\Services\StockForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemController extends ApiController
{
    public function __construct(private ItemStockApiService $stockService)
    {
    }

    public function stock(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_stock_history', 'view_inventory'])) {
            return $deny;
        }

        $data = $this->stockService->listStock(
            $request->user(),
            $this->apiBusinessId(),
            $this->itemFormBranchFilterId($request->user()),
            $request
        );

        return $this->success($data);
    }

    public function forecast(Request $request, StockForecastService $forecast): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_stock_history', 'view_inventory', 'receive_stock'])) {
            return $deny;
        }

        return $this->success($forecast->forecast(
            $this->apiBusinessId(),
            $this->itemFormBranchFilterId($request->user())
        ));
    }

    public function history(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_stock_history', 'view_inventory'])) {
            return $deny;
        }

        if ($item->business_id != $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $item->loadMissing('category');

        if ($deny = $this->ensureCanAccessItem($request->user(), $item)) {
            return $deny;
        }

        return $this->success($this->stockService->itemHistory($item, $this->apiBusinessId()));
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_inventory'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $branchFilterId = $this->itemFormBranchFilterId($request->user());
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

        $query = Item::query()
            ->where('business_id', $business->id)
            ->with(['category', 'packagings.packagingType', 'packagings.unitBarcodes', 'receivingPackaging']);

        if ($branchFilterId) {
            $query->whereHas('category', fn ($q) => $q->where('branch_id', $branchFilterId));
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        if ($request->filled('business_type_key')) {
            $key = (string) $request->business_type_key;
            $query->whereHas('category', fn ($q) => $q->where('source_business_type_key', $key));
        }

        $items = $query->orderBy('name')->get();
        $stockDisplay = app(ItemStockDisplayService::class);

        $categoryFilters = $items
            ->filter(fn (Item $item) => $item->category)
            ->unique(fn (Item $item) => Str::slug($item->category->name).'|'.($item->category->source_business_type_key ?: 'other'))
            ->map(fn (Item $item) => [
                'name' => $item->category->name,
                'slug' => Str::slug($item->category->name),
                'business_type_key' => $item->category->source_business_type_key ?: 'other',
            ])
            ->sortBy('name')
            ->values();

        return $this->success([
            'items' => $items->map(fn (Item $item) => $this->itemInventoryPayload($item, $stockDisplay))->values(),
            'meta' => [
                'branch_filter_id' => $branchFilterId,
                'active_branch_name' => $branchFilterId
                    ? ($this->tenantContext()->branch()?->name ?? Branch::find($branchFilterId)?->name)
                    : null,
                'viewing_all_branches' => $request->user()->seesBusinessWideData() && ! $branchFilterId,
                'business_types' => $businessTypes,
                'multi_business' => count($businessTypes) > 1,
                'category_filters' => $categoryFilters,
                'has_uncategorized_items' => $items->contains(fn (Item $item) => ! $item->category_id),
                'items_count' => $items->count(),
                'max_items' => $business->plan?->max_items,
            ],
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['add_items'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $context = $this->itemFormContext($business, $request->user());

        $maxItems = (int) ($business->plan?->max_items ?? 0);
        $currentCount = Item::query()->where('business_id', $business->id)->count();

        return $this->success([
            'business_types' => $context['business_types'],
            'multi_business' => $context['multi_business'],
            'default_business_type_key' => $context['default_business_type_key'],
            'categories' => $context['categories'],
            'packagings' => $context['packagings'],
            'branch_filter_id' => $context['branch_filter_id'],
            'plan' => [
                'max_items' => $maxItems > 0 ? $maxItems : null,
                'current_items' => $currentCount,
                'can_add' => $maxItems <= 0 || $currentCount < $maxItems,
            ],
            'fields' => [
                'name' => ['required' => true],
                'category_id' => ['required' => false],
                'brand' => ['required' => false],
                'description' => ['required' => false],
                'business_type_key' => ['required' => $context['multi_business']],
                'receiving_packaging_id' => ['required' => true],
                'units_per_receiving_pack' => ['required' => true, 'min' => 1],
                'selling_packagings' => ['required' => true, 'min' => 1],
            ],
        ]);
    }

    public function checkName(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['add_items', 'edit_items'])) {
            return $deny;
        }

        $name = trim((string) $request->query('name', ''));
        $excludeId = $request->query('exclude_id');

        if (mb_strlen($name) < 2) {
            return $this->success([
                'exact' => false,
                'exact_item' => null,
                'matches' => [],
            ]);
        }

        $businessId = $this->apiBusinessId();
        $normalized = mb_strtolower($name);

        $baseQuery = Item::query()
            ->where('business_id', $businessId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', (int) $excludeId));

        if ($branchFilterId = $this->itemFormBranchFilterId($request->user())) {
            $baseQuery->where(function ($q) use ($branchFilterId) {
                $q->whereHas('category', fn ($c) => $c->where('branch_id', $branchFilterId))
                    ->orWhereNull('category_id');
            });
        }

        $exact = (clone $baseQuery)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->with('category:id,name')
            ->first();

        $matches = (clone $baseQuery)
            ->where('name', 'like', '%'.$name.'%')
            ->with('category:id,name')
            ->orderByRaw('CASE WHEN LOWER(TRIM(name)) = ? THEN 0 ELSE 1 END', [$normalized])
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'category' => $item->category?->name,
                'sku' => $item->sku,
                'exact' => mb_strtolower(trim($item->name)) === $normalized,
            ])
            ->values()
            ->all();

        return $this->success([
            'exact' => (bool) $exact,
            'exact_item' => $exact ? [
                'id' => $exact->id,
                'name' => $exact->name,
            ] : null,
            'matches' => $matches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['add_items'])) {
            return $deny;
        }

        try {
            $business = $this->requireBusiness();
            $maxItems = (int) ($business->plan?->max_items ?? 0);

            if ($maxItems > 0) {
                $currentItemsCount = Item::query()->where('business_id', $business->id)->count();
                if ($currentItemsCount >= $maxItems) {
                    return $this->error(
                        "You have reached the maximum limit of {$maxItems} items for your current plan. Please upgrade to add more.",
                        422
                    );
                }
            }

            $typeKeys = $this->branchBusinessTypeKeys($business, $request->user());

            $rules = [
                'name' => 'required|string|max:255',
                'category_id' => 'nullable|exists:categories,id',
                'brand' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'receiving_packaging_id' => 'required|exists:packagings,id',
                'units_per_receiving_pack' => 'required|integer|min:1',
                'selling_packagings' => 'required|array|min:1',
                'selling_packagings.*.packaging_id' => 'required|exists:packagings,id',
                'selling_packagings.*.quantity_per_unit' => 'required|integer|min:1',
                'selling_packagings.*.selling_price' => 'nullable|numeric|min:0',
                'selling_packagings.*.barcode' => 'nullable|string|max:128',
                'selling_packagings.*.manufacturer_barcode' => 'nullable|string|max:128',
                'selling_packagings.*.barcode_type' => 'nullable|string|max:32',
                'cost_price' => 'nullable|numeric|min:0',
            ];

            if (count($typeKeys) > 1) {
                $rules['business_type_key'] = 'required|string|in:'.implode(',', $typeKeys);
            }

            $request->validate($rules);

            if ($duplicateError = $this->duplicateItemNameError($request->name, (int) $business->id, $request->user())) {
                return $this->error($duplicateError, 422, ['name' => [$duplicateError]]);
            }

            if ($scopeError = $this->validateItemBusinessTypeScope($request, $business, $request->user())) {
                return $this->error($scopeError, 422);
            }

            $sellingRows = $this->mergeManufacturerBarcodes($request->input('selling_packagings', []));
            $packagingTypes = Packaging::query()->where('business_id', $business->id)->get();
            $normalizer = app(ItemPackagingNormalizer::class);
            $sellingRows = $normalizer->normalizeSellingRows(
                (int) $request->receiving_packaging_id,
                $sellingRows,
                $packagingTypes
            );
            $unitsPerReceiving = max(1, (int) $request->input('units_per_receiving_pack', 1));
            $sku = 'SP-'.strtoupper(bin2hex(random_bytes(4)));

            $item = Item::create([
                'business_id' => $business->id,
                'category_id' => $request->category_id,
                'receiving_packaging_id' => $request->receiving_packaging_id,
                'units_per_receiving_pack' => $unitsPerReceiving,
                'name' => $request->name,
                'sku' => $sku,
                'brand' => $request->brand,
                'description' => $request->description,
            ]);

            $this->syncSellingPackagings($item, $sellingRows, [
                'cost_price' => $request->input('cost_price', 0),
                'selling_price' => 0,
            ]);

            $item->load(['category', 'packagings.packagingType', 'receivingPackaging']);

            return $this->success([
                'item' => $this->itemDetailPayload($item),
                'barcodes_url' => url('/api/v1/items/'.$item->id.'/barcodes'),
                'web_print_url' => url('/items/'.$item->id.'/barcodes/print'),
            ], 'Item registered successfully. Barcodes generated ??? print labels or fetch /items/{id}/barcodes.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function search(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['process_sales', 'view_inventory'])) {
            return $deny;
        }

        $request->validate([
            'q' => 'nullable|string|max:120',
            'limit' => 'nullable|integer|min:1|max:500',
            'business_type_key' => 'nullable|string|max:120',
        ]);

        $businessId = $this->apiBusinessId();
        $q = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 30);

        $query = Item::query()
            ->where('business_id', $businessId)
            ->where('current_stock', '>', 0)
            ->whereNotNull('category_id')
            ->with(['category', 'packagings.packagingType', 'packagings.unitBarcodes', 'receivingPackaging']);

        $this->scopeItemsForPos($query, $request->user());

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhereHas('packagings', fn ($p) => $p->where('barcode', $q)->orWhere('manufacturer_barcode', $q))
                    ->orWhereHas('packagings.unitBarcodes', fn ($p) => $p->where('barcode', $q));
            });
        }

        if ($request->filled('business_type_key')) {
            $key = (string) $request->business_type_key;
            $query->whereHas('category', fn ($c) => $c->where('source_business_type_key', $key));
        }

        $stockDisplay = app(ItemStockDisplayService::class);
        $normalizer = app(ItemPackagingNormalizer::class);

        $items = $query->orderBy('name')->limit($limit)->get()->map(function (Item $item) use ($stockDisplay, $normalizer) {
            $info = $stockDisplay->format($item);
            $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
            $normalized = $normalizer->normalizeItemPackagings($item, $packagingModels);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'brand' => $item->brand,
                'category' => $item->category?->name,
                'business_type_key' => $item->category?->source_business_type_key ?: 'other',
                'current_stock' => (float) $item->current_stock,
                'stock_display' => $info['stock_display'] ?? (string) $item->current_stock,
                'unit' => $info['unit_name'] ?? 'Unit',
                'packagings' => $normalized->map(function ($row) {
                    $pkg = $row['packaging'];

                    return [
                        'id' => $pkg->id,
                        'item_packaging_id' => $pkg->id,
                        'packaging_id' => $pkg->packaging_id,
                        'name' => $pkg->packagingType?->name ?? 'Unit',
                        'quantity_per_unit' => (int) $row['quantity_per_unit'],
                        'selling_price' => (float) $pkg->selling_price,
                        'cost_price' => (float) $pkg->cost_price,
                        'barcode' => $pkg->barcode ?? '',
                    ];
                })->values(),
            ];
        })->values();

        $businessTypes = $this->posBusinessTypesFor($request->user());

        return $this->success([
            'items' => $items,
            'business_types' => $businessTypes,
            'multi_business' => count($businessTypes) > 1,
        ]);
    }

    /**
     * Business-type tabs for the POS, limited to the branch and the user's assigned types.
     *
     * @return list<array{key: string, label: string, icon: string}>
     */
    private function posBusinessTypesFor($user): array
    {
        $business = $this->requireBusiness();
        $branchId = (! $user->seesBusinessWideData() && $user->branch_id)
            ? (int) $user->branch_id
            : $this->tenantContext()->branchId();

        $types = $branchId
            ? $business->branchPosBusinessTypesMeta((int) $branchId)
            : $business->posBusinessTypesMeta();

        if (! $user->seesBusinessWideData() && ($keys = $user->assignedBusinessTypeKeys()) !== []) {
            $types = array_filter($types, fn ($t) => in_array($t['key'], $keys, true));
        }

        return array_values($types);
    }

    /**
     * Fast POS scan: resolve a selling packaging by barcode.
     */
    public function lookupBarcode(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['process_sales', 'view_inventory'])) {
            return $deny;
        }

        $data = $request->validate([
            'code' => 'required_without:barcode|nullable|string|max:128',
            'barcode' => 'required_without:code|nullable|string|max:128',
            'branch_id' => 'nullable|integer|min:1',
            'context' => 'nullable|string|in:sell,receiving',
        ]);

        $scanCode = trim((string) ($data['code'] ?? ''));
        if ($scanCode === '' && ! empty($data['barcode'])) {
            $scanCode = trim((string) $data['barcode']);
        }

        $businessId = $this->apiBusinessId();
        if ($scanCode === '') {
            return $this->error('Barcode code is required.', 422);
        }

        $lookup = app(\App\Services\BarcodeLookupService::class)->resolve(
            $scanCode,
            $businessId,
            fn ($q) => $this->scopeItemsForPos($q, $request->user())
        );

        if ($lookup['candidates']->count() > 1) {
            return $this->error('Multiple items match this code.', 409, [
                'code' => 'BARCODE_AMBIGUOUS',
                'candidates' => $lookup['candidates']->map(fn ($pkg) => [
                    'item_id' => $pkg->item_id,
                    'item_name' => $pkg->item?->name,
                    'item_packaging_id' => $pkg->id,
                    'packaging_name' => $pkg->packagingType?->name,
                    'barcode' => $pkg->barcode,
                ])->values()->all(),
            ]);
        }

        $packaging = $lookup['packaging'];
        if (! $packaging) {
            return $this->error('No item for this code.', 404, ['code' => 'BARCODE_NOT_FOUND']);
        }

        $item = $packaging->item;
        $stockDisplay = app(ItemStockDisplayService::class);
        $info = $stockDisplay->format($item);

        $resolvedBarcode = $lookup['scanned_barcode'] ?? $packaging->barcode;

        return $this->success([
            'barcode' => $resolvedBarcode,
            'source' => $lookup['source'] ?? 'internal',
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'brand' => $item->brand,
                'category' => $item->category?->name,
                'current_stock' => (float) $item->current_stock,
                'stock_display' => $info['stock_display'] ?? (string) $item->current_stock,
                'unit' => $info['unit_name'] ?? 'Unit',
                'in_stock' => (float) $item->current_stock > 0,
            ],
            'packaging' => [
                'id' => $packaging->id,
                'packaging_id' => $packaging->packaging_id,
                'name' => $packaging->packagingType?->name ?? 'Unit',
                'quantity_per_unit' => (int) $packaging->quantity_per_unit,
                'selling_price' => (float) $packaging->selling_price,
                'cost_price' => (float) $packaging->cost_price,
                'barcode' => $packaging->barcode,
                'barcode_type' => strlen(preg_replace('/\D+/', '', (string) $packaging->barcode) ?? '') >= 8 ? 'ean13' : 'internal',
            ],
            'cart_line' => [
                'item_id' => $item->id,
                'item_packaging_id' => $packaging->id,
                'quantity' => 1,
                'unit_price' => (float) $packaging->selling_price,
            ],
        ]);
    }

    public function linkBarcode(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['edit_items', 'add_items', 'view_inventory'])) {
            return $deny;
        }

        return $this->performLinkBarcode($request, null);
    }

    public function linkBarcodeForItem(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['edit_items', 'add_items', 'view_inventory'])) {
            return $deny;
        }

        if ((int) $item->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        return $this->performLinkBarcode($request, $item);
    }

    private function performLinkBarcode(Request $request, ?Item $item): JsonResponse
    {
        $request->validate([
            'item_id' => 'nullable|integer|min:1',
            'item_packaging_id' => 'nullable|integer|min:1',
            'sell_package_id' => 'nullable|integer|min:1',
            'sell_packaging_id' => 'nullable|integer|min:1',
            'packaging_id' => 'nullable|integer|min:1',
            'barcode' => 'nullable|string|max:128',
            'code' => 'nullable|string|max:128',
            'manufacturer_barcode' => 'nullable|string|max:128',
            'quantity_per_unit' => 'nullable|integer|min:1',
            'link_type' => 'nullable|string|in:primary,piece,case,pack',
            'unit_barcode' => 'nullable|boolean',
        ]);

        try {
            $result = app(\App\Services\ItemBarcodeLinkService::class)->link(
                $this->apiBusinessId(),
                $request->all(),
                $item?->id,
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }

        $packaging = $result['packaging'];
        $unitCount = $packaging->unitBarcodes->count();

        return $this->success([
            'item_id' => $packaging->item_id,
            'item_packaging_id' => $packaging->id,
            'unit_barcode_count' => $unitCount,
            'link_mode' => $result['mode'],
            'barcode' => $result['barcode'],
            'store_barcode' => (string) ($packaging->barcode ?? ''),
            'manufacturer_barcode' => filled($packaging->manufacturer_barcode) ? (string) $packaging->manufacturer_barcode : null,
            'barcode_linked' => $packaging->hasLinkedBarcode(),
            'has_linked_barcode' => $packaging->hasLinkedBarcode(),
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'packaging' => $this->packagingBarcodesPayload($packaging),
        ], 'Linked');
    }

    public function barcodes(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_inventory', 'add_items', 'edit_items', 'process_sales'])) {
            return $deny;
        }

        if ((int) $item->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $item->load(['packagings.packagingType', 'packagings.unitBarcodes']);
        $barcodeService = app(\App\Services\ItemBarcodeService::class);

        foreach ($item->packagings as $packaging) {
            $barcodeService->ensureBarcode($packaging, (int) $item->business_id);
        }

        $item->refresh()->load(['packagings.packagingType', 'packagings.unitBarcodes']);

        $sorted = $item->packagings->sortBy('quantity_per_unit')->values();

        return $this->success([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
            ],
            'packagings' => $sorted->map(fn ($pkg) => $this->packagingBarcodesPayload($pkg))->values(),
            'labels' => $sorted->map(function ($pkg) use ($barcodeService) {
                $code = (string) $pkg->barcode;

                return array_merge($this->packagingBarcodesPayload($pkg), [
                    'packaging_name' => $pkg->packagingType?->name ?? 'Unit',
                    'barcode_png_base64' => $code !== '' ? $barcodeService->pngBase64($code) : null,
                    'print_hint' => 'Use barcode_png_base64 as data:image/png;base64,... for label preview/print',
                ]);
            }),
            'has_linked_barcode' => $sorted->contains(fn ($pkg) => $pkg->hasLinkedBarcode()),
            'web_print_url' => url('/items/'.$item->id.'/barcodes/print'),
        ]);
    }

    public function printBarcodesData(Request $request, Item $item): JsonResponse
    {
        return $this->barcodes($request, $item);
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['process_sales', 'view_inventory', 'edit_items'])) {
            return $deny;
        }

        if ((int) $item->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $item->load(['category', 'packagings.packagingType', 'packagings.unitBarcodes', 'receivingPackaging']);

        return $this->success([
            'item' => $this->itemDetailPayload($item),
        ]);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['edit_items'])) {
            return $deny;
        }

        if ((int) $item->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        try {
            $business = $this->requireBusiness();
            $typeKeys = $this->branchBusinessTypeKeys($business, $request->user());

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
                'selling_packagings.*.selling_price' => 'nullable|numeric|min:0',
                'selling_packagings.*.barcode' => 'nullable|string|max:128',
                'selling_packagings.*.manufacturer_barcode' => 'nullable|string|max:128',
                'selling_packagings.*.barcode_type' => 'nullable|string|max:32',
                'cost_price' => 'nullable|numeric|min:0',
            ];

            if (count($typeKeys) > 1) {
                $rules['business_type_key'] = 'required|string|in:'.implode(',', $typeKeys);
            }

            $request->validate($rules);

            if ($duplicateError = $this->duplicateItemNameError($request->name, (int) $business->id, $request->user(), $item->id)) {
                return $this->error($duplicateError, 422, ['name' => [$duplicateError]]);
            }

            if ($scopeError = $this->validateItemBusinessTypeScope($request, $business, $request->user())) {
                return $this->error($scopeError, 422);
            }

            $sellingRows = $this->mergeManufacturerBarcodes($request->input('selling_packagings', []));
            $packagingTypes = Packaging::query()->where('business_id', $business->id)->get();
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
            ]);

            $item->load(['category', 'packagings.packagingType', 'receivingPackaging']);

            return $this->success([
                'item' => $this->itemDetailPayload($item),
            ], 'Item updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['delete_items'])) {
            return $deny;
        }

        if ((int) $item->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $item->delete();

        return $this->success(null, 'Item deleted successfully.');
    }

    private function requireBusiness(): Business
    {
        $business = $this->apiBusiness();
        if (! $business) {
            abort(404, 'Business not found.');
        }

        return $business->loadMissing('plan');
    }

    private function itemInventoryPayload(Item $item, ItemStockDisplayService $stockDisplay): array
    {
        $info = $stockDisplay->format($item);
        $item->loadMissing(['packagings.unitBarcodes']);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'brand' => $item->brand,
            'category_id' => $item->category_id,
            'category' => $item->category?->name,
            'business_type_key' => $item->category?->source_business_type_key,
            'current_stock' => (float) $item->current_stock,
            'stock_display' => $info['stock_display'] ?? (string) $item->current_stock,
            'unit' => $info['unit_name'] ?? 'Unit',
            'has_linked_barcode' => $item->packagings->contains(fn ($pkg) => $pkg->hasLinkedBarcode()),
            'barcode_linked' => $item->packagings->contains(fn ($pkg) => $pkg->hasLinkedBarcode()),
        ];
    }

    private function itemDetailPayload(Item $item): array
    {
        $stockDisplay = app(ItemStockDisplayService::class);
        $info = $stockDisplay->format($item);
        $normalizer = app(ItemPackagingNormalizer::class);
        $normalized = $normalizer->normalizeItemPackagings(
            $item,
            $item->packagings->sortBy('quantity_per_unit')->values()
        );

        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'brand' => $item->brand,
            'description' => $item->description,
            'category_id' => $item->category_id,
            'category' => $item->category ? [
                'id' => $item->category->id,
                'name' => $item->category->name,
                'business_type_key' => $item->category->source_business_type_key,
            ] : null,
            'receiving_packaging_id' => $item->receiving_packaging_id,
            'receiving_packaging' => $item->receivingPackaging?->name,
            'units_per_receiving_pack' => (int) ($item->units_per_receiving_pack ?? 1),
            'current_stock' => (float) $item->current_stock,
            'stock_display' => $info['stock_display'] ?? (string) $item->current_stock,
            'unit' => $info['unit_name'] ?? 'Unit',
            'has_linked_barcode' => $item->packagings->contains(fn ($pkg) => $pkg->hasLinkedBarcode()),
            'barcode_linked' => $item->packagings->contains(fn ($pkg) => $pkg->hasLinkedBarcode()),
            'packagings' => $normalized->map(function ($row) {
                $pkg = $row['packaging'];

                return array_merge($this->packagingBarcodesPayload($pkg), [
                    'id' => $pkg->id,
                    'cost_price' => (float) $pkg->cost_price,
                ]);
            })->values(),
            'selling_packagings' => $normalized->map(function ($row) {
                $pkg = $row['packaging'];

                return array_merge($this->packagingBarcodesPayload($pkg), [
                    'id' => $pkg->id,
                    'cost_price' => (float) $pkg->cost_price,
                ]);
            })->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packagingBarcodesPayload(ItemPackaging $pkg): array
    {
        $unitRows = $pkg->relationLoaded('unitBarcodes')
            ? $pkg->unitBarcodes
            : collect();

        $unitBarcodes = $unitRows
            ->map(fn ($row) => ['barcode' => (string) $row->barcode])
            ->values()
            ->all();

        $store = (string) ($pkg->barcode ?? '');
        $manufacturer = filled($pkg->manufacturer_barcode) ? (string) $pkg->manufacturer_barcode : null;
        $linked = $manufacturer !== null || count($unitBarcodes) > 0;

        return [
            'item_packaging_id' => $pkg->id,
            'packaging_id' => $pkg->packaging_id,
            'name' => $pkg->packagingType?->name ?? 'Unit',
            'quantity_per_unit' => (int) $pkg->quantity_per_unit,
            'selling_price' => (float) $pkg->selling_price,
            'barcode' => $store,
            'store_barcode' => $store,
            'manufacturer_barcode' => $manufacturer,
            'barcode_linked' => $linked,
            'has_linked_barcode' => $linked,
            'unit_barcode_count' => count($unitBarcodes),
            'unit_barcodes' => $unitBarcodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemFormContext(Business $business, $user, ?Item $item = null): array
    {
        $branchFilterId = $this->itemFormBranchFilterId($user);
        $templates = config('category_templates', []);

        $categoriesQuery = Category::query()->where('business_id', $business->id)->orderBy('name');
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

        $packagingQuery = Packaging::query()->where('business_id', $business->id)->orderBy('name');
        if ($branchFilterId && $branchTypeKeys !== []) {
            $packagingQuery->where(function ($query) use ($branchTypeKeys) {
                $query->whereIn('source_business_type_key', $branchTypeKeys)
                    ->orWhereNull('source_business_type_key')
                    ->orWhere('source_business_type_key', 'other')
                    ->orWhere('source_business_type_key', '');
            });
        }
        $packagingTypes = $packagingQuery->get();

        $defaultBusinessTypeKey = null;
        if ($item?->category?->source_business_type_key) {
            $defaultBusinessTypeKey = $item->category->source_business_type_key;
        } elseif (count($businessTypes) === 1) {
            $defaultBusinessTypeKey = $businessTypes[0]['key'];
        }

        return [
            'business_types' => $businessTypes,
            'multi_business' => $multiBusiness,
            'default_business_type_key' => $defaultBusinessTypeKey,
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'business_type_key' => $category->source_business_type_key ?: 'other',
            ])->values()->all(),
            'packagings' => $packagingTypes->map(fn (Packaging $packaging) => [
                'id' => $packaging->id,
                'name' => $packaging->name,
                'business_type_key' => $packaging->source_business_type_key ?: 'other',
            ])->values()->all(),
            'branch_filter_id' => $branchFilterId,
        ];
    }

    private function itemFormBranchFilterId($user): ?int
    {
        if (! $user->seesBusinessWideData()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        return $this->tenantContext()->branchId();
    }

    private function ensureCanAccessItem($user, Item $item): ?JsonResponse
    {
        if (! $user->seesBusinessWideData() && $user->branch_id && $item->category_id) {
            if ((int) $item->category?->branch_id !== (int) $user->branch_id) {
                return $this->forbidden('You do not have access to this item.');
            }
        }

        $branchFilterId = $this->itemFormBranchFilterId($user);
        if ($user->seesBusinessWideData() && $branchFilterId && $item->category_id) {
            if ((int) $item->category?->branch_id !== $branchFilterId) {
                return $this->forbidden('Switch to the correct branch to view this item.');
            }
        }

        return null;
    }

    private function duplicateItemNameError(string $name, int $businessId, $user, ?int $excludeId = null): ?string
    {
        $normalized = mb_strtolower(trim($name));

        $query = Item::query()
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        if ($branchFilterId = $this->itemFormBranchFilterId($user)) {
            $query->where(function ($q) use ($branchFilterId) {
                $q->whereHas('category', fn ($c) => $c->where('branch_id', $branchFilterId))
                    ->orWhereNull('category_id');
            });
        }

        if (! $query->exists()) {
            return null;
        }

        return 'An item with this name already exists. Use a different name or open the existing item.';
    }

    /**
     * @return list<string>
     */
    private function branchBusinessTypeKeys(Business $business, $user): array
    {
        $branchFilterId = $this->itemFormBranchFilterId($user);

        if ($branchFilterId) {
            return collect($business->importedTypesForBranch($branchFilterId))
                ->pluck('key')
                ->filter()
                ->values()
                ->all();
        }

        return collect($business->posBusinessTypesMeta())->pluck('key')->filter()->values()->all();
    }

    private function validateItemBusinessTypeScope(Request $request, Business $business, $user): ?string
    {
        $businessTypes = $this->branchBusinessTypeKeys($business, $user);

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
            $categoryQuery = Category::query()
                ->where('business_id', $business->id)
                ->where('id', $request->category_id);

            if ($branchFilterId = $this->itemFormBranchFilterId($user)) {
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

        $invalidPackaging = Packaging::query()
            ->where('business_id', $business->id)
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

    private function syncSellingPackagings(Item $item, array $rows, array $firstPrices = []): void
    {
        $existing = $item->packagings()->with('unitBarcodes')->get()->keyBy('packaging_id');
        $savedUnitBarcodes = [];
        foreach ($existing as $packagingId => $previous) {
            $savedUnitBarcodes[(int) $packagingId] = $previous->unitBarcodes
                ->map(fn ($row) => (string) $row->barcode)
                ->filter()
                ->values()
                ->all();
        }
        $item->packagings()->delete();
        $isFirst = true;
        $created = [];

        foreach ($rows as $row) {
            if (empty($row['packaging_id'])) {
                continue;
            }

            $previous = $existing->get((int) $row['packaging_id']);
            $cost = $isFirst && array_key_exists('cost_price', $firstPrices) && $firstPrices['cost_price'] !== null
                ? (float) $firstPrices['cost_price']
                : (float) ($previous?->cost_price ?? 0);

            if (array_key_exists('selling_price', $row) && $row['selling_price'] !== null && $row['selling_price'] !== '') {
                $sell = (float) $row['selling_price'];
            } elseif ($isFirst && array_key_exists('selling_price', $firstPrices) && $firstPrices['selling_price'] !== null) {
                $sell = (float) $firstPrices['selling_price'];
            } else {
                $sell = (float) ($previous?->selling_price ?? 0);
            }

            $barcode = filled($row['barcode'] ?? null)
                ? trim((string) $row['barcode'])
                : ($previous?->barcode);

            $manufacturer = filled($row['manufacturer_barcode'] ?? null)
                ? trim((string) $row['manufacturer_barcode'])
                : ($previous?->manufacturer_barcode);

            $created[] = ItemPackaging::create([
                'item_id' => $item->id,
                'packaging_id' => $row['packaging_id'],
                'quantity_per_unit' => max(1, (int) ($row['quantity_per_unit'] ?? 1)),
                'cost_price' => $cost,
                'selling_price' => $sell,
                'barcode' => $barcode,
                'manufacturer_barcode' => $manufacturer,
            ]);

            $isFirst = false;
        }

        app(\App\Services\ItemBarcodeService::class)->assignMissingBarcodes(
            (int) $item->business_id,
            $created,
            $existing
        );

        foreach ($created as $packaging) {
            $codes = $savedUnitBarcodes[(int) $packaging->packaging_id] ?? [];
            foreach ($codes as $code) {
                \App\Models\ItemPackagingUnitBarcode::query()->firstOrCreate(
                    [
                        'business_id' => (int) $item->business_id,
                        'barcode' => $code,
                    ],
                    [
                        'item_id' => $item->id,
                        'item_packaging_id' => $packaging->id,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mergeManufacturerBarcodes(array $rows): array
    {
        // Keep manufacturer_barcode separate from durable store/label barcode.
        return collect($rows)->map(function ($row) {
            if (! empty($row['manufacturer_barcode'])) {
                $row['manufacturer_barcode'] = trim((string) $row['manufacturer_barcode']);
            }

            return $row;
        })->all();
    }

    private function scopeItemsForPos($query, $user): void
    {
        $ctx = $this->tenantContext();

        if (! $user->seesBusinessWideData() && $user->branch_id) {
            $query->whereHas('category', fn ($q) => $q->where('branch_id', (int) $user->branch_id));
        } elseif ($ctx->branchId()) {
            $query->whereHas('category', fn ($q) => $q->where('branch_id', $ctx->branchId()));
        }

        if (! $user->seesBusinessWideData()) {
            $keys = $user->assignedBusinessTypeKeys();
            if ($keys !== []) {
                $query->whereHas('category', fn ($q) => $q->whereIn('source_business_type_key', $keys));
            }
        }
    }
}
