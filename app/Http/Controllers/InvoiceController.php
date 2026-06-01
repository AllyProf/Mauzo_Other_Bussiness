<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Services\SaleStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['view_invoices', 'view_sales_history', 'process_sales']);

        $businessId = Auth::user()->business_id;
        $salesQuery = Sale::where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled')
            ->where('sale_source', 'invoice');

        // Invoices are business documents — staff see their own; owners see all.
        if (! $this->actsAsBusinessWideViewer()) {
            $salesQuery->where('user_id', Auth::id());
        }

        $stats = [
            'total' => (clone $salesQuery)->count(),
            'unpaid' => (clone $salesQuery)->whereIn('payment_status', ['pending', 'partial', 'debt'])->count(),
            'total_amount' => (float) (clone $salesQuery)->sum('total_amount'),
        ];

        $sales = (clone $salesQuery)
            ->with(['user', 'customer', 'items.item'])
            ->latest()
            ->paginate(20);

        $scopedToSelf = ! $this->actsAsBusinessWideViewer();

        $customers = Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $paymentMethods = Auth::user()->business->enabledPaymentMethods();

        return view('invoices.index', compact(
            'sales',
            'stats',
            'scopedToSelf',
            'customers',
            'paymentMethods',
        ));
    }

    public function create()
    {
        $this->authorizeAny(['create_invoices', 'process_sales']);

        $openShift = Shift::openForUser(Auth::id(), Auth::user()->business_id);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('warning', 'Complete a physical stock check and open your shift before creating invoices.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $stockService = app(SaleStockService::class);
        $stockContext = $stockService->shiftStockContext($openShift);
        $business = Auth::user()->business;
        $businessTypes = $business->posBusinessTypesMeta();

        $catalogItems = Item::where('business_id', $business->id)
            ->with(['packagings', 'category'])
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($openShift, $stockContext, $business, $stockService) {
                $available = $stockService->availableStockForShift($item, $openShift, $stockContext);
                $price = (float) (optional($item->packagings->first())->selling_price ?? 0);
                $typeKey = $item->category?->source_business_type_key ?: 'other';

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku ?? '',
                    'stock' => $available,
                    'price' => $price,
                    'business_type_key' => $typeKey,
                    'business_type_label' => $business->businessTypeLabel($typeKey),
                ];
            })
            ->filter(fn ($item) => $item['stock'] > 0 && $item['price'] > 0)
            ->values();

        $catalogGroups = $catalogItems
            ->groupBy('business_type_key')
            ->map(function ($items, $key) use ($business) {
                return [
                    'key' => $key,
                    'label' => $business->businessTypeLabel((string) $key),
                    'items' => $items->values()->all(),
                ];
            })
            ->values()
            ->all();

        $customers = Customer::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('invoices.create', compact(
            'catalogItems',
            'catalogGroups',
            'businessTypes',
            'customers',
            'openShift',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['create_invoices', 'process_sales']);

        $openShift = Shift::openForUser(Auth::id(), Auth::user()->business_id);
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
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', Auth::user()->business_id)],
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $activeItems = array_values(array_filter($request->items, fn ($i) => ($i['qty'] ?? 0) > 0));

            if (empty($activeItems)) {
                return redirect()->back()->with('error', 'Please add at least one item with quantity.')->withInput();
            }

            $stockContext = app(SaleStockService::class)->shiftStockContext($openShift);
        $stockService = app(SaleStockService::class);

            foreach ($activeItems as $i) {
                $item = Item::find($i['id']);
                $available = $this->availableStockForShift($item, $openShift, $stockContext);

                if ((float) $i['qty'] > $available) {
                    DB::rollBack();

                    return redirect()->back()
                        ->with('error', "Not enough stock for {$item->name}. Available: {$available}.")
                        ->withInput();
                }
            }

            $totalAmount = 0;
            foreach ($activeItems as $i) {
                $totalAmount += ($i['qty'] * $i['price']);
            }

            $ref = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $customerFields = $this->resolveCustomerFields($request);

            $sale = Sale::create([
                'business_id' => Auth::user()->business_id,
                'user_id' => Auth::id(),
                'shift_id' => $openShift?->id,
                'reference_no' => $ref,
                'sale_source' => 'invoice',
                'stock_deducted' => false,
                'sale_date' => $request->sale_date,
                'total_amount' => $totalAmount,
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
                $unitCost = (float) (optional($item?->packagings?->first())->cost_price ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $i['id'],
                    'quantity' => $i['qty'],
                    'unit_price' => $i['price'],
                    'list_unit_price' => $i['price'],
                    'cost_price' => $unitCost,
                    'subtotal' => $subtotal,
                ]);
            }

            DB::commit();
            $openShift?->refreshTotals();

            return redirect()
                ->route('invoices.show', $sale)
                ->with('success', "Invoice {$ref} created. Print the invoice for the customer, then record payment when they pay.");
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Sale $invoice)
    {
        $this->authorizeAny(['view_invoices', 'view_sales_history', 'process_sales']);

        if ($invoice->business_id != Auth::user()->business_id) {
            abort(403);
        }

        $this->ensureCanAccessStaffRecord((int) $invoice->user_id);

        if ($invoice->payment_status === 'cancelled') {
            return redirect()->route('invoices.index')->with('error', 'This invoice was cancelled.');
        }

        $invoice->load(['items.item', 'user', 'customer', 'payments.user', 'business']);

        $branch = active_branch_service()->activeBranch();

        $customers = Customer::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $paymentMethods = Auth::user()->business->enabledPaymentMethods();

        return view('invoices.show', [
            'sale' => $invoice,
            'business' => Auth::user()->business,
            'branch' => $branch,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'paymentReceiveDetails' => collect(Auth::user()->business->paymentMethodsConfig())
                ->filter(fn ($m) => ! empty($m['enabled']))
                ->flatMap(function ($method) {
                    return collect($method['provider_accounts'] ?? [])
                        ->filter(fn ($account) => ! empty($account['pay_number']) || ! empty($account['account_name']))
                        ->map(fn ($account) => [
                            'method_key' => $method['key'],
                            'method_label' => $method['label'],
                            'platform' => $account['name'],
                            'pay_number' => $account['pay_number'] ?? '',
                            'account_name' => $account['account_name'] ?? '',
                        ]);
                })
                ->values(),
        ]);
    }

    private function resolveCustomerFields(Request $request): array
    {
        $businessId = Auth::user()->business_id;
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
