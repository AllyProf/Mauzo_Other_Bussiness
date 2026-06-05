<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\Item;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Services\ItemPackagingNormalizer;
use App\Services\SaleStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['view_sales_history', 'process_sales']);
        $businessId = $this->currentBusinessId();
        $requiresOpenShift = Auth::user()->requiresOpenShift();
        $openShift = Shift::openForUser(Auth::id(), $businessId);

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        $business = $this->currentBusiness();
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

        $salesQuery = Sale::where('business_id', $businessId);

        if ($requiresOpenShift) {
            if ($openShift) {
                $salesQuery->where('shift_id', $openShift->id);
            } else {
                $salesQuery->whereRaw('1 = 0');
            }
        } elseif ($this->actsAsBusinessWideViewer()) {
            if ($branchFilterId) {
                $salesQuery->whereHas('items.item.category', function ($query) use ($branchFilterId) {
                    $query->where('branch_id', $branchFilterId);
                });
            }
        } else {
            $salesQuery->where('user_id', Auth::id());
        }

        $activeSales = (clone $salesQuery)->where('payment_status', '!=', 'cancelled');

        $stats = [
            'total_sales' => (clone $activeSales)->count(),
            'gross_sales' => (float) (clone $activeSales)->sum('total_amount'),
            'collected' => (float) (clone $activeSales)->sum('amount_paid'),
            'outstanding' => (float) (clone $salesQuery)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
                ->sum(DB::raw('total_amount - amount_paid')),
        ];

        $sales = (clone $salesQuery)
            ->with(['user', 'items.item.category', 'items.itemPackaging.packagingType', 'items.service', 'customer'])
            ->latest()
            ->paginate(15);

        $customers = $this->activeCustomers();
        $paymentMethods = $business->enabledPaymentMethods();

        $scopedToSelf = $requiresOpenShift || ! $this->actsAsBusinessWideViewer();
        $shiftContext = $requiresOpenShift
            ? ($openShift ? 'current' : 'none')
            : ($scopedToSelf ? 'self' : 'all');

        return view('sales.index', compact(
            'sales',
            'stats',
            'scopedToSelf',
            'openShift',
            'requiresOpenShift',
            'shiftContext',
            'customers',
            'paymentMethods',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
            'businessTypes',
            'multiBusiness',
        ));
    }

    public function create()
    {
        $this->authorizeAny(['process_sales']);

        $businessId = $this->currentBusinessId();
        $openShift = Shift::openForUser(Auth::id(), $businessId);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('warning', 'Complete a physical stock check and open your shift before selling.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        // POS Screen
        $business = $this->currentBusiness();

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

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
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? Auth::user()->branch?->name ?? 'Branch')
            : null;
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;

        $categoriesQuery = Category::where('business_id', $business->id)->has('items');
        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }
        if (! $this->actsAsBusinessWideViewer() && ($typeKeys = Auth::user()->assignedBusinessTypeKeys()) !== []) {
            $categoriesQuery->whereIn('source_business_type_key', $typeKeys);
        }
        $categories = $categoriesQuery->get();

        $stockContext = app(SaleStockService::class)->shiftStockContext($openShift);

        $itemsByCategory = Category::where('business_id', $business->id)
            ->has('items')
            ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
            ->when(! $this->actsAsBusinessWideViewer() && ($typeKeys = Auth::user()->assignedBusinessTypeKeys()) !== [], fn ($query) => $query->whereIn('source_business_type_key', $typeKeys))
            ->with(['items.packagings.packagingType'])
            ->get()
            ->mapWithKeys(function ($cat) use ($openShift, $stockContext) {
                $typeKey = $cat->source_business_type_key ?: 'other';
                $stockService = app(SaleStockService::class);
                $items = $cat->items->map(function ($item) use ($openShift, $stockContext, $typeKey, $stockService) {
                    $available = $stockService->availableStockForShift($item, $openShift, $stockContext);
                    if ($available <= 0) {
                        return null;
                    }

                    $normalizer = app(ItemPackagingNormalizer::class);
                    $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
                    $normalized = $normalizer->normalizeItemPackagings($item, $packagingModels);
                    $defaultRow = $normalized->first(function ($row) {
                        return (float) ($row['packaging']->selling_price ?? 0) > 0
                            && (int) $row['quantity_per_unit'] === 1;
                    }) ?? $normalized->first(function ($row) {
                        return (float) ($row['packaging']->selling_price ?? 0) > 0;
                    }) ?? $normalized->firstWhere('quantity_per_unit', 1)
                        ?? $normalized->first();
                    $defaultPackaging = $defaultRow['packaging'] ?? null;

                    return [
                        'id'            => $item->id,
                        'name'          => $item->name,
                        'sku'           => $item->sku ?? '',
                        'stock'         => $available,
                        'stock_pieces'  => $available,
                        'stock_unit'    => 'pcs',
                        'selling_price' => (float) (optional($defaultPackaging)->selling_price ?? 0),
                        'default_packaging_id' => $defaultPackaging?->id,
                        'packagings'    => $normalized->map(function ($row) use ($available) {
                            $p = $row['packaging'];
                            $qpu = (int) $row['quantity_per_unit'];

                            return [
                                'id' => $p->id,
                                'name' => $p->packagingType->name ?? 'Unit',
                                'quantity_per_unit' => $qpu,
                                'selling_price' => (float) $p->selling_price,
                                'max_qty' => (int) floor($available / max(1, $qpu)),
                            ];
                        })->values()->all(),
                        'businessTypeKey' => $typeKey,
                    ];
                })->filter()->values();

                return [$cat->id => $items];
            });

        $customers = $this->activeCustomers();

        return view('sales.create', compact(
            'categories',
            'itemsByCategory',
            'openShift',
            'customers',
            'businessTypes',
            'multiBusiness',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['process_sales']);

        $businessId = $this->currentBusinessId();
        $openShift = Shift::openForUser(Auth::id(), $businessId);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('error', 'Your shift is not open. Complete stock check first.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $request->validate([
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.item_packaging_id' => 'nullable|exists:item_packagings,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.01',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        try {
            $ref = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $activeItems = array_filter($request->items, fn($i) => ($i['qty'] ?? 0) > 0);

            if (empty($activeItems)) {
                return redirect()->back()->with('error', 'Please enter at least one item with quantity > 0.')->withInput();
            }

            $total_amount = 0;
            foreach ($activeItems as $i) {
                $total_amount += ($i['qty'] * $i['price']);
            }

            $stockContext = app(SaleStockService::class)->shiftStockContext($openShift);
            $stockService = app(SaleStockService::class);

            foreach ($activeItems as $i) {
                $item = Item::with('packagings')->find($i['id']);
                $packaging = ! empty($i['item_packaging_id'])
                    ? $item->packagings->firstWhere('id', (int) $i['item_packaging_id'])
                    : $item->packagings->sortBy('quantity_per_unit')->first();
                $stockNeeded = $item->stockUnitsForPackaging((int) $i['qty'], $packaging);
                $available = $stockService->availableStockForShift($item, $openShift, $stockContext);

                if ($stockNeeded > $available) {
                    DB::rollBack();

                    $unitLabel = $packaging?->packagingType?->name ?? 'unit';

                    return redirect()->back()
                        ->with('error', "Not enough stock for {$item->name} ({$unitLabel}). Available: {$available} pieces.")
                        ->withInput();
                }
            }

            $customerFields = $this->resolveCustomerFields($request);

            $sale = Sale::create([
                'business_id' => $businessId,
                'user_id' => Auth::id(),
                'shift_id' => $openShift?->id,
                'reference_no' => $ref,
                'sale_source' => 'pos',
                'stock_deducted' => false,
                'sale_date' => $request->sale_date,
                'total_amount' => $total_amount,
                'amount_paid' => 0,
                'payment_status' => 'pending',
                'customer_id' => $customerFields['customer_id'],
                'customer_name' => $customerFields['customer_name'],
                'customer_phone' => $customerFields['customer_phone'],
                'notes' => $request->notes,
            ]);

            foreach ($activeItems as $i) {
                $subtotal = $i['qty'] * $i['price'];
                $item = Item::with('packagings')->find($i['id']);
                $packaging = ! empty($i['item_packaging_id'])
                    ? $item->packagings->firstWhere('id', (int) $i['item_packaging_id'])
                    : $item->packagings->sortBy('quantity_per_unit')->first();
                $unitCost = (float) (optional($packaging)->cost_price ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $i['id'],
                    'item_packaging_id' => $packaging?->id,
                    'quantity' => $i['qty'],
                    'unit_price' => $i['price'],
                    'list_unit_price' => $i['price'],
                    'cost_price' => $unitCost,
                    'subtotal' => $subtotal,
                ]);

                if ($packaging && (float) $packaging->selling_price <= 0 && (float) $i['price'] > 0) {
                    $packaging->update(['selling_price' => (float) $i['price']]);
                }
            }

            app(SaleStockService::class)->deductForSale($sale->fresh());

            DB::commit();
            $openShift?->refreshTotals();

            return redirect()->route('sales.index')->with('success', "Order placed successfully ($ref). Please process payment.");

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Sale $sale)
    {
        $this->authorizeAny(['view_sales_history', 'process_sales']);
        if ($sale->business_id != $this->currentBusinessId()) {
            abort(403);
        }
        $this->ensureCanAccessStaffRecord((int) $sale->user_id);
        $sale->load(['items.item', 'items.service', 'user', 'payments.user']);
        return view('sales.show', compact('sale'));
    }

    public function pay(Request $request, Sale $sale)
    {
        $this->authorizeAny(['collect_invoice_payments', 'collect_payments', 'process_sales']);
        $business = $this->currentBusiness();
        $businessId = $this->currentBusinessId();

        if ($sale->business_id != $businessId) {
            abort(403);
        }
        $this->ensureCanAccessStaffRecord((int) $sale->user_id);

        if (in_array($sale->payment_status, ['paid', 'cancelled'])) {
            return redirect()->back()->with('error', 'This sale is already fully paid or cancelled.');
        }

        $balanceDue = (float) $sale->total_amount - (float) $sale->amount_paid;

        if ($balanceDue <= 0) {
            return redirect()->back()->with('error', 'This sale has no balance remaining.');
        }

        if ($request->filled('line_items')) {
            $request->validate([
                'line_items' => 'required|array|min:1',
                'line_items.*.id' => 'required|integer|exists:sale_items,id',
                'line_items.*.adjustment_mode' => 'required|string|in:price,discount',
                'line_items.*.unit_price' => 'required|numeric|min:0',
                'line_items.*.discount_type' => 'nullable|string|in:fixed,percent',
                'line_items.*.discount_value' => 'nullable|numeric|min:0',
            ]);

            try {
                $this->applySaleLineAdjustments($sale, $request->line_items);
                $sale->refresh();
            } catch (\InvalidArgumentException $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }

            $balanceDue = (float) $sale->total_amount - (float) $sale->amount_paid;

            if ($balanceDue <= 0) {
                return redirect()->back()->with('success', 'Order total updated. This sale is already fully covered by previous payments.');
            }
        }

        $request->validate([
            'payment_method' => ['required', 'string', Rule::in($business->enabledPaymentMethodKeys())],
        ]);

        $method = $business->findPaymentMethod($request->payment_method);

        if (($method['type'] ?? '') === 'credit') {
            $request->validate([
                'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'due_date' => 'required|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $status = $sale->amount_paid > 0 ? 'partial' : 'debt';
            $customerFields = $this->resolveCustomerFields($request);

            $updateData = [
                'payment_status' => $status,
                'customer_id' => $customerFields['customer_id'],
                'customer_name' => $customerFields['customer_name'],
                'customer_phone' => $customerFields['customer_phone'],
                'due_date' => $request->due_date,
            ];

            if ($sale->due_date != $request->due_date) {
                $updateData['debt_due_soon_sms_sent_at'] = null;
                $updateData['debt_due_soon_second_sms_sent_at'] = null;
                $updateData['debt_due_today_sms_sent_at'] = null;
                $updateData['debt_overdue_sms_sent_at'] = null;
            }

            if ($request->filled('notes')) {
                $updateData['notes'] = $this->appendSaleNote($sale, $request->notes);
            }

            $sale->update($updateData);

            $message = $sale->amount_paid > 0
                ? 'Remaining balance of '.money($balanceDue).' will be paid by '.$request->due_date.'.'
                : 'Sale saved as credit. Customer owes '.money($balanceDue).' — due '.$request->due_date.'.';

            return redirect()->back()->with('success', $message);
        }

        $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
        ]);

        $amountToPay = min((float) $request->amount_paid, $balanceDue);
        $newAmountPaid = (float) $sale->amount_paid + $amountToPay;
        $willBePartial = $newAmountPaid < (float) $sale->total_amount;

        if ($willBePartial) {
            $request->validate([
                'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'due_date' => 'required|date',
                'notes' => 'required|string|max:1000',
            ]);
        }

        if (! empty($method['requires_reference'])) {
            $request->validate([
                'payment_provider' => 'required|string|max:255',
                'transaction_reference' => 'required|string|max:255',
            ]);
        }

        DB::beginTransaction();
        try {
            SalePayment::create([
                'sale_id' => $sale->id,
                'user_id' => Auth::id(),
                'amount' => $amountToPay,
                'payment_method' => $request->payment_method,
                'payment_provider' => $request->payment_provider,
                'transaction_reference' => $request->transaction_reference,
            ]);

            $newAmountPaid = (float) $sale->amount_paid + $amountToPay;
            $status = ($newAmountPaid >= (float) $sale->total_amount) ? 'paid' : 'partial';

            $updateData = [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $status,
                'payment_method' => $request->payment_method,
            ];

            if ($willBePartial) {
                $customerFields = $this->resolveCustomerFields($request);
                $updateData['customer_id'] = $customerFields['customer_id'];
                $updateData['customer_name'] = $customerFields['customer_name'];
                $updateData['customer_phone'] = $customerFields['customer_phone'];
                $updateData['due_date'] = $request->due_date;
                $updateData['notes'] = $this->appendSaleNote($sale, $request->notes);
                if ($sale->due_date != $request->due_date) {
                    $updateData['debt_due_soon_sms_sent_at'] = null;
                    $updateData['debt_due_soon_second_sms_sent_at'] = null;
                    $updateData['debt_due_today_sms_sent_at'] = null;
                    $updateData['debt_overdue_sms_sent_at'] = null;
                }
            } elseif ($status === 'paid') {
                $updateData['due_date'] = null;
                if ($request->filled('customer_id')) {
                    $customerFields = $this->resolveCustomerFields($request);
                    $updateData['customer_id'] = $customerFields['customer_id'];
                    $updateData['customer_name'] = $customerFields['customer_name'];
                    $updateData['customer_phone'] = $customerFields['customer_phone'];
                }
            }

            $sale->update($updateData);

            $sale->refresh();
            app(SaleStockService::class)->assertInvoiceStockAvailable(
                $sale,
                $sale->shift_id ? Shift::find($sale->shift_id) : null
            );
            app(SaleStockService::class)->deductInvoiceIfPaid($sale);

            DB::commit();

            if ($sale->shift_id) {
                Shift::find($sale->shift_id)?->refreshTotals();
            }

            $message = 'Payment of '.money($amountToPay).' recorded successfully.';
            if ($status === 'partial') {
                $remaining = (float) $sale->total_amount - $newAmountPaid;
                $message .= ' Balance remaining: '.money($remaining).' — due '.$request->due_date.'.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Error processing payment: ' . $e->getMessage());
        }
    }

    public function cancel(Sale $sale)
    {
        $this->authorizeAny(['cancel_sales', 'process_sales']);
        if ($sale->business_id != $this->currentBusinessId()) {
            abort(403);
        }
        $this->ensureCanAccessStaffRecord((int) $sale->user_id);

        if (in_array($sale->payment_status, ['paid', 'cancelled'])) {
            return redirect()->back()->with('error', 'Cannot cancel a paid or already cancelled sale.');
        }

        DB::beginTransaction();
        try {
            app(SaleStockService::class)->restoreForSale($sale);

            $sale->update([
                'payment_status' => 'cancelled',
            ]);

            DB::commit();

            if ($sale->shift_id) {
                Shift::find($sale->shift_id)?->refreshTotals();
            }

            return redirect()->route('sales.index')->with('success', "Sale ($sale->reference_no) has been cancelled and stock restored.");
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Error cancelling sale: ' . $e->getMessage());
        }
    }

    private function appendSaleNote(Sale $sale, string $note): string
    {
        $noteLine = now()->format('Y-m-d H:i') . ': ' . trim($note);

        return trim(($sale->notes ? $sale->notes . "\n" : '') . $noteLine);
    }

    private function applySaleLineAdjustments(Sale $sale, array $lineItems): void
    {
        $sale->load('items');
        $submittedIds = collect($lineItems)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($submittedIds) !== $sale->items->count()) {
            throw new \InvalidArgumentException('All order lines must be included when adjusting prices.');
        }

        $newTotal = 0;

        foreach ($lineItems as $line) {
            $saleItem = $sale->items->firstWhere('id', (int) $line['id']);

            if (! $saleItem) {
                throw new \InvalidArgumentException('Invalid order line selected.');
            }

            $qty = (float) $saleItem->quantity;
            $listUnitPrice = (float) ($saleItem->list_unit_price ?? $saleItem->unit_price);
            $mode = $line['adjustment_mode'] ?? 'price';

            if ($mode === 'discount') {
                $discType = $line['discount_type'] ?? '';
                $discVal = (float) ($line['discount_value'] ?? 0);
                $gross = $qty * $listUnitPrice;

                if ($discType === 'percent') {
                    $discount = $gross * ($discVal / 100);
                } elseif ($discType === 'fixed') {
                    $discount = $discVal;
                } else {
                    $discount = 0;
                }

                $subtotal = max(0, $gross - $discount);
                $unitPrice = $qty > 0 ? round($subtotal / $qty, 2) : 0;

                $saleItem->update([
                    'unit_price' => $unitPrice,
                    'list_unit_price' => $listUnitPrice,
                    'subtotal' => $subtotal,
                    'adjustment_mode' => $discount > 0 ? 'discount' : null,
                    'discount_type' => $discount > 0 ? $discType : null,
                    'discount_value' => $discount > 0 ? $discVal : 0,
                    'discount_amount' => $discount,
                ]);
            } else {
                $unitPrice = (float) ($line['unit_price'] ?? $listUnitPrice);
                $subtotal = $qty * $unitPrice;
                $isCustomPrice = abs($unitPrice - $listUnitPrice) > 0.001;

                $saleItem->update([
                    'unit_price' => $unitPrice,
                    'list_unit_price' => $listUnitPrice,
                    'subtotal' => $subtotal,
                    'adjustment_mode' => $isCustomPrice ? 'price' : null,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                ]);
            }

            $newTotal += (float) $saleItem->fresh()->subtotal;
        }

        if ($newTotal < (float) $sale->amount_paid) {
            throw new \InvalidArgumentException('Revised total cannot be less than the amount already paid.');
        }

        $sale->update(['total_amount' => $newTotal]);

        if ($sale->shift_id) {
            Shift::find($sale->shift_id)?->refreshTotals();
        }
    }

    private function activeCustomers()
    {
        return Customer::where('business_id', $this->currentBusinessId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);
    }

    private function resolveCustomerFields(Request $request): array
    {
        $businessId = $this->currentBusinessId();
        $customerId = $request->input('customer_id');

        if ($customerId) {
            $customer = Customer::where('business_id', $businessId)
                ->where('id', $customerId)
                ->where('is_active', true)
                ->first();

            if ($customer) {
                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                ];
            }
        }

        $phone = Customer::normalizePhone($request->customer_phone);

        return [
            'customer_id' => null,
            'customer_name' => $request->customer_name,
            'customer_phone' => $phone ?: $request->customer_phone,
        ];
    }
}
