<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\UsesWebBranchContext;
use App\Models\Category;
use App\Models\Item;
use App\Models\ShiftStockCheck;
use App\Models\StockLoss;
use App\Models\StockLossItem;
use App\Services\StockShortageImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Same as web /stock-losses: write off lost / damaged / expired stock.
 */
class StockLossController extends ApiController
{
    use UsesWebBranchContext;

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['record_stock_loss', 'view_stock_history', 'open_shift', 'process_sales'])) {
            return $deny;
        }

        $branchFilterId = $this->bootWebBranchContext($request);
        $filter = $this->branchBusinessFilterContext($request);
        $user = $request->user();
        $businessId = $this->apiBusinessId();
        $activeBusinessType = $filter['activeBusinessType'];

        $canViewManualLosses = $user->can('record_stock_loss') || $user->can('view_stock_history');
        $showStaffShortages = $user->requiresOpenShift() && ($user->can('open_shift') || $user->can('process_sales'));

        $losses = null;
        $stats = ['total_records' => 0, 'total_units_lost' => 0.0, 'total_cost_value' => 0.0];

        if ($canViewManualLosses) {
            $applyScope = function ($query) use ($user, $branchFilterId, $activeBusinessType) {
                if (! $user->seesBusinessWideData()) {
                    $query->where('user_id', $user->id);
                } else {
                    $this->scopeStockLossesForActiveBranch($query);
                }

                if ($branchFilterId || $activeBusinessType) {
                    $query->whereHas('items.item.category', function ($cat) use ($branchFilterId, $activeBusinessType) {
                        if ($branchFilterId) {
                            $cat->where('branch_id', $branchFilterId);
                        }
                        if ($activeBusinessType) {
                            $cat->where('source_business_type_key', $activeBusinessType);
                        }
                    });
                }
            };

            $query = StockLoss::where('business_id', $businessId)
                ->with(['user:id,name'])
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
                $query->whereDate('loss_date', '>=', $request->get('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('loss_date', '<=', $request->get('date_to'));
            }

            $losses = $query->paginate($this->perPage($request, 15));

            $statsQuery = StockLoss::where('business_id', $businessId)->where('status', 'completed');
            $applyScope($statsQuery);
            $stats = [
                'total_records' => (clone $statsQuery)->count(),
                'total_units_lost' => (float) (clone $statsQuery)->sum('total_quantity'),
                'total_cost_value' => (float) (clone $statsQuery)->sum('total_cost_value'),
            ];
        }

        $shortageService = app(StockShortageImpactService::class);
        $myShortages = $showStaffShortages ? $shortageService->staffShortagesForUser($user, 30) : collect();

        return $this->success([
            'stats' => $stats,
            'losses' => $losses
                ? collect($losses->items())->map(fn (StockLoss $l) => $this->summary($l))->values()
                : [],
            'can_view_losses' => $canViewManualLosses,
            'can_record' => $canViewManualLosses && $user->can('record_stock_loss'),
            'reasons' => $this->reasonsList(),
            'my_stock_shortages' => $myShortages->map(fn (ShiftStockCheck $check) => [
                'id' => $check->id,
                'item' => $check->item?->name,
                'category' => $check->item?->category?->name,
                'shift_id' => $check->shift_id,
                'system_stock' => (float) $check->system_stock,
                'counted_stock' => (float) $check->counted_stock,
                'shortage_qty' => abs((float) $check->variance),
                'cost_value' => (float) ($check->financial_impact['cost_value'] ?? 0),
                'notes' => $check->notes,
                'owner_decision' => $check->owner_decision,
                'is_verified' => $check->isVerified(),
                'recorded_at' => $check->recorded_at?->toIso8601String(),
            ])->values(),
            'my_shortage_stats' => $shortageService->staffShortageStats($myShortages),
            'show_staff_shortages' => $showStaffShortages,
            'filters' => $this->filterMetaPayload($filter),
            'meta' => $losses ? $this->paginationMeta($losses) : ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['record_stock_loss'])) {
            return $deny;
        }

        $branchFilterId = $this->bootWebBranchContext($request);
        $business = $this->apiBusiness();
        $businessId = $this->apiBusinessId();

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
            $items = $category->items
                ->filter(fn (Item $item) => (float) $item->current_stock > 0)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(function (Item $item) {
                    $pkg = $item->packagings->first();

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku ?? '',
                        'brand' => $item->brand ?? '',
                        'stock' => (float) $item->current_stock,
                        'unit' => optional($pkg?->packagingType)->name ?? 'Unit',
                        'unit_cost' => (float) (optional($pkg)->cost_price ?? 0),
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
            'defaults' => ['loss_date' => now()->toDateString()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['record_stock_loss'])) {
            return $deny;
        }

        $request->validate([
            'loss_date' => 'required|date',
            'reason' => 'required|in:'.implode(',', array_keys(StockLoss::REASONS)),
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.line_notes' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $businessId = $this->apiBusinessId();

        DB::beginTransaction();
        try {
            $lineRecords = [];
            $totalQty = 0.0;
            $totalCost = 0.0;

            foreach ($request->items as $row) {
                $item = Item::where('business_id', $businessId)->with('packagings')->find($row['id']);
                if (! $item) {
                    throw new \InvalidArgumentException('Invalid item selected.');
                }

                $qty = (float) $row['qty'];
                if ($qty > (float) $item->current_stock) {
                    throw new \InvalidArgumentException("Not enough stock for {$item->name}. Available: {$item->current_stock}.");
                }

                $unitCost = (float) (optional($item->packagings->first())->cost_price ?? 0);
                $totalQty += $qty;
                $totalCost += $qty * $unitCost;
                $lineRecords[] = [
                    'item' => $item,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'cost_value' => $qty * $unitCost,
                    'line_notes' => $row['line_notes'] ?? null,
                ];
            }

            $ref = 'LOSS-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $stockLoss = StockLoss::create([
                'business_id' => $businessId,
                'user_id' => $request->user()->id,
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
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Failed to record stock loss: '.$e->getMessage(), 500);
        }

        $stockLoss->load(['items.item.category', 'user:id,name']);

        return $this->success(
            ['loss' => $this->detail($stockLoss)],
            "Stock loss recorded successfully ({$ref}). Inventory has been updated.",
            201
        );
    }

    public function show(Request $request, StockLoss $stockLoss): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['record_stock_loss', 'view_stock_history'])) {
            return $deny;
        }

        if ((int) $stockLoss->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $stockLoss->load(['items.item.category', 'user:id,name']);

        return $this->success(['loss' => $this->detail($stockLoss)]);
    }

    public function cancel(Request $request, StockLoss $stockLoss): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['cancel_stock_loss', 'record_stock_loss'])) {
            return $deny;
        }

        if ((int) $stockLoss->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        if ($stockLoss->isCancelled()) {
            return $this->error('This record has already been cancelled.', 422);
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
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Could not cancel: '.$e->getMessage(), 422);
        }

        $stockLoss->load(['items.item.category', 'user:id,name']);

        return $this->success(
            ['loss' => $this->detail($stockLoss)],
            "Stock loss {$stockLoss->reference_no} cancelled. Stock has been restored."
        );
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function reasonsList(): array
    {
        return collect(StockLoss::REASONS)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(StockLoss $l): array
    {
        return [
            'id' => $l->id,
            'reference_no' => $l->reference_no,
            'loss_date' => $l->loss_date?->toDateString(),
            'reason' => $l->reason,
            'reason_label' => $l->reasonLabel(),
            'items_count' => (int) ($l->items_count ?? $l->items()->count()),
            'total_quantity' => (float) $l->total_quantity,
            'total_cost_value' => (float) $l->total_cost_value,
            'status' => $l->status,
            'notes' => $l->notes,
            'recorded_by' => $l->user?->name,
            'created_at' => $l->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(StockLoss $l): array
    {
        return array_merge($this->summary($l), [
            'items_count' => $l->items->count(),
            'items' => $l->items->map(fn (StockLossItem $line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'name' => $line->item?->name,
                'sku' => $line->item?->sku,
                'category' => $line->item?->category?->name,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'cost_value' => (float) $line->cost_value,
                'line_notes' => $line->line_notes,
            ])->values(),
        ]);
    }
}
