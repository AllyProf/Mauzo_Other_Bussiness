<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Item;
use App\Models\ReceivingItem;
use App\Models\SaleItem;
use App\Models\StockAdjustmentItem;
use App\Models\StockLossItem;
use App\Models\BranchTransferItem;
use App\Models\User;
use Illuminate\Http\Request;

class ItemStockApiService
{
    public function __construct(private ItemStockReportService $reportService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function listStock(User $user, int $businessId, ?int $branchFilterId, Request $request): array
    {
        $business = Business::findOrFail($businessId);
        $report = $this->reportService->build($user, $branchFilterId, $business);
        $canViewValue = (bool) ($report['canViewValue'] ?? false);

        $items = collect($report['stockItems']);

        if ($search = trim((string) $request->get('q', ''))) {
            $needle = mb_strtolower($search);
            $items = $items->filter(function ($item) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $item['name'] ?? '',
                    $item['sku'] ?? '',
                    $item['brand'] ?? '',
                    $item['category'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            });
        }

        if ($request->boolean('low_stock')) {
            $items = $items->where('is_low_stock', true);
        }

        if ($request->filled('business_type_key') && $request->get('business_type_key') !== 'all') {
            $key = (string) $request->get('business_type_key');
            $items = $items->where('business_type_key', $key);
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id;
            $items = $items->filter(function ($item) use ($categoryId, $businessId) {
                $model = Item::query()
                    ->where('business_id', $businessId)
                    ->where('id', $item['id'])
                    ->value('category_id');

                return (int) $model === $categoryId;
            });
        }

        if ($slug = trim((string) $request->get('category_slug', ''))) {
            $items = $items->where('category_slug', $slug);
        }

        $items = $items->values();

        $stats = [
            'total_items' => $items->count(),
            'low_stock' => $items->where('is_low_stock', true)->count(),
        ];

        $totals = null;
        if ($canViewValue) {
            $totalRevenue = (float) $items->sum('expected_revenue');
            $totalCost = (float) $items->sum('cost_holding_value');
            $totals = [
                'expected_revenue' => $totalRevenue,
                'expected_profit' => round($totalRevenue - $totalCost, 2),
                'cost_holding_value' => $totalCost,
            ];
        }

        return [
            'items' => $items->map(fn (array $item) => $this->stockItemPayload($item, $canViewValue))->values()->all(),
            'stats' => $stats,
            'totals' => $totals,
            'meta' => [
                'branch_filter_id' => $report['branchFilterId'] ?? $branchFilterId,
                'active_branch_name' => $report['activeBranchName'] ?? null,
                'viewing_all_branches' => (bool) ($report['viewingAllBranches'] ?? false),
                'business_types' => $report['businessTypes'] ?? [],
                'multi_business' => (bool) ($report['multiBusiness'] ?? false),
                'category_filters' => $report['categoryFilters'] ?? [],
                'low_stock_threshold' => (int) ($report['lowStockThreshold'] ?? 5),
                'can_view_value' => $canViewValue,
                'items_count' => $items->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemHistory(Item $item, int $businessId): array
    {
        $item->load(['category', 'packagings.packagingType', 'receivingPackaging']);

        $unitName = $item->baseStockUnitName();
        $movements = collect();

        $receivingItems = ReceivingItem::where('item_id', $item->id)
            ->whereHas('receiving', fn ($q) => $q->where('business_id', $businessId))
            ->with(['receiving.supplier', 'receiving.user'])
            ->get();

        foreach ($receivingItems as $receivingItem) {
            $receiving = $receivingItem->receiving;
            $stockQty = $receivingItem->receivedPieces($item);
            $cancelled = ($receiving->status ?? 'completed') === 'cancelled';

            $movements->push([
                'sort_date' => $receiving->received_date.' '.($receiving->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $receiving->received_date,
                'time' => $receiving->created_at?->format('h:i A') ?? '',
                'type' => 'stock_in',
                'type_label' => $cancelled ? 'Stock In (Cancelled)' : 'Stock In',
                'badge' => $cancelled ? 'secondary' : 'success',
                'reference' => $receiving->reference_no,
                'receiving_id' => $receiving->id,
                'quantity' => (float) $stockQty,
                'quantity_label' => '+'.$this->formatQty($stockQty),
                'quantity_unit' => $unitName,
                'direction' => 'in',
                'by' => $receiving->user->name ?? 'N/A',
                'party' => $receiving->supplier->name ?? 'N/A',
                'party_label' => 'Supplier',
                'details' => 'Received '.$receivingItem->receivedQuantityLabel($item)
                    .($cancelled ? ' — receiving cancelled, stock reversed' : ''),
                'status' => $cancelled ? 'Cancelled' : 'Completed',
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $saleItems = SaleItem::where('item_id', $item->id)
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId))
            ->with(['sale.user', 'itemPackaging.packagingType'])
            ->get();

        foreach ($saleItems as $saleItem) {
            $sale = $saleItem->sale;
            $cancelled = $sale->payment_status === 'cancelled';
            $packaging = $saleItem->itemPackaging;
            $soldUnitName = $packaging?->packagingType?->name ?? $unitName;
            $piecesSold = $item->stockUnitsForPackaging((int) $saleItem->quantity, $packaging);

            $movements->push([
                'sort_date' => $sale->sale_date.' '.($sale->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $sale->sale_date,
                'time' => $sale->created_at?->format('h:i A') ?? '',
                'type' => 'sale',
                'type_label' => $cancelled ? 'Sale (Cancelled)' : 'Sale',
                'badge' => $cancelled ? 'secondary' : 'primary',
                'reference' => $sale->reference_no,
                'sale_id' => $sale->id,
                'quantity' => (float) $piecesSold,
                'quantity_label' => '-'.$this->formatQty($saleItem->quantity),
                'quantity_unit' => $soldUnitName,
                'direction' => 'out',
                'by' => $sale->user->name ?? 'N/A',
                'party' => $sale->customer_name ?: 'Walk-in Customer',
                'party_label' => 'Customer',
                'details' => 'TZS '.number_format($saleItem->unit_price, 2).' × '
                    .$this->formatQty($saleItem->quantity).' '.$soldUnitName
                    .($piecesSold !== (float) $saleItem->quantity
                        ? ' ('.$this->formatQty($piecesSold).' '.$unitName.'(s))'
                        : '')
                    .' = TZS '.number_format($saleItem->subtotal, 2)
                    .($cancelled ? ' — sale cancelled, stock restored' : ''),
                'status' => ucfirst(str_replace('_', ' ', $sale->payment_status)),
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $lossItems = StockLossItem::where('item_id', $item->id)
            ->whereHas('stockLoss', fn ($q) => $q->where('business_id', $businessId))
            ->with(['stockLoss.user'])
            ->get();

        foreach ($lossItems as $lossItem) {
            $loss = $lossItem->stockLoss;
            $cancelled = $loss->isCancelled();

            $movements->push([
                'sort_date' => $loss->loss_date->format('Y-m-d').' '.($loss->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $loss->loss_date->format('Y-m-d'),
                'time' => $loss->created_at?->format('h:i A') ?? '',
                'type' => 'stock_loss',
                'type_label' => $cancelled ? 'Stock Loss (Cancelled)' : 'Stock Loss',
                'badge' => $cancelled ? 'secondary' : 'warning',
                'reference' => $loss->reference_no,
                'stock_loss_id' => $loss->id,
                'quantity' => (float) $lossItem->quantity,
                'quantity_label' => '-'.$this->formatQty($lossItem->quantity),
                'quantity_unit' => $unitName,
                'direction' => 'out',
                'by' => $loss->user->name ?? 'N/A',
                'party' => $loss->reasonLabel(),
                'party_label' => 'Reason',
                'details' => ($lossItem->line_notes ?: $loss->reasonLabel())
                    .($cancelled ? ' — record cancelled, stock restored' : ''),
                'status' => $cancelled ? 'Cancelled' : 'Recorded',
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $transferLines = BranchTransferItem::query()
            ->where(function ($q) use ($item) {
                $q->where('from_item_id', $item->id)->orWhere('to_item_id', $item->id);
            })
            ->whereHas('transfer', fn ($q) => $q->where('business_id', $businessId))
            ->with(['transfer.fromBranch', 'transfer.toBranch', 'transfer.user'])
            ->get();

        foreach ($transferLines as $line) {
            $transfer = $line->transfer;
            $cancelled = $transfer->isCancelled();
            $pending = $transfer->isPending();
            $isOut = (int) $line->from_item_id === (int) $item->id;

            if (! $isOut && $pending) {
                continue;
            }

            $movements->push([
                'sort_date' => $transfer->transfer_date->format('Y-m-d').' '.($transfer->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $transfer->transfer_date->format('Y-m-d'),
                'time' => $transfer->created_at?->format('h:i A') ?? '',
                'type' => $isOut ? 'transfer_out' : 'transfer_in',
                'type_label' => $cancelled
                    ? 'Branch supply (Cancelled)'
                    : ($pending
                        ? 'Sent to branch (awaiting receive)'
                        : ($isOut ? 'Supplied to branch' : 'Received from main')),
                'badge' => $cancelled ? 'secondary' : ($pending ? 'warning' : ($isOut ? 'warning' : 'info')),
                'reference' => $transfer->reference_no,
                'branch_transfer_id' => $transfer->id,
                'quantity' => (float) $line->quantity,
                'quantity_label' => ($isOut ? '-' : '+').$this->formatQty($line->quantity),
                'quantity_unit' => $unitName,
                'direction' => $isOut ? 'out' : 'in',
                'by' => $transfer->user->name ?? 'N/A',
                'party' => $isOut ? ($transfer->toBranch->name ?? 'Branch') : ($transfer->fromBranch->name ?? 'Main'),
                'party_label' => $isOut ? 'To' : 'From',
                'details' => ($isOut ? 'Sent to ' : 'Received from ')
                    .($isOut ? ($transfer->toBranch->name ?? 'branch') : ($transfer->fromBranch->name ?? 'main'))
                    .($cancelled ? ' — supply cancelled, stock reversed' : ($pending ? ' — waiting for destination to receive' : '')),
                'status' => $cancelled ? 'Cancelled' : ($pending ? 'Awaiting receive' : 'Completed'),
                'counts_toward_totals' => ! $cancelled && ! ($pending && ! $isOut),
            ]);
        }

        $adjustmentItems = StockAdjustmentItem::where('item_id', $item->id)
            ->whereHas('stockAdjustment', fn ($q) => $q->where('business_id', $businessId))
            ->with(['stockAdjustment.user'])
            ->get();

        foreach ($adjustmentItems as $adjustmentItem) {
            $adjustment = $adjustmentItem->stockAdjustment;
            $cancelled = $adjustment->isCancelled();
            $delta = (float) $adjustmentItem->adjustment_qty;
            $sign = $delta >= 0 ? '+' : '';

            $movements->push([
                'sort_date' => $adjustment->adjustment_date->format('Y-m-d').' '.($adjustment->created_at?->format('H:i:s') ?? '00:00:00'),
                'date' => $adjustment->adjustment_date->format('Y-m-d'),
                'time' => $adjustment->created_at?->format('h:i A') ?? '',
                'type' => 'stock_adjustment',
                'type_label' => $cancelled ? 'Stock Adjustment (Cancelled)' : 'Stock Adjustment',
                'badge' => $cancelled ? 'secondary' : 'danger',
                'reference' => $adjustment->reference_no,
                'stock_adjustment_id' => $adjustment->id,
                'quantity' => abs($delta),
                'quantity_label' => $sign.$this->formatQty(abs($delta)),
                'quantity_unit' => $unitName,
                'direction' => $delta >= 0 ? 'in' : 'out',
                'by' => $adjustment->user->name ?? 'N/A',
                'party' => $adjustment->reasonLabel(),
                'party_label' => 'Reason',
                'details' => $this->formatQty($adjustmentItem->previous_stock).' → '.$this->formatQty($adjustmentItem->new_stock).' '.$unitName
                    .($adjustmentItem->line_notes ? ' — '.$adjustmentItem->line_notes : '')
                    .($cancelled ? ' — adjustment cancelled, stock restored' : ''),
                'status' => $cancelled ? 'Cancelled' : 'Applied',
                'counts_toward_totals' => ! $cancelled,
            ]);
        }

        $movements = $movements->sortByDesc('sort_date')->values();

        return [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'brand' => $item->brand,
                'category' => $item->category?->name,
                'unit' => $unitName,
                'current_stock' => (float) $item->current_stock,
                'stock_display' => app(ItemStockDisplayService::class)->remainsDisplay($item),
            ],
            'movements' => $movements->map(fn (array $row) => collect($row)->except('sort_date')->all())->values()->all(),
            'stats' => [
                'total_received' => (float) $movements->where('type', 'stock_in')->where('counts_toward_totals', true)->sum('quantity'),
                'total_sold' => (float) $movements->where('type', 'sale')->where('counts_toward_totals', true)->sum('quantity'),
                'total_lost' => (float) $movements->where('type', 'stock_loss')->where('counts_toward_totals', true)->sum('quantity'),
                'current_stock' => (float) $item->current_stock,
            ],
        ];
    }

    private function stockItemPayload(array $item, bool $canViewValue): array
    {
        $payload = [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'] ?? '',
            'brand' => $item['brand'] ?? '',
            'category' => $item['category'],
            'category_slug' => $item['category_slug'],
            'business_type_key' => $item['business_type_key'],
            'business_type_label' => $item['business_type_label'] ?? null,
            'unit' => $item['unit'],
            'stock_pieces' => (float) $item['stock_pieces'],
            'stock_display' => $item['stock_display'],
            'formatted_quantity' => $item['formatted_quantity'],
            'stock_bulk_count' => $item['stock_bulk_count'] ?? null,
            'stock_bulk_name' => $item['stock_bulk_name'] ?? null,
            'has_bulk_stock' => (bool) ($item['has_bulk_stock'] ?? false),
            'packaging_breakdown' => $item['packaging_breakdown'] ?? [],
            'selling_price' => (float) ($item['selling_price'] ?? 0),
            'sell_per_piece' => (float) ($item['sell_per_piece'] ?? 0),
            'packaging_prices' => $item['packaging_prices'] ?? [],
            'has_multi_packaging' => (bool) ($item['has_multi_packaging'] ?? false),
            'cost_price' => (float) ($item['cost_price'] ?? 0),
            'has_price' => (bool) ($item['has_price'] ?? false),
            'is_low_stock' => (bool) ($item['is_low_stock'] ?? false),
            'status' => ($item['is_low_stock'] ?? false) ? 'low_stock' : 'in_stock',
            'status_color' => $item['status_color'] ?? 'success',
        ];

        if ($canViewValue) {
            $payload['expected_revenue'] = (float) ($item['expected_revenue'] ?? 0);
            $payload['expected_profit'] = (float) ($item['expected_profit'] ?? 0);
            $payload['holding_value'] = (float) ($item['holding_value'] ?? 0);
            $payload['cost_holding_value'] = (float) ($item['cost_holding_value'] ?? 0);
            $payload['margin_percent'] = (float) ($item['margin_percent'] ?? 0);
        }

        return $payload;
    }

    private function formatQty(float $qty): string
    {
        return fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : number_format($qty, 2);
    }
}
