<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockLoss;
use App\Models\StockLossItem;
use App\Services\StockShortageImpactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class StockLossController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['record_stock_loss', 'view_stock_history', 'open_shift', 'process_sales']);

        $user = Auth::user();
        $businessId = (int) $user->business_id;
        $filter = $this->branchBusinessFilterContext($request);
        extract($filter);
        $canViewManualLosses = $user->can('record_stock_loss') || $user->can('view_stock_history');
        $showStaffShortages = $user->requiresOpenShift()
            && ($user->can('open_shift') || $user->can('process_sales'));

        if ($canViewManualLosses) {
            $applyLossScope = function ($query) use ($user) {
                if (! $this->actsAsBusinessWideViewer()) {
                    $query->where('user_id', $user->id);
                } else {
                    $this->scopeStockLossesForActiveBranch($query);
                }
            };

            $applyItemFilters = function ($query) use ($branchFilterId, $activeBusinessType) {
                if ($branchFilterId || $activeBusinessType) {
                    $query->whereHas('items.item', function ($itemQuery) use ($branchFilterId, $activeBusinessType) {
                        if ($branchFilterId) {
                            $itemQuery->whereHas('category', fn ($cat) => $cat->where('branch_id', $branchFilterId));
                        }
                        if ($activeBusinessType) {
                            $itemQuery->whereHas('category', fn ($cat) => $cat->where('source_business_type_key', $activeBusinessType));
                        }
                    });
                }
            };

            $lossQuery = StockLoss::where('business_id', $businessId)
                ->with(['user'])
                ->withCount('items')
                ->latest();
            $applyLossScope($lossQuery);
            $applyItemFilters($lossQuery);
            $losses = $lossQuery->paginate(15)->withQueryString();

            $statsQuery = StockLoss::where('business_id', $businessId)->where('status', 'completed');
            $applyLossScope($statsQuery);
            $applyItemFilters($statsQuery);

            $stats = [
                'total_records' => (clone $statsQuery)->count(),
                'total_units_lost' => (float) (clone $statsQuery)->sum('total_quantity'),
                'total_cost_value' => (float) (clone $statsQuery)->sum('total_cost_value'),
            ];
        } else {
            $losses = new LengthAwarePaginator([], 0, 15);
            $stats = [
                'total_records' => 0,
                'total_units_lost' => 0.0,
                'total_cost_value' => 0.0,
            ];
        }

        $shortageService = app(StockShortageImpactService::class);
        $myStockShortages = $showStaffShortages
            ? $shortageService->staffShortagesForUser($user, 30)
            : collect();
        $myShortageStats = $shortageService->staffShortageStats($myStockShortages);

        $form = ($canViewManualLosses && $user->can('record_stock_loss'))
            ? $this->formContext($branchFilterId)
            : null;

        return view('stock-losses.index', compact(
            'losses',
            'stats',
            'form',
            'myStockShortages',
            'myShortageStats',
            'canViewManualLosses',
            'showStaffShortages',
        ) + $filter);
    }

    public function create()
    {
        $this->authorizeAny(['record_stock_loss']);

        return redirect()->route('stock-losses.index', ['record' => 1]);
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['record_stock_loss']);

        $request->validate([
            'loss_date' => 'required|date',
            'reason' => 'required|in:'.implode(',', array_keys(StockLoss::REASONS)),
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.line_notes' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $activeItems = array_values(array_filter(
            $request->items,
            fn ($row) => (float) ($row['qty'] ?? 0) > 0
        ));

        if (empty($activeItems)) {
            return redirect()->back()->with('error', 'Please enter at least one item with quantity.')->withInput();
        }

        DB::beginTransaction();

        try {
            foreach ($activeItems as $row) {
                $item = Item::where('business_id', Auth::user()->business_id)->find($row['id']);
                if (! $item) {
                    throw new \InvalidArgumentException('Invalid item selected.');
                }

                if ((float) $row['qty'] > (float) $item->current_stock) {
                    throw new \InvalidArgumentException(
                        "Not enough stock for {$item->name}. Available: {$item->current_stock}."
                    );
                }
            }

            $ref = 'LOSS-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
            $totalQty = 0;
            $totalCost = 0;
            $lineRecords = [];

            foreach ($activeItems as $row) {
                $item = Item::with('packagings')->find($row['id']);
                $qty = (float) $row['qty'];
                $unitCost = (float) (optional($item->packagings->first())->cost_price ?? 0);
                $costValue = $qty * $unitCost;

                $totalQty += $qty;
                $totalCost += $costValue;

                $lineRecords[] = [
                    'item' => $item,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'cost_value' => $costValue,
                    'line_notes' => $row['line_notes'] ?? null,
                ];
            }

            $stockLoss = StockLoss::create([
                'business_id' => Auth::user()->business_id,
                'user_id' => Auth::id(),
                'reference_no' => $ref,
                'loss_date' => $request->loss_date,
                'reason' => $request->reason,
                'total_quantity' => $totalQty,
                'total_cost_value' => $totalCost,
                'notes' => $request->notes,
                'status' => 'completed',
            ]);

            foreach ($lineRecords as $line) {
                StockLossItem::create([
                    'stock_loss_id' => $stockLoss->id,
                    'item_id' => $line['item']->id,
                    'quantity' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                    'cost_value' => $line['cost_value'],
                    'line_notes' => $line['line_notes'],
                ]);

                $line['item']->current_stock = max(0, (float) $line['item']->current_stock - $line['qty']);
                $line['item']->save();
            }

            DB::commit();

            return redirect()->route('stock-losses.index')
                ->with('success', "Stock loss recorded successfully ({$ref}). Inventory has been updated.");
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('stock-losses.index')
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    private function formContext(?int $branchFilterId = null): array
    {
        $businessId = Auth::user()->business_id;
        $business = Auth::user()->business;
        $branchFilterId = $branchFilterId ?? $this->resolveBranchFilterId();
        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;

        $categoriesQuery = Category::where('business_id', $businessId)->has('items');
        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }
        $categories = $categoriesQuery->orderBy('name')->get();

        $itemsByCategory = Category::where('business_id', $businessId)
            ->has('items')
            ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
            ->with(['items.packagings.packagingType'])
            ->get()
            ->mapWithKeys(function ($cat) {
                return [$cat->id => $cat->items->map(function ($item) {
                    $pkg = $item->packagings->first();

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku ?? '',
                        'stock' => (float) $item->current_stock,
                        'unit' => optional($pkg?->packagingType)->name ?? 'Unit',
                        'unit_cost' => (float) (optional($pkg)->cost_price ?? 0),
                    ];
                })->filter(fn ($item) => $item['stock'] > 0)->values()];
            })
            ->filter(fn ($items) => $items->isNotEmpty());

        $categoriesList = $categories
            ->filter(fn ($cat) => isset($itemsByCategory[$cat->id]))
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'business_type_key' => $cat->source_business_type_key ?: 'other',
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'categoriesList' => $categoriesList,
            'itemsByCategory' => $itemsByCategory,
            'reasons' => StockLoss::REASONS,
            'businessTypes' => $businessTypes,
            'multiBusiness' => $multiBusiness,
        ];
    }

    public function show(StockLoss $stockLoss)
    {
        $this->authorizeAny(['record_stock_loss', 'view_stock_history']);

        if ($stockLoss->business_id != Auth::user()->business_id) {
            abort(403);
        }

        $stockLoss->load(['items.item.category', 'user']);

        return view('stock-losses.show', compact('stockLoss'));
    }

    public function cancel(StockLoss $stockLoss)
    {
        $this->authorizeAny(['cancel_stock_loss', 'record_stock_loss']);

        if ($stockLoss->business_id != Auth::user()->business_id) {
            abort(403);
        }

        if ($stockLoss->isCancelled()) {
            return redirect()->back()->with('error', 'This record has already been cancelled.');
        }

        DB::beginTransaction();

        try {
            $stockLoss->load('items.item');

            foreach ($stockLoss->items as $line) {
                if ($line->item) {
                    $line->item->current_stock += (float) $line->quantity;
                    $line->item->save();
                }
            }

            $stockLoss->update([
                'status' => 'cancelled',
                'notes' => trim(($stockLoss->notes ?? '').' [Cancelled on '.now()->format('Y-m-d H:i').']') ?: null,
            ]);

            DB::commit();

            return redirect()->route('stock-losses.index')
                ->with('success', "Stock loss {$stockLoss->reference_no} cancelled. Stock has been restored.");
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Could not cancel: '.$e->getMessage());
        }
    }
}
