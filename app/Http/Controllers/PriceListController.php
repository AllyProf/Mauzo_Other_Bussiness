<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Services\ItemPackagingNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceListController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['view_price_list', 'view_inventory', 'process_sales']);

        $business = Auth::user()->business;
        $businessId = $this->currentBusinessId();

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        $categoriesQuery = Category::where('business_id', $businessId)
            ->whereIn('id', function ($query) use ($businessId, $branchFilterId) {
                $query->select('category_id')
                    ->from('items')
                    ->where('business_id', $businessId)
                    ->whereNotNull('category_id');

                if ($branchFilterId) {
                    $query->whereIn('category_id', function ($categoryQuery) use ($branchFilterId) {
                        $categoryQuery->select('id')
                            ->from('categories')
                            ->where('branch_id', $branchFilterId);
                    });
                }
            })
            ->orderBy('name');
        $categories = $categoriesQuery->get(['id', 'name']);

        $selectedCategoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        if ($selectedCategoryId && ! $categories->contains('id', $selectedCategoryId)) {
            $selectedCategory = Category::where('business_id', $businessId)
                ->where('id', $selectedCategoryId)
                ->first(['id', 'name']);
            if ($selectedCategory) {
                $categories->push($selectedCategory);
                $categories = $categories->sortBy('name')->values();
            } else {
                $selectedCategoryId = null;
            }
        }

        $showUnpriced = $request->boolean('show_unpriced');
        $search = trim((string) $request->input('q', ''));

        $itemsQuery = Item::where('business_id', $businessId)
            ->with(['category', 'packagings.packagingType'])
            ->orderBy('name');

        if ($branchFilterId) {
            $itemsQuery->whereHas('category', fn ($query) => $query->where('branch_id', $branchFilterId));
        }

        if ($selectedCategoryId) {
            $itemsQuery->where('category_id', $selectedCategoryId);
        }

        if ($search !== '') {
            $itemsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        $items = $itemsQuery->get();
        $normalizer = app(ItemPackagingNormalizer::class);
        $grouped = collect();
        $pricedPackagingCount = 0;

        foreach ($items as $item) {
            $normalized = $normalizer->normalizeItemPackagings($item, $item->packagings);

            $packagingRows = $normalized->map(function ($row) {
                $packaging = $row['packaging'];

                return [
                    'label' => $packaging->packagingType?->name ?? 'Unit',
                    'quantity_per_unit' => max(1, (int) $row['quantity_per_unit']),
                    'selling_price' => (float) $packaging->selling_price,
                ];
            })->filter(fn ($row) => $showUnpriced || $row['selling_price'] > 0)->values();

            if ($packagingRows->isEmpty()) {
                if (! $showUnpriced) {
                    continue;
                }

                $packagingRows = collect([[
                    'label' => $item->baseStockUnitName(),
                    'quantity_per_unit' => 1,
                    'selling_price' => 0.0,
                ]]);
            }

            $pricedPackagingCount += $packagingRows->where('selling_price', '>', 0)->count();

            $categoryName = $item->category?->name ?? __('dashboard.uncategorized');

            if (! $grouped->has($categoryName)) {
                $grouped[$categoryName] = collect();
            }

            $grouped[$categoryName]->push([
                'id' => $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'sku' => $item->sku,
                'packaging_rows' => $packagingRows->all(),
            ]);
        }

        $grouped = $grouped->sortKeys();
        $totalItems = $grouped->flatten(1)->count();
        $selectedCategoryName = $selectedCategoryId
            ? ($categories->firstWhere('id', $selectedCategoryId)?->name ?? __('price_list.all_categories'))
            : __('price_list.all_categories');

        return view('price-list.index', compact(
            'business',
            'categories',
            'selectedCategoryId',
            'selectedCategoryName',
            'showUnpriced',
            'search',
            'grouped',
            'totalItems',
            'pricedPackagingCount',
            'activeBranchName',
            'branchFilterId',
        ));
    }
}
