<?php

namespace App\Http\Controllers;

use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Branch;
use App\Services\ItemPackagingNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivingController extends Controller
{
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('receive_stock');

        $businessId = Auth::user()->business_id;

        $query = Receiving::query()
            ->where('business_id', $businessId)
            ->with(['supplier', 'user', 'branch']);

        if (! $this->actsAsBusinessWideViewer()) {
            if (Auth::user()->branch_id) {
                $query->where('branch_id', Auth::user()->branch_id);
            }
        } elseif ($branchId = active_branch_id()) {
            $query->where('branch_id', $branchId);
        }

        $receivings = $query->latest()->get();

        return view('receivings.index', compact('receivings'));
    }

    public function create()
    {
        \Illuminate\Support\Facades\Gate::authorize('receive_stock');

        $business = Auth::user()->business;
        $businessTypes = $business->posBusinessTypesMeta();
        $suppliers = Supplier::where('business_id', $business->id)->get();
        $categories = \App\Models\Category::where('business_id', $business->id)->has('items')->get();

        // Build items grouped by category for the JS selector
        $itemsByCategory = \App\Models\Category::where('business_id', $business->id)
            ->has('items')
            ->with(['items.packagings.packagingType', 'items.receivingPackaging'])
            ->get()
            ->mapWithKeys(function ($cat) {
                return [$cat->id => $cat->items->map(function ($item) {
                    $packagings = $item->packagings->sortBy('quantity_per_unit')->values();
                    $receivingPkg = $packagings->firstWhere('packaging_id', $item->receiving_packaging_id)
                        ?? $packagings->sortByDesc('quantity_per_unit')->first();

                    return [
                        'id'            => $item->id,
                        'name'          => $item->name,
                        'sku'           => $item->sku ?? '',
                        'unit'          => optional($item->receivingPackaging)->name ?? 'Unit',
                        'units_per_receiving_pack' => (int) ($item->units_per_receiving_pack ?? 1),
                        'current_stock' => (float) $item->current_stock,
                        'cost_price'    => (float) (optional($receivingPkg)->cost_price ?? $packagings->first()?->cost_price ?? 0),
                        'selling_price' => (float) (optional($packagings->first())->selling_price ?? 0),
                        'packagings'    => $packagings->map(fn ($p) => [
                            'id'                => $p->id,
                            'name'              => $p->packagingType->name ?? 'Unit',
                            'quantity_per_unit' => (int) $p->quantity_per_unit,
                            'cost_price'        => (float) $p->cost_price,
                            'selling_price'     => (float) $p->selling_price,
                        ])->values()->all(),
                    ];
                })];
            });

        $branches = Branch::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultBranchId = active_branch_id()
            ?? Auth::user()->branch_id
            ?? $branches->firstWhere('is_default', true)?->id
            ?? $branches->first()?->id;

        return view('receivings.create', compact('suppliers', 'categories', 'itemsByCategory', 'businessTypes', 'branches', 'defaultBranchId'));
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('receive_stock');

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'received_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
            'items.*.cost_mode' => 'nullable|in:pkg,unit',
            'items.*.selling' => 'nullable|numeric|min:0',
            'items.*.selling_prices' => 'nullable|array',
            'items.*.selling_prices.*' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:fixed,percent',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Generate unique reference number
            $ref = 'RCV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Filter items with qty > 0 only
            $activeItems = array_filter($request->items, fn($i) => ($i['qty'] ?? 0) > 0);

            if (empty($activeItems)) {
                return redirect()->back()->with('error', 'Please enter at least one item with quantity > 0.')->withInput();
            }

            foreach ($activeItems as $i) {
                $item = Item::with('packagings.packagingType')->find($i['id']);
                if ($priceError = $this->validateRetailAgainstBuying($item, $i)) {
                    return redirect()->back()->with('error', $priceError)->withInput();
                }
            }

            $total_amount = 0;
            foreach ($activeItems as $i) {
                $itemForTotal = Item::find($i['id']);
                $unitsPerReceiving = max(1, (int) ($itemForTotal->units_per_receiving_pack ?? 1));
                $costMode = ($i['cost_mode'] ?? 'pkg') === 'unit' ? 'unit' : 'pkg';
                $pieces = $i['qty'] * $unitsPerReceiving;
                $subtotal = $costMode === 'unit'
                    ? $pieces * $i['cost']
                    : $i['qty'] * $i['cost'];
                if (!empty($i['discount_type']) && !empty($i['discount_value'])) {
                    if ($i['discount_type'] === 'percent') {
                        $subtotal -= $subtotal * ($i['discount_value'] / 100);
                    } else {
                        $subtotal -= $i['discount_value'];
                    }
                }
                $total_amount += max(0, $subtotal);
            }

            $receiving = Receiving::create([
                'business_id' => Auth::user()->business_id,
                'branch_id' => $this->resolveReceivingBranchId($request),
                'supplier_id' => $request->supplier_id,
                'user_id' => Auth::id(),
                'reference_no' => $ref,
                'received_date' => $request->received_date,
                'total_amount' => $total_amount,
                'notes' => $request->notes,
                'status' => 'completed',
            ]);

            foreach ($activeItems as $i) {
                $item = Item::with('packagings.packagingType')->find($i['id']);
                $unitsPerReceiving = max(1, (int) ($item->units_per_receiving_pack ?? 1));
                $costMode = ($i['cost_mode'] ?? 'pkg') === 'unit' ? 'unit' : 'pkg';
                $pieces = $i['qty'] * $unitsPerReceiving;
                $lineGross = $costMode === 'unit'
                    ? $pieces * $i['cost']
                    : $i['qty'] * $i['cost'];

                $discountAmount = 0;
                if (!empty($i['discount_type']) && !empty($i['discount_value'])) {
                    if ($i['discount_type'] === 'percent') {
                        $discountAmount = $lineGross * ($i['discount_value'] / 100);
                    } else {
                        $discountAmount = $i['discount_value'];
                    }
                }

                $sellingPrices = $i['selling_prices'] ?? [];

                ReceivingItem::create([
                    'receiving_id' => $receiving->id,
                    'item_id' => $i['id'],
                    'quantity' => $i['qty'],
                    'cost_price' => $i['cost'],
                    'selling_price' => $this->resolvePerPieceSellingPrice($item, $i),
                    'selling_prices_snapshot' => ! empty($sellingPrices) ? $sellingPrices : null,
                    'discount_type' => $i['discount_type'] ?? null,
                    'discount_value' => $i['discount_value'] ?? 0,
                    'discount_amount' => $discountAmount,
                ]);

                // Update Item Stock & Pricing
                $item->current_stock += ($i['qty'] * $unitsPerReceiving);
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
            return redirect()->route('receivings.index')->with('success', "Stock-in record created successfully ($ref).");

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Receiving $receiving)
    {
        \Illuminate\Support\Facades\Gate::authorize('receive_stock');

        if ($receiving->business_id != Auth::user()->business_id) {
            abort(403);
        }
        $receiving->load(['items.item.packagings.packagingType', 'items.item.receivingPackaging', 'supplier', 'user', 'branch']);

        $lineMetrics = $receiving->items->mapWithKeys(function ($line) {
            return [$line->id => $this->buildReceivingLineMetrics($line)];
        });

        return view('receivings.show', compact('receiving', 'lineMetrics'));
    }

    public function cancel(Request $request, Receiving $receiving)
    {
        $this->authorizeAny(['cancel_receiving', 'receive_stock']);

        if ($receiving->business_id != Auth::user()->business_id) {
            abort(403);
        }

        if ($receiving->status === 'cancelled') {
            return redirect()->back()->with('error', 'This receiving has already been cancelled.');
        }

        $receiving->load(['items.item.packagings']);
        $plan = $this->buildReversalPlan($receiving);

        if ($plan['requires_partial'] && ! $request->boolean('partial_ok')) {
            return redirect()->back()->with('partial_cancel_prompt', [
                'receiving_id' => $receiving->id,
                'reference_no' => $receiving->reference_no,
                'items' => collect($plan['items'])->map(fn ($row) => [
                    'name' => $row['item']->name,
                    'added' => $row['stock_to_remove'],
                    'reversible' => $row['reversible'],
                    'not_reversible' => $row['not_reversible'],
                ])->values()->all(),
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
                    $soldNotes[] = "{$item->name}: " . $this->formatQty($row['not_reversible']) . ' already sold';
                }
            }

            $notes = $receiving->notes ?? '';
            if ($plan['requires_partial']) {
                $notes = trim($notes . ' [Partially cancelled on ' . now()->format('Y-m-d H:i') . '. Some stock was already sold.]');
            }

            $receiving->update([
                'status' => 'cancelled',
                'notes' => $notes ?: null,
            ]);

            DB::commit();

            $message = "Receiving ({$receiving->reference_no}) has been cancelled.";
            if (! empty($soldNotes)) {
                $message .= ' ' . implode('; ', $soldNotes) . '.';
            } else {
                $message .= ' Stock has been reversed.';
            }

            return redirect()->route('receivings.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Error cancelling receiving: ' . $e->getMessage());
        }
    }

    private function resolveReceivingBranchId(Request $request): ?int
    {
        $businessId = Auth::user()->business_id;

        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            return (int) Auth::user()->branch_id;
        }

        if ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;

            Branch::query()
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->where('id', $branchId)
                ->firstOrFail();

            return $branchId;
        }

        if ($branchId = active_branch_id()) {
            return $branchId;
        }

        if ($branchId = Auth::user()->branch_id) {
            return (int) $branchId;
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
        $qty = max(1, (int) ($line['qty'] ?? 0));
        $cost = (float) ($line['cost'] ?? 0);
        $costMode = ($line['cost_mode'] ?? 'pkg') === 'unit' ? 'unit' : 'pkg';
        $pieces = $qty * $unitsPerReceiving;

        $gross = $costMode === 'unit' ? $pieces * $cost : $qty * $cost;
        $discountAmount = 0.0;

        if (! empty($line['discount_type']) && ! empty($line['discount_value'])) {
            if ($line['discount_type'] === 'percent') {
                $discountAmount = $gross * ((float) $line['discount_value'] / 100);
            } else {
                $discountAmount = (float) $line['discount_value'];
            }
        }

        $buyPerPiece = ($pieces > 0 ? max(0, $gross - $discountAmount) / $pieces : (
            $costMode === 'unit' ? $cost : $cost / $unitsPerReceiving
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
            $stockToRemove = $receivingItem->quantity * $unitsPerPack;
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
        $normalizer = app(ItemPackagingNormalizer::class);
        $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
        $normalized = $normalizer->normalizeItemPackagings($item, $packagingModels);
        $unitsPerReceiving = $normalizer->effectiveUnitsPerReceivingPack($item, $packagingModels);
        $totalPieces = $line->quantity * $unitsPerReceiving;
        $receivingUnit = optional($item->receivingPackaging)->name ?? 'Unit';

        $grossCost = $line->quantity * $line->cost_price;
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

        $quantityLabel = $unitsPerReceiving > 1
            ? "{$line->quantity} {$receivingUnit} ({$totalPieces} pcs)"
            : (string) $line->quantity;

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
}
