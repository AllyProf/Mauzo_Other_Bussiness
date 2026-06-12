<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\ItemStockDisplayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['adjust_stock', 'view_stock_adjustments']);

        $user = Auth::user();
        $businessId = (int) $user->business_id;
        $filter = $this->branchBusinessFilterContext($request);
        extract($filter);

        $canAdjust = $user->can('adjust_stock');
        $applyScope = function ($query) use ($user, $branchFilterId) {
            if (! $this->actsAsBusinessWideViewer()) {
                $query->where('user_id', $user->id);
                if ($branchFilterId) {
                    $query->where('branch_id', $branchFilterId);
                }

                return;
            }

            if ($branchFilterId) {
                $query->where(function ($scoped) use ($branchFilterId, $user) {
                    $scoped->where('branch_id', $branchFilterId)
                        ->orWhere('user_id', $user->id);
                });
            }
        };

        $adjustmentsQuery = StockAdjustment::where('business_id', $businessId)
            ->with(['user', 'branch'])
            ->withCount('items')
            ->latest();
        $applyScope($adjustmentsQuery);
        $adjustments = $adjustmentsQuery->paginate(15)->withQueryString();

        $statsQuery = StockAdjustment::where('business_id', $businessId)->where('status', 'completed');
        $applyScope($statsQuery);

        $stats = [
            'total_records' => (clone $statsQuery)->count(),
            'total_lines' => (int) StockAdjustmentItem::query()
                ->whereIn('stock_adjustment_id', (clone $statsQuery)->select('id'))
                ->count(),
            'net_adjustment' => (float) (clone $statsQuery)->sum('net_adjustment'),
        ];

        $form = $canAdjust ? $this->formContext($branchFilterId) : null;

        return view('stock-adjustments.index', compact(
            'adjustments',
            'stats',
            'form',
            'canAdjust',
        ) + $filter);
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['adjust_stock']);

        $request->validate([
            'adjustment_date' => 'required|date',
            'reason' => 'required|in:'.implode(',', array_keys(StockAdjustment::REASONS)),
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.new_stock' => 'required|numeric|min:0',
            'items.*.line_notes' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'confirm_ack' => 'accepted',
        ], [
            'confirm_ack.accepted' => __('stock_adjustments.confirm_required'),
        ]);

        $activeItems = [];
        foreach ($request->items as $row) {
            if (! array_key_exists('new_stock', $row)) {
                continue;
            }
            $activeItems[] = $row;
        }

        if (empty($activeItems)) {
            return redirect()->back()->with('error', __('stock_adjustments.no_items'))->withInput();
        }

        $businessId = (int) Auth::user()->business_id;
        $branchId = Auth::user()->branch_id ? (int) Auth::user()->branch_id : $this->resolveBranchFilterId();

        DB::beginTransaction();

        try {
            $lineRecords = [];
            $netAdjustment = 0;

            foreach ($activeItems as $row) {
                $item = Item::where('business_id', $businessId)->find($row['id']);
                if (! $item) {
                    throw new \InvalidArgumentException(__('stock_adjustments.invalid_item'));
                }

                if ($branchId) {
                    $item->loadMissing('category');
                    if ((int) ($item->category?->branch_id ?? 0) !== (int) $branchId) {
                        throw new \InvalidArgumentException(__('stock_adjustments.item_branch_mismatch', ['item' => $item->name]));
                    }
                }

                $previous = (float) $item->current_stock;
                $newStock = (float) $row['new_stock'];
                $delta = round($newStock - $previous, 2);

                if (abs($delta) < 0.0001) {
                    continue;
                }

                $netAdjustment += $delta;
                $lineRecords[] = [
                    'item' => $item,
                    'previous_stock' => $previous,
                    'new_stock' => $newStock,
                    'adjustment_qty' => $delta,
                    'line_notes' => $row['line_notes'] ?? null,
                ];
            }

            if (empty($lineRecords)) {
                throw new \InvalidArgumentException(__('stock_adjustments.no_changes'));
            }

            $ref = 'ADJ-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $adjustment = StockAdjustment::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'user_id' => Auth::id(),
                'reference_no' => $ref,
                'adjustment_date' => $request->adjustment_date,
                'reason' => $request->reason,
                'total_items' => count($lineRecords),
                'net_adjustment' => $netAdjustment,
                'notes' => $request->notes,
                'status' => 'completed',
            ]);

            foreach ($lineRecords as $line) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $line['item']->id,
                    'previous_stock' => $line['previous_stock'],
                    'new_stock' => $line['new_stock'],
                    'adjustment_qty' => $line['adjustment_qty'],
                    'line_notes' => $line['line_notes'],
                ]);

                $line['item']->update(['current_stock' => $line['new_stock']]);
            }

            DB::commit();

            return redirect()->route('stock-adjustments.index')
                ->with('success', __('stock_adjustments.saved', ['ref' => $ref]));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('stock-adjustments.index', ['adjust' => 1])
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $this->authorizeAny(['adjust_stock', 'view_stock_adjustments']);
        $this->ensureAccess($stockAdjustment);

        $stockAdjustment->load(['items.item.category', 'user', 'branch']);

        return view('stock-adjustments.show', compact('stockAdjustment'));
    }

    public function cancel(StockAdjustment $stockAdjustment)
    {
        $this->authorizeAny(['adjust_stock']);

        $this->ensureAccess($stockAdjustment);

        if ($stockAdjustment->isCancelled()) {
            return redirect()->back()->with('error', __('stock_adjustments.already_cancelled'));
        }

        DB::beginTransaction();

        try {
            $stockAdjustment->load('items.item');

            foreach ($stockAdjustment->items as $line) {
                if ($line->item) {
                    $line->item->update(['current_stock' => $line->previous_stock]);
                }
            }

            $stockAdjustment->update(['status' => 'cancelled']);

            DB::commit();

            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('success', __('stock_adjustments.cancelled'));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function ensureAccess(StockAdjustment $stockAdjustment): void
    {
        if ((int) $stockAdjustment->business_id !== (int) Auth::user()->business_id) {
            abort(403);
        }

        if (! $this->actsAsBusinessWideViewer() && (int) $stockAdjustment->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $branchFilterId = $this->resolveBranchFilterId();
        if ($branchFilterId
            && (int) $stockAdjustment->branch_id !== (int) $branchFilterId
            && (int) $stockAdjustment->user_id !== (int) Auth::id()) {
            abort(403);
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
                $stockDisplay = app(ItemStockDisplayService::class);

                return [$cat->id => $cat->items->map(function ($item) use ($stockDisplay) {
                    $formatted = $stockDisplay->format($item);
                    $breakdown = collect($formatted['packaging_breakdown'] ?? [])
                        ->map(fn ($row) => $row['formatted_count'].' '.$row['name'])
                        ->implode(' · ');

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku ?? '',
                        'stock' => (float) $item->current_stock,
                        'stock_label' => $stockDisplay->remainsDisplay($item),
                        'stock_breakdown' => $breakdown,
                    ];
                })->values()];
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
            'reasons' => StockAdjustment::REASONS,
            'businessTypes' => $businessTypes,
            'multiBusiness' => $multiBusiness,
        ];
    }
}
