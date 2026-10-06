<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\Category;
use App\Models\Item;
use App\Services\ItemPackagingNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Same as web /price-list: selling price per sale unit, grouped by category.
 */
class PriceListController extends ApiController
{
    use UsesWebBranchContext;

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['view_price_list', 'view_inventory', 'process_sales'])) {
            return $deny;
        }

        $request->validate([
            'category_id' => 'nullable|integer',
            'q' => 'nullable|string|max:120',
            'business_type_key' => 'nullable|string|max:120',
        ]);

        $businessId = $this->apiBusinessId();
        $branchFilterId = $this->apiBranchFilterId($request);
        $business = $this->apiBusiness();
        $businessTypes = $business
            ? ($branchFilterId ? $business->branchPosBusinessTypesMeta($branchFilterId) : $business->posBusinessTypesMeta())
            : [];
        $typeKey = $request->filled('business_type_key') ? (string) $request->business_type_key : null;

        $categories = Category::where('business_id', $businessId)
            ->whereHas('items')
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->when($typeKey, fn ($q) => $q->where('source_business_type_key', $typeKey))
            ->orderBy('name')
            ->get(['id', 'name', 'source_business_type_key'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'business_type_key' => $c->source_business_type_key ?: 'other',
            ])
            ->values();

        $selectedCategoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        if ($selectedCategoryId && ! Category::where('business_id', $businessId)->whereKey($selectedCategoryId)->exists()) {
            $selectedCategoryId = null;
        }

        $showUnpriced = $request->boolean('show_unpriced');
        $search = trim((string) $request->input('q', ''));

        $items = Item::where('business_id', $businessId)
            ->with(['category', 'packagings.packagingType'])
            ->when($branchFilterId, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('branch_id', $branchFilterId)))
            ->when($selectedCategoryId, fn ($q) => $q->where('category_id', $selectedCategoryId))
            ->when($typeKey, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('source_business_type_key', $typeKey)))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get();

        $normalizer = app(ItemPackagingNormalizer::class);
        $grouped = [];
        $pricedPackagingCount = 0;

        foreach ($items as $item) {
            $rows = $normalizer->normalizeItemPackagings($item, $item->packagings)
                ->map(fn ($row) => [
                    'packaging_id' => $row['packaging']->id,
                    'label' => $row['packaging']->packagingType?->name ?? 'Unit',
                    'quantity_per_unit' => max(1, (int) $row['quantity_per_unit']),
                    'selling_price' => (float) $row['packaging']->selling_price,
                ])
                ->filter(fn ($row) => $showUnpriced || $row['selling_price'] > 0)
                ->values();

            if ($rows->isEmpty()) {
                if (! $showUnpriced) {
                    continue;
                }
                $rows = collect([[
                    'packaging_id' => null,
                    'label' => $item->baseStockUnitName(),
                    'quantity_per_unit' => 1,
                    'selling_price' => 0.0,
                ]]);
            }

            $pricedPackagingCount += $rows->where('selling_price', '>', 0)->count();
            $categoryName = $item->category?->name ?? __('dashboard.uncategorized');

            $grouped[$categoryName]['category_id'] = $item->category_id;
            $grouped[$categoryName]['category'] = $categoryName;
            $grouped[$categoryName]['business_type_key'] = $item->category?->source_business_type_key ?: 'other';
            $grouped[$categoryName]['items'][] = [
                'id' => $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'sku' => $item->sku,
                'prices' => $rows->all(),
            ];
        }

        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->success([
            'business' => [
                'name' => $this->apiBusiness()?->name,
                'currency' => 'TZS',
            ],
            'branch_id' => $branchFilterId,
            'branch_name' => $this->branchName($branchFilterId),
            'categories' => $categories,
            'business_types' => $businessTypes,
            'multi_business' => count($businessTypes) > 1,
            'business_type_key' => $typeKey,
            'selected_category_id' => $selectedCategoryId,
            'show_unpriced' => $showUnpriced,
            'search' => $search,
            'total_items' => collect($grouped)->sum(fn ($g) => count($g['items'])),
            'priced_packaging_count' => $pricedPackagingCount,
            'groups' => array_values($grouped),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
