<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use App\Models\Item;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Services\ItemPackagingNormalizer;
use App\Services\SaleStockService;
use App\Models\User;
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

        $business = $this->requireCurrentBusiness();
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
        $carriedOverUnpaidCount = 0;

        $saleSourceFilter = request()->query('source');
        if (! in_array($saleSourceFilter, ['products', 'services'], true)) {
            $saleSourceFilter = 'all';
        }

        if ($saleSourceFilter === 'services') {
            $salesQuery->where(function ($q) {
                $q->whereIn('sale_source', ['service_pos', 'service_invoice'])
                    ->orWhereHas('items', fn ($items) => $items->whereNotNull('service_id'));
            });
        } elseif ($saleSourceFilter === 'products') {
            $salesQuery->where(function ($q) {
                $q->whereNull('sale_source')
                    ->orWhereNotIn('sale_source', ['service_pos', 'service_invoice']);
            });
        }

        $search = trim((string) request()->query('search', request()->query('q', '')));
        $status = request()->query('status', request()->query('payment_status'));
        $paymentMethodFilter = request()->query('payment_method');
        $cashierIdFilter = request()->query('cashier_id', request()->query('user_id'));

        $dateFrom = request()->query('date_from');
        $dateTo = request()->query('date_to');
        $period = request()->query('period');

        if ($period) {
            switch ($period) {
                case 'today':
                    $dateFrom = date('Y-m-d');
                    $dateTo = date('Y-m-d');
                    break;
                case 'yesterday':
                    $dateFrom = date('Y-m-d', strtotime('-1 day'));
                    $dateTo = date('Y-m-d', strtotime('-1 day'));
                    break;
                case 'this_week':
                    $dateFrom = date('Y-m-d', strtotime('monday this week'));
                    $dateTo = date('Y-m-d', strtotime('sunday this week'));
                    break;
                case 'last_week':
                    $dateFrom = date('Y-m-d', strtotime('monday last week'));
                    $dateTo = date('Y-m-d', strtotime('sunday last week'));
                    break;
                case 'this_month':
                    $dateFrom = date('Y-m-01');
                    $dateTo = date('Y-m-t');
                    break;
                case 'last_month':
                    $dateFrom = date('Y-m-01', strtotime('last month'));
                    $dateTo = date('Y-m-t', strtotime('last month'));
                    break;
            }
        }

        $hasActiveFilter = $search !== ''
            || ($status && $status !== 'all')
            || ($paymentMethodFilter && $paymentMethodFilter !== 'all')
            || ($cashierIdFilter && $cashierIdFilter !== 'all')
            || $dateFrom
            || $dateTo
            || $period;

        $showAllHistory = request()->query('history') === 'all' || $hasActiveFilter;

        if ($requiresOpenShift) {
            if ($showAllHistory || !$openShift) {
                $salesQuery->where('user_id', Auth::id());
                $showAllHistory = true;
            } else {
                $carriedOverUnpaidCount = $this->countCarriedOverUnpaidSales($businessId, (int) Auth::id(), (int) $openShift->id);

                $salesQuery->where(function ($query) use ($openShift) {
                    $query->where('shift_id', $openShift->id)
                        ->orWhere(function ($unpaid) use ($openShift) {
                            $this->scopeCarriedOverUnpaidSales($unpaid, (int) Auth::id(), (int) $openShift->id);
                        });
                });
            }
        } elseif ($this->actsAsBusinessWideViewer()) {
            if ($branchFilterId) {
                $salesQuery->where(function ($query) use ($branchFilterId) {
                    $query->whereHas('items.item.category', function ($q) use ($branchFilterId) {
                        $q->where('branch_id', $branchFilterId);
                    })->orWhereHas('items.service', function ($q) use ($branchFilterId) {
                        $q->where('branch_id', $branchFilterId);
                    });
                });
            }
        } else {
            $salesQuery->where('user_id', Auth::id());
        }

        if ($cashierIdFilter && $cashierIdFilter !== 'all') {
            $salesQuery->where('user_id', (int) $cashierIdFilter);
        }

        if ($status && in_array($status, ['paid', 'partial', 'debt', 'pending', 'cancelled'], true)) {
            $salesQuery->where('payment_status', $status);
        }

        if ($paymentMethodFilter && $paymentMethodFilter !== 'all') {
            $salesQuery->where('payment_method', $paymentMethodFilter);
        }

        if ($search !== '') {
            $salesQuery->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('items.item', fn ($i) => $i->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%"))
                    ->orWhereHas('items.service', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        if ($dateFrom) {
            $salesQuery->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('sale_date', '<=', $dateTo);
        }

        $activeSales = (clone $salesQuery)->where('payment_status', '!=', 'cancelled');

        $shiftSalesQuery = ($requiresOpenShift && $openShift && !$showAllHistory)
            ? Sale::where('business_id', $businessId)->where('shift_id', $openShift->id)
            : $activeSales;

        $stats = [
            'total_sales' => (clone $shiftSalesQuery)->where('payment_status', '!=', 'cancelled')->count(),
            'gross_sales' => (float) (clone $shiftSalesQuery)->where('payment_status', '!=', 'cancelled')->sum('total_amount'),
            'collected' => (float) (clone $shiftSalesQuery)->where('payment_status', '!=', 'cancelled')->sum('amount_paid'),
            'outstanding' => (float) (clone $salesQuery)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
                ->sum(DB::raw('total_amount - amount_paid')),
        ];

        $sales = (clone $salesQuery)
            ->with(['user', 'items.item.category', 'items.itemPackaging.packagingType', 'items.service.category', 'customer'])
            ->latest('id')
            ->paginate(15);

        $customers = $this->activeCustomers();
        $paymentMethods = $business->enabledPaymentMethods();
        $cashiers = User::where('business_id', $businessId)->select('id', 'name')->orderBy('name')->get();

        $scopedToSelf = $requiresOpenShift || ! $this->actsAsBusinessWideViewer();
        $shiftContext = $requiresOpenShift
            ? ($openShift ? 'current' : 'none')
            : ($scopedToSelf ? 'self' : 'all');

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'html_table' => view('sales.partials.sale-table-rows', compact('sales', 'openShift', 'shiftContext', 'showAllHistory'))->render(),
                'html_mobile' => view('sales.partials.sale-mobile-list', compact('sales', 'openShift', 'shiftContext'))->render(),
                'pagination' => $sales->appends(request()->query())->links('pagination::bootstrap-4')->render(),
                'stats' => [
                    'total_sales' => number_format($stats['total_sales']),
                    'gross_sales' => 'TZS ' . number_format($stats['gross_sales'], 0),
                    'collected' => 'TZS ' . number_format($stats['collected'], 0),
                    'outstanding' => 'TZS ' . number_format($stats['outstanding'], 0),
                ],
                'total' => $sales->total(),
            ]);
        }

        return view('sales.index', compact(
            'sales',
            'stats',
            'scopedToSelf',
            'openShift',
            'requiresOpenShift',
            'shiftContext',
            'carriedOverUnpaidCount',
            'customers',
            'paymentMethods',
            'cashiers',
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
            'businessTypes',
            'multiBusiness',
            'showAllHistory',
            'dateFrom',
            'dateTo',
            'period',
            'saleSourceFilter',
            'search',
            'status',
            'paymentMethodFilter',
            'cashierIdFilter'
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

        $business = $this->requireCurrentBusiness();
        $retailEnabled = $business->isRetailEnabled();
        $servicesEnabled = $business->servicesMenuVisible();

        if (! $retailEnabled && ! $servicesEnabled) {
            return redirect()->route('home')
                ->with('warning', 'Neither retail nor services POS is enabled for this business.');
        }

        $branchFilterId = null;
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            $branchFilterId = (int) Auth::user()->branch_id;
        } elseif ($branchId = active_branch_id()) {
            $branchFilterId = $branchId;
        }

        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? Auth::user()->branch?->name ?? 'Branch')
            : null;
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $customers = $this->activeCustomers();

        $categories = collect();
        $itemsByCategory = collect();
        $businessTypes = [];
        $multiBusiness = false;

        if ($retailEnabled) {
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

            $categoryRecords = Category::where('business_id', $business->id)
                ->has('items')
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->when(! $this->actsAsBusinessWideViewer() && ($typeKeys = Auth::user()->assignedBusinessTypeKeys()) !== [], fn ($query) => $query->whereIn('source_business_type_key', $typeKeys))
                ->with(['items.packagings.packagingType'])
                ->orderBy('name')
                ->get();

            $stockContext = app(SaleStockService::class)->shiftStockContext($openShift);

            $itemsByCategory = $categoryRecords->mapWithKeys(function ($cat) use ($openShift, $stockContext) {
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
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku ?? '',
                        'stock' => $available,
                        'stock_pieces' => $available,
                        'stock_unit' => 'pcs',
                        'selling_price' => (float) (optional($defaultPackaging)->selling_price ?? 0),
                        'default_packaging_id' => $defaultPackaging?->id,
                        'packagings' => $normalized->map(function ($row) use ($available) {
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
            })->filter(fn ($items) => $items->isNotEmpty());

            $categories = $categoryRecords
                ->filter(fn ($cat) => $itemsByCategory->has($cat->id))
                ->values();
        }

        $serviceCategories = collect();
        $servicesByCategory = collect();
        $serviceBusinessTypes = [];
        $multiServiceBusiness = false;

        if ($servicesEnabled) {
            if ($branchFilterId) {
                $serviceBusinessTypes = $business->branchServicePosTypesMeta($branchFilterId);
            } else {
                $serviceBusinessTypes = $business->servicePosTypesMeta();
            }

            $multiServiceBusiness = count($serviceBusinessTypes) > 1;

            $serviceCategoryRecords = ServiceCategory::query()
                ->where('business_id', $business->id)
                ->whereHas('services', fn ($q) => $q->where('is_active', true))
                ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
                ->orderBy('name')
                ->get();

            $servicesByCategory = ServiceCategory::query()
                ->where('business_id', $business->id)
                ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
                ->with(['activeServices'])
                ->get()
                ->mapWithKeys(function ($cat) {
                    $typeKey = $cat->source_service_type_key ?: 'other';
                    $services = $cat->activeServices->map(fn (Service $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'unit_label' => $s->unit_label,
                        'price' => (float) $s->price,
                        'businessTypeKey' => $typeKey,
                    ])->values();

                    return [$cat->id => $services];
                })
                ->filter(fn ($services) => $services->isNotEmpty());

            $serviceCategories = $serviceCategoryRecords
                ->filter(fn ($cat) => $servicesByCategory->has($cat->id))
                ->values();
        }

        $defaultCatalog = $retailEnabled ? 'products' : 'services';
        if ($retailEnabled && $servicesEnabled && $categories->isEmpty() && $serviceCategories->isNotEmpty()) {
            $defaultCatalog = 'services';
        }

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
            'retailEnabled',
            'servicesEnabled',
            'serviceCategories',
            'servicesByCategory',
            'serviceBusinessTypes',
            'multiServiceBusiness',
            'defaultCatalog',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['process_sales']);

        $businessId = $this->currentBusinessId();
        $business = $this->requireCurrentBusiness();
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
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|exists:items,id',
            'items.*.item_packaging_id' => 'nullable|exists:item_packagings,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0.01',
            'services' => 'nullable|array',
            'services.*.service_id' => 'required_with:services|exists:services,id',
            'services.*.qty' => 'required_with:services|integer|min:1',
            'services.*.price' => 'required_with:services|numeric|min:0',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $activeItems = array_values(array_filter($request->input('items', []) ?: [], fn ($i) => ($i['qty'] ?? 0) > 0));
        $activeServices = array_values(array_filter($request->input('services', []) ?: [], fn ($s) => ($s['qty'] ?? 0) > 0));

        if (empty($activeItems) && empty($activeServices)) {
            return redirect()->back()->with('error', 'Add at least one product or service to the cart.')->withInput();
        }

        if (! empty($activeItems) && ! $business->isRetailEnabled()) {
            return redirect()->back()->with('error', 'Retail products are not enabled for this business.')->withInput();
        }

        if (! empty($activeServices) && ! $business->servicesMenuVisible()) {
            return redirect()->back()->with('error', 'Services are not enabled for this business.')->withInput();
        }

        DB::beginTransaction();

        try {
            if (! empty($activeItems)) {
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
            }

            $customerFields = $this->resolveCustomerFields($request);
            $createdSales = [];

            // Products → separate ORD- sale
            if (! empty($activeItems)) {
                $productTotal = 0;
                foreach ($activeItems as $i) {
                    $productTotal += ((float) $i['qty'] * (float) $i['price']);
                }

                $productRef = 'ORD-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
                $productSale = Sale::create([
                    'business_id' => $businessId,
                    'user_id' => Auth::id(),
                    'shift_id' => $openShift?->id,
                    'reference_no' => $productRef,
                    'sale_source' => 'pos',
                    'stock_deducted' => false,
                    'consumables_deducted' => true,
                    'sale_date' => $request->sale_date,
                    'total_amount' => $productTotal,
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
                        'sale_id' => $productSale->id,
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

                $createdSales[] = $productSale;
            }

            // Services → separate SRV- sale
            if (! empty($activeServices)) {
                $serviceTotal = 0;
                foreach ($activeServices as $s) {
                    $serviceTotal += ((float) $s['qty'] * (float) $s['price']);
                }

                $serviceRef = 'SRV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
                $serviceSale = Sale::create([
                    'business_id' => $businessId,
                    'user_id' => Auth::id(),
                    'shift_id' => $openShift?->id,
                    'reference_no' => $serviceRef,
                    'sale_source' => 'service_pos',
                    'stock_deducted' => false,
                    'consumables_deducted' => false,
                    'sale_date' => $request->sale_date,
                    'total_amount' => $serviceTotal,
                    'amount_paid' => 0,
                    'payment_status' => 'pending',
                    'customer_id' => $customerFields['customer_id'],
                    'customer_name' => $customerFields['customer_name'],
                    'customer_phone' => $customerFields['customer_phone'],
                    'notes' => $request->notes,
                ]);

                foreach ($activeServices as $line) {
                    $service = Service::find($line['service_id']);
                    if (! $service || $service->business_id !== $businessId) {
                        throw new \InvalidArgumentException('Invalid service selected.');
                    }

                    $qty = (float) $line['qty'];
                    $price = (float) $line['price'];
                    $subtotal = $qty * $price;

                    SaleItem::create([
                        'sale_id' => $serviceSale->id,
                        'item_id' => null,
                        'service_id' => $service->id,
                        'line_description' => $service->name.' ('.$service->unit_label.')',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'list_unit_price' => $price,
                        'cost_price' => 0,
                        'subtotal' => $subtotal,
                    ]);
                }

                $createdSales[] = $serviceSale;
            }

            DB::commit();
            $openShift?->refreshTotals();

            $refs = collect($createdSales)->pluck('reference_no')->implode(' + ');
            $paySale = $createdSales[0];
            $redirectParams = ['pay' => $paySale->id];

            if (count($createdSales) === 2) {
                $redirectParams['also_pay'] = $createdSales[1]->id;
                $message = "2 orders saved ({$refs}). Pay once below — one payment covers both.";
            } else {
                $message = "Order placed successfully ({$refs}). Complete payment below.";
            }

            return redirect()->route('sales.index', $redirectParams)
                ->with('success', $message);

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
        $sale->load(['items.item', 'items.itemPackaging.packagingType', 'items.service', 'user', 'payments.user']);
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

        $linkedIds = collect((array) $request->input('linked_sale_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $sale->id)
            ->unique()
            ->values();

        if ($linkedIds->isNotEmpty()) {
            $linkedSales = Sale::query()
                ->where('business_id', $businessId)
                ->whereIn('id', $linkedIds)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->get();

            foreach ($linkedSales as $linkedSale) {
                $this->ensureCanAccessStaffRecord((int) $linkedSale->user_id);
            }

            if ($linkedSales->isNotEmpty()) {
                return $this->payLinkedCheckout($request, $sale, $linkedSales, $business, $businessId);
            }
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

        if ($request->has('split_payments') && is_array($request->split_payments) && count($request->split_payments) > 0) {
            return $this->processSplitPayments($request, $sale, $balanceDue, $business, $businessId);
        }

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

            DB::beginTransaction();
            try {
                $sale->update($updateData);
                $sale->refresh();
                app(SaleStockService::class)->deductIfPaid(
                    $sale,
                    $sale->shift_id ? Shift::find($sale->shift_id) : null
                );
                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return redirect()->back()->with('error', $e->getMessage());
            } catch (\Exception $e) {
                DB::rollBack();

                return redirect()->back()->with('error', 'Error saving pay later sale: '.$e->getMessage());
            }

            if ($sale->shift_id) {
                Shift::find($sale->shift_id)?->refreshTotals();
            }

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
            app(SaleStockService::class)->deductIfPaid(
                $sale,
                $sale->shift_id ? Shift::find($sale->shift_id) : null
            );

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
            $hadStockDeducted = $sale->stock_deducted;
            app(SaleStockService::class)->restoreForSale($sale);

            $sale->update([
                'payment_status' => 'cancelled',
            ]);

            DB::commit();

            if ($sale->shift_id) {
                Shift::find($sale->shift_id)?->refreshTotals();
            }

            return redirect()->back()->with('success', "Sale ($sale->reference_no) has been cancelled".($hadStockDeducted ? ' and stock restored' : '').'.');
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
        $sale->load('items.item', 'items.itemPackaging');
        $submittedIds = collect($lineItems)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($submittedIds) > $sale->items->count()) {
            throw new \InvalidArgumentException('Invalid order lines selected.');
        }

        $itemsToDelete = $sale->items->filter(fn ($item) => ! in_array((int) $item->id, $submittedIds, true));

        if ($itemsToDelete->count() === $sale->items->count()) {
            throw new \InvalidArgumentException('Cannot remove all items from order. Please cancel the sale if you want to remove all items.');
        }

        foreach ($itemsToDelete as $removedItem) {
            if ($sale->stock_deducted) {
                app(SaleStockService::class)->restoreSaleItemStock($removedItem);
            }
            $removedItem->delete();
        }

        $sale->load('items');
        $newTotal = 0;

        foreach ($lineItems as $line) {
            $saleItem = $sale->items->firstWhere('id', (int) $line['id']);

            if (! $saleItem) {
                continue;
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

    private function processSplitPayments(Request $request, Sale $sale, float $balanceDue, $business, int $businessId)
    {
        $enabledKeys = $business->enabledPaymentMethodKeys();

        $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => ['required', 'string', Rule::in($enabledKeys)],
            'split_payments' => 'required|array|min:1|max:4',
            'split_payments.*.payment_method' => ['required', 'string', Rule::in($enabledKeys)],
            'split_payments.*.amount' => 'required|numeric|min:0.01',
            'split_payments.*.payment_provider' => 'nullable|string|max:255',
            'split_payments.*.transaction_reference' => 'nullable|string|max:255',
        ]);

        $primaryMethod = $business->findPaymentMethod($request->payment_method);
        if (! $primaryMethod || ($primaryMethod['type'] ?? '') === 'credit') {
            return redirect()->back()->with('error', 'Split payment cannot use Pay Later (Credit) as a payment line.');
        }

        $lines = [[
            'method' => $primaryMethod,
            'amount' => (float) $request->amount_paid,
            'payment_provider' => $request->payment_provider,
            'transaction_reference' => $request->transaction_reference,
        ]];

        foreach ($request->split_payments as $split) {
            $splitMethod = $business->findPaymentMethod($split['payment_method'] ?? '');
            if (! $splitMethod || ($splitMethod['type'] ?? '') === 'credit') {
                return redirect()->back()->with('error', 'One of the split payment methods is invalid.');
            }

            $lines[] = [
                'method' => $splitMethod,
                'amount' => (float) ($split['amount'] ?? 0),
                'payment_provider' => $split['payment_provider'] ?? null,
                'transaction_reference' => $split['transaction_reference'] ?? null,
            ];
        }

        foreach ($lines as $line) {
            if (! empty($line['method']['requires_reference'])) {
                if (! filled(trim((string) ($line['payment_provider'] ?? ''))) || ! filled(trim((string) ($line['transaction_reference'] ?? '')))) {
                    return redirect()->back()->with('error', 'Provider and transaction reference are required for '.$line['method']['label'].'.');
                }
            }
        }

        $totalRequested = array_sum(array_map(fn ($line) => $line['amount'], $lines));
        if ($totalRequested <= 0 || $totalRequested > $balanceDue + 0.001) {
            return redirect()->back()->with('error', 'Split payment total must be greater than zero and cannot exceed the balance due.');
        }

        $willBePartial = $totalRequested < $balanceDue - 0.001;

        if ($willBePartial) {
            $request->validate([
                'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'due_date' => 'required|date',
                'notes' => 'required|string|max:1000',
            ]);
        }

        if (! empty($primaryMethod['requires_reference'])) {
            $request->validate([
                'payment_provider' => 'required|string|max:255',
                'transaction_reference' => 'required|string|max:255',
            ]);
        }

        DB::beginTransaction();
        try {
            $amountApplied = 0.0;
            $lastMethodKey = $primaryMethod['key'];

            foreach ($lines as $line) {
                $amountToPay = min($line['amount'], $balanceDue - $amountApplied);
                if ($amountToPay <= 0) {
                    break;
                }

                SalePayment::create([
                    'sale_id' => $sale->id,
                    'user_id' => Auth::id(),
                    'amount' => $amountToPay,
                    'payment_method' => $line['method']['key'],
                    'payment_provider' => $line['payment_provider'] ?? null,
                    'transaction_reference' => $line['transaction_reference'] ?? null,
                ]);

                $amountApplied += $amountToPay;
                $lastMethodKey = $line['method']['key'];
            }

            $newAmountPaid = (float) $sale->amount_paid + $amountApplied;
            $status = $newAmountPaid >= (float) $sale->total_amount ? 'paid' : 'partial';

            $updateData = [
                'amount_paid' => $newAmountPaid,
                'payment_status' => $status,
                'payment_method' => $lastMethodKey,
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
            app(SaleStockService::class)->deductIfPaid(
                $sale,
                $sale->shift_id ? Shift::find($sale->shift_id) : null
            );

            DB::commit();

            if ($sale->shift_id) {
                Shift::find($sale->shift_id)?->refreshTotals();
            }

            $methodsUsed = collect($lines)->map(fn ($line) => $line['method']['label'])->unique()->implode(' + ');
            $message = 'Split payment of '.money($amountApplied).' recorded ('.$methodsUsed.').';

            if ($status === 'partial') {
                $remaining = (float) $sale->total_amount - $newAmountPaid;
                $message .= ' Balance remaining: '.money($remaining).' — due '.$request->due_date.'.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Error processing split payment: '.$e->getMessage());
        }
    }

    /**
     * One payment covering a primary sale + linked checkout sales (e.g. products ORD + services SRV).
     *
     * @param  \Illuminate\Support\Collection<int, Sale>  $linkedSales
     */
    private function payLinkedCheckout(Request $request, Sale $primarySale, $linkedSales, $business, int $businessId)
    {
        $sales = collect([$primarySale])->merge($linkedSales)->unique('id')->values();

        $combinedBalance = $sales->sum(fn (Sale $s) => max(0, (float) $s->total_amount - (float) $s->amount_paid));

        if ($combinedBalance <= 0) {
            return redirect()->back()->with('error', 'These sales have no balance remaining.');
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

            $customerFields = $this->resolveCustomerFields($request);

            DB::beginTransaction();
            try {
                foreach ($sales as $sale) {
                    $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
                    if ($balance <= 0) {
                        continue;
                    }

                    $status = $sale->amount_paid > 0 ? 'partial' : 'debt';
                    $updateData = [
                        'payment_status' => $status,
                        'customer_id' => $customerFields['customer_id'],
                        'customer_name' => $customerFields['customer_name'],
                        'customer_phone' => $customerFields['customer_phone'],
                        'due_date' => $request->due_date,
                    ];

                    if ($request->filled('notes')) {
                        $updateData['notes'] = $this->appendSaleNote($sale, $request->notes);
                    }

                    $sale->update($updateData);
                    $sale->refresh();
                    app(SaleStockService::class)->deductIfPaid(
                        $sale,
                        $sale->shift_id ? Shift::find($sale->shift_id) : null
                    );
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                return redirect()->back()->with('error', $e->getMessage());
            }

            $sales->pluck('shift_id')->filter()->unique()->each(fn ($id) => Shift::find($id)?->refreshTotals());

            $refs = $sales->pluck('reference_no')->implode(' + ');

            return redirect()->back()->with(
                'success',
                'Orders '.$refs.' saved as credit. Combined balance '.money($combinedBalance).' due '.$request->due_date.'.'
            );
        }

        $request->validate([
            'amount_paid' => 'required|numeric|min:0.01',
        ]);

        if (! empty($method['requires_reference'])) {
            $request->validate([
                'payment_provider' => 'required|string|max:255',
                'transaction_reference' => 'required|string|max:255',
            ]);
        }

        $amountRemaining = min((float) $request->amount_paid, $combinedBalance);
        $willBePartial = $amountRemaining < $combinedBalance;

        if ($willBePartial) {
            $request->validate([
                'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $businessId)],
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'due_date' => 'required|date',
                'notes' => 'required|string|max:1000',
            ]);
        }

        DB::beginTransaction();
        try {
            $customerFields = $willBePartial || $request->filled('customer_id')
                ? $this->resolveCustomerFields($request)
                : null;

            foreach ($sales as $sale) {
                $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
                if ($balance <= 0 || $amountRemaining <= 0) {
                    continue;
                }

                $amountToPay = min($amountRemaining, $balance);
                $amountRemaining -= $amountToPay;

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

                if ($status !== 'paid' || $willBePartial) {
                    if ($customerFields) {
                        $updateData['customer_id'] = $customerFields['customer_id'];
                        $updateData['customer_name'] = $customerFields['customer_name'];
                        $updateData['customer_phone'] = $customerFields['customer_phone'];
                    }
                    if ($willBePartial) {
                        $updateData['due_date'] = $request->due_date;
                        $updateData['notes'] = $this->appendSaleNote($sale, $request->notes);
                    }
                } elseif ($status === 'paid') {
                    $updateData['due_date'] = null;
                    if ($customerFields) {
                        $updateData['customer_id'] = $customerFields['customer_id'];
                        $updateData['customer_name'] = $customerFields['customer_name'];
                        $updateData['customer_phone'] = $customerFields['customer_phone'];
                    }
                }

                $sale->update($updateData);
                $sale->refresh();
                app(SaleStockService::class)->deductIfPaid(
                    $sale,
                    $sale->shift_id ? Shift::find($sale->shift_id) : null
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }

        $sales->pluck('shift_id')->filter()->unique()->each(fn ($id) => Shift::find($id)?->refreshTotals());

        $paidTotal = min((float) $request->amount_paid, $combinedBalance);
        $refs = $sales->pluck('reference_no')->implode(' + ');
        $message = 'One payment of '.money($paidTotal).' recorded for '.$refs.'.';

        if ($willBePartial) {
            $message .= ' Remaining balance due '.$request->due_date.'.';
        }

        return redirect()->back()->with('success', $message);
    }

    private function scopeCarriedOverUnpaidSales($query, int $userId, int $currentShiftId): void
    {
        $query->where('user_id', $userId)
            ->where('shift_id', '!=', $currentShiftId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid');
    }

    private function countCarriedOverUnpaidSales(int $businessId, int $userId, int $currentShiftId): int
    {
        return Sale::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('shift_id', '!=', $currentShiftId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->count();
    }
}
