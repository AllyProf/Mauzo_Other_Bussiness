<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\ItemStockDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Same as web /stock-adjustments: set an item's stock to a counted value (records the difference).
 */
class StockAdjustmentController extends ApiController
{
    use UsesWebBranchContext;

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['adjust_stock', 'view_stock_adjustments'])) {
            return $deny;
        }

        $branchFilterId = $this->bootWebBranchContext($request);
        $filter = $this->branchBusinessFilterContext($request);
        $user = $request->user();
        $businessId = $this->apiBusinessId();

        $applyScope = function ($query) use ($user, $branchFilterId) {
            if (! $user->seesBusinessWideData()) {
                $query->where('user_id', $user->id);
                if ($branchFilterId) {
                    $query->where('branch_id', $branchFilterId);
                }

                return;
            }

            if ($branchFilterId) {
                $query->where(fn ($scoped) => $scoped->where('branch_id', $branchFilterId)->orWhere('user_id', $user->id));
            }
        };

        $query = StockAdjustment::where('business_id', $businessId)
            ->with(['user:id,name', 'branch:id,name'])
            ->withCount('items')
            ->latest();
        $applyScope($query);

        if (in_array($request->get('status'), ['completed', 'cancelled'], true)) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('reason')) {
            $query->where('reason', $request->get('reason'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('adjustment_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('adjustment_date', '<=', $request->get('date_to'));
        }

        $adjustments = $query->paginate($this->perPage($request, 15));

        $statsQuery = StockAdjustment::where('business_id', $businessId)->where('status', 'completed');
        $applyScope($statsQuery);

        return $this->success([
            'stats' => [
                'total_records' => (clone $statsQuery)->count(),
                'total_lines' => (int) StockAdjustmentItem::whereIn('stock_adjustment_id', (clone $statsQuery)->select('id'))->count(),
                'net_adjustment' => (float) (clone $statsQuery)->sum('net_adjustment'),
            ],
            'adjustments' => collect($adjustments->items())->map(fn (StockAdjustment $a) => $this->summary($a))->values(),
            'can_adjust' => $user->can('adjust_stock'),
            'reasons' => $this->reasonsList(),
            'filters' => $this->filterMetaPayload($filter),
            'meta' => $this->paginationMeta($adjustments),
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['adjust_stock'])) {
            return $deny;
        }

        $branchFilterId = $this->bootWebBranchContext($request);
        $business = $this->apiBusiness();
        $businessId = $this->apiBusinessId();
        $stockDisplay = app(ItemStockDisplayService::class);

        $businessTypes = $branchFilterId
            ? $business->branchPosBusinessTypesMeta($branchFilterId)
            : $business->posBusinessTypesMeta();

        $categories = Category::where('business_id', $businessId)
            ->has('items')
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->with(['items.packagings.packagingType'])
            ->orderBy('name')
            ->get();

        $itemsByCategory = [];
        $categoryList = [];
        foreach ($categories as $category) {
            $items = $category->items->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->map(function (Item $item) use ($stockDisplay) {
                $formatted = $stockDisplay->format($item);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku ?? '',
                    'brand' => $item->brand ?? '',
                    'stock' => (float) $item->current_stock,
                    'stock_label' => $stockDisplay->remainsDisplay($item),
                    'stock_breakdown' => collect($formatted['packaging_breakdown'] ?? [])
                        ->map(fn ($row) => $row['formatted_count'].' '.$row['name'])
                        ->implode(' · '),
                ];
            })->values()->all();

            if ($items === []) {
                continue;
            }

            $itemsByCategory[(string) $category->id] = $items;
            $categoryList[] = [
                'id' => $category->id,
                'name' => $category->name,
                'branch_id' => $category->branch_id,
                'business_type_key' => $category->source_business_type_key ?: 'other',
            ];
        }

        return $this->success([
            'categories' => $categoryList,
            'items_by_category' => $itemsByCategory,
            'reasons' => $this->reasonsList(),
            'business_types' => array_values($businessTypes),
            'multi_business' => count($businessTypes) > 1,
            'branch_id' => $branchFilterId,
            'branch_name' => $this->branchName($branchFilterId),
            'defaults' => ['adjustment_date' => now()->toDateString()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['adjust_stock'])) {
            return $deny;
        }

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

        $user = $request->user();
        $businessId = $this->apiBusinessId();
        $branchId = $user->branch_id ? (int) $user->branch_id : $this->apiBranchFilterId($request);

        DB::beginTransaction();
        try {
            $lineRecords = [];
            $netAdjustment = 0.0;

            foreach ($request->items as $row) {
                $item = Item::where('business_id', $businessId)->with('category')->find($row['id']);
                if (! $item) {
                    throw new \InvalidArgumentException(__('stock_adjustments.invalid_item'));
                }

                if ($branchId && (int) ($item->category?->branch_id ?? 0) !== (int) $branchId) {
                    throw new \InvalidArgumentException(__('stock_adjustments.item_branch_mismatch', ['item' => $item->name]));
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

            if ($lineRecords === []) {
                throw new \InvalidArgumentException(__('stock_adjustments.no_changes'));
            }

            $ref = 'ADJ-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $adjustment = StockAdjustment::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'user_id' => $user->id,
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
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Failed to save adjustment: '.$e->getMessage(), 500);
        }

        $adjustment->load(['items.item.category', 'user:id,name', 'branch:id,name']);

        return $this->success(['adjustment' => $this->detail($adjustment)], __('stock_adjustments.saved', ['ref' => $ref]), 201);
    }

    public function show(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['adjust_stock', 'view_stock_adjustments'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($request, $stockAdjustment)) {
            return $deny;
        }

        $stockAdjustment->load(['items.item.category', 'user:id,name', 'branch:id,name']);

        return $this->success(['adjustment' => $this->detail($stockAdjustment)]);
    }

    public function cancel(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['adjust_stock'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($request, $stockAdjustment)) {
            return $deny;
        }

        if ($stockAdjustment->isCancelled()) {
            return $this->error(__('stock_adjustments.already_cancelled'), 422);
        }

        DB::beginTransaction();
        try {
            $stockAdjustment->load('items.item');
            foreach ($stockAdjustment->items as $line) {
                $line->item?->update(['current_stock' => $line->previous_stock]);
            }
            $stockAdjustment->update(['status' => 'cancelled']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        }

        $stockAdjustment->load(['items.item.category', 'user:id,name', 'branch:id,name']);

        return $this->success(['adjustment' => $this->detail($stockAdjustment)], __('stock_adjustments.cancelled'));
    }

    private function ensureAccess(Request $request, StockAdjustment $adjustment): ?JsonResponse
    {
        $user = $request->user();

        if ((int) $adjustment->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if (! $user->seesBusinessWideData() && (int) $adjustment->user_id !== (int) $user->id) {
            return $this->forbidden('You can only view your own adjustments.');
        }

        return null;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function reasonsList(): array
    {
        return collect(StockAdjustment::REASONS)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(StockAdjustment $a): array
    {
        return [
            'id' => $a->id,
            'reference_no' => $a->reference_no,
            'adjustment_date' => $a->adjustment_date?->toDateString(),
            'reason' => $a->reason,
            'reason_label' => $a->reasonLabel(),
            'total_items' => (int) ($a->items_count ?? $a->total_items),
            'net_adjustment' => (float) $a->net_adjustment,
            'status' => $a->status,
            'notes' => $a->notes,
            'branch' => $a->branch?->name,
            'recorded_by' => $a->user?->name,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(StockAdjustment $a): array
    {
        return $this->summary($a) + [
            'items' => $a->items->map(fn (StockAdjustmentItem $line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'name' => $line->item?->name,
                'sku' => $line->item?->sku,
                'category' => $line->item?->category?->name,
                'previous_stock' => (float) $line->previous_stock,
                'new_stock' => (float) $line->new_stock,
                'adjustment_qty' => (float) $line->adjustment_qty,
                'line_notes' => $line->line_notes,
            ])->values(),
        ];
    }
}
