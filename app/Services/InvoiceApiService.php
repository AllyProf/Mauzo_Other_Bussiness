<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceApiService
{
    public function __construct(private SaleStockService $stockService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(User $user, int $businessId, ?int $branchFilterId, array $filters = []): array
    {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled')
            ->where('sale_source', 'invoice')
            ->with(['user:id,name', 'customer:id,name,phone,email'])
            ->latest()
            ->latest('id');

        if (! $user->seesBusinessWideData()) {
            $query->where('user_id', $user->id);
        } elseif ($branchFilterId) {
            $this->scopeSalesToBranch($query, $businessId, $branchFilterId);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('sale_date', $filters['date']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($builder) use ($q) {
                $builder->where('reference_no', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        $statsQuery = Sale::query()
            ->where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled')
            ->where('sale_source', 'invoice');

        if (! $user->seesBusinessWideData()) {
            $statsQuery->where('user_id', $user->id);
        } elseif ($branchFilterId) {
            $this->scopeSalesToBranch($statsQuery, $businessId, $branchFilterId);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 20)));
        $invoices = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'invoices' => collect($invoices->items())->map(fn (Sale $s) => $this->formatSummary($s))->values()->all(),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'unpaid' => (clone $statsQuery)->whereIn('payment_status', ['pending', 'partial', 'debt'])->count(),
                'total_amount' => (float) (clone $statsQuery)->sum('total_amount'),
            ],
            'scoped_to_self' => ! $user->seesBusinessWideData(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createForm(User $user, Business $business, ?int $branchFilterId): array
    {
        $openShift = Shift::openForUser($user->id, $business->id);

        if ($user->requiresOpenShift() && ! $openShift) {
            throw ValidationException::withMessages([
                'shift' => 'Complete a physical stock check and open your shift before creating invoices.',
            ]);
        }

        $stockContext = $this->stockService->shiftStockContext($openShift);
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

        $catalogItems = Item::where('business_id', $business->id)
            ->when($branchFilterId, fn ($query) => $query->whereHas('category', fn ($cat) => $cat->where('branch_id', $branchFilterId)))
            ->with(['packagings', 'category'])
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($openShift, $stockContext, $business) {
                $available = $this->stockService->availableStockForShift($item, $openShift, $stockContext);
                $price = (float) (optional($item->packagings->first())->selling_price ?? 0);
                $typeKey = $item->category?->source_business_type_key ?: 'other';

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku ?? '',
                    'stock' => (float) $available,
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

        $customers = Customer::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
            ])
            ->values()
            ->all();

        $activeBranchName = $branchFilterId
            ? (Branch::find($branchFilterId)?->name ?? $user->branch?->name ?? 'Branch')
            : null;

        return [
            'shift' => $openShift ? [
                'id' => $openShift->id,
                'opened_at' => $openShift->opened_at?->toIso8601String(),
            ] : null,
            'requires_open_shift' => $user->requiresOpenShift(),
            'branch_id' => $branchFilterId,
            'branch_name' => $activeBranchName,
            'business_types' => $businessTypes,
            'multi_business' => count($businessTypes) > 1,
            'catalog_items' => $catalogItems->all(),
            'catalog_groups' => $catalogGroups,
            'customers' => $customers,
            'default_sale_date' => now()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(User $user, Business $business, ?int $branchFilterId, array $payload): array
    {
        $openShift = Shift::openForUser($user->id, $business->id);

        if ($user->requiresOpenShift() && ! $openShift) {
            throw ValidationException::withMessages([
                'shift' => 'Your shift is not open. Complete stock check first.',
            ]);
        }

        $validated = validator($payload, [
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', $business->id)],
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ])->validate();

        $activeItems = array_values(array_filter($validated['items'], fn ($i) => ($i['qty'] ?? 0) > 0));

        if ($activeItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Please add at least one item with quantity.',
            ]);
        }

        return DB::transaction(function () use ($user, $business, $branchFilterId, $openShift, $activeItems, $validated, $payload) {
            $stockContext = $this->stockService->shiftStockContext($openShift);

            foreach ($activeItems as $i) {
                $item = Item::with('category')->where('business_id', $business->id)->findOrFail($i['id']);

                if ($branchFilterId && (int) ($item->category?->branch_id ?? 0) !== (int) $branchFilterId) {
                    throw ValidationException::withMessages([
                        'items' => "Item {$item->name} is not available for the selected branch.",
                    ]);
                }

                $available = $this->stockService->availableStockForShift($item, $openShift, $stockContext);

                if ((float) $i['qty'] > $available) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$item->name}. Available: {$available}.",
                    ]);
                }
            }

            $totalAmount = 0.0;
            foreach ($activeItems as $i) {
                $totalAmount += (float) $i['qty'] * (float) $i['price'];
            }

            $ref = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
            $customerFields = $this->resolveCustomerFields($business->id, $validated);

            $sale = Sale::create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'shift_id' => $openShift?->id,
                'reference_no' => $ref,
                'sale_source' => 'invoice',
                'stock_deducted' => false,
                'sale_date' => $validated['sale_date'],
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'payment_status' => 'pending',
                'customer_id' => $customerFields['customer_id'],
                'customer_name' => $customerFields['customer_name'],
                'customer_phone' => $customerFields['customer_phone'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($activeItems as $i) {
                $subtotal = (float) $i['qty'] * (float) $i['price'];
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

            $openShift?->refreshTotals();

            $sale->load(['items.item', 'user:id,name', 'customer', 'business', 'payments.user:id,name']);

            $notification = app(BusinessInvoiceNotificationService::class)
                ->notifyCreated($sale, $user, $payload['customer_email'] ?? null);

            return [
                'invoice' => $this->formatDetail($sale),
                'notifications' => [
                    'sms_sent' => (bool) ($notification['sms_sent'] ?? false),
                    'email_sent' => (bool) ($notification['email_sent'] ?? false),
                    'sms_error' => $notification['sms_error'] ?? null,
                    'email_error' => $notification['email_error'] ?? null,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Sale $invoice): array
    {
        $invoice->load([
            'items.item',
            'items.itemPackaging.packagingType',
            'user:id,name',
            'customer',
            'payments.user:id,name',
            'business',
        ]);

        $business = $invoice->business;
        $paymentMethods = $business?->enabledPaymentMethods() ?? [];
        $paymentReceiveDetails = collect($business?->paymentMethodsConfig() ?? [])
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
            ->values()
            ->all();

        return [
            'invoice' => $this->formatDetail($invoice),
            'payment_methods' => collect($paymentMethods)->map(fn ($m) => [
                'key' => $m['key'] ?? $m,
                'label' => $m['label'] ?? ($m['key'] ?? $m),
            ])->values()->all(),
            'payment_receive_details' => $paymentReceiveDetails,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatSummary(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'reference_no' => $sale->reference_no,
            'sale_date' => $sale->sale_date instanceof \Carbon\Carbon
                ? $sale->sale_date->toDateString()
                : (string) $sale->sale_date,
            'total_amount' => (float) $sale->total_amount,
            'amount_paid' => (float) $sale->amount_paid,
            'balance_due' => max(0, (float) $sale->total_amount - (float) $sale->amount_paid),
            'payment_status' => $sale->payment_status,
            'payment_method' => $sale->payment_method,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer_name ?: $sale->customer?->name,
            'customer_phone' => $sale->customer_phone ?: $sale->customer?->phone,
            'customer_email' => $sale->customer?->email,
            'cashier' => $sale->user?->name,
            'shift_id' => $sale->shift_id,
            'sale_source' => $sale->sale_source,
            'created_at' => $sale->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDetail(Sale $sale): array
    {
        return array_merge($this->formatSummary($sale), [
            'due_date' => $sale->due_date?->toDateString(),
            'notes' => $sale->notes,
            'items' => $sale->items->map(fn (SaleItem $line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'name' => $line->item?->name ?? $line->line_description,
                'sku' => $line->item?->sku,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
                'packaging' => $line->itemPackaging?->packagingType?->name,
            ])->values()->all(),
            'payments' => $sale->relationLoaded('payments')
                ? $sale->payments->map(fn (SalePayment $p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'payment_method' => $p->payment_method,
                    'payment_provider' => $p->payment_provider,
                    'transaction_reference' => $p->transaction_reference,
                    'paid_at' => $p->created_at?->toIso8601String(),
                    'recorded_by' => $p->user?->name,
                ])->values()->all()
                : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{customer_id: ?int, customer_name: ?string, customer_phone: ?string}
     */
    private function resolveCustomerFields(int $businessId, array $payload): array
    {
        $customerId = $payload['customer_id'] ?? null;

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

        $phone = Customer::normalizePhone($payload['customer_phone'] ?? null);

        return [
            'customer_id' => null,
            'customer_name' => $payload['customer_name'] ?? null,
            'customer_phone' => $phone ?: ($payload['customer_phone'] ?? null),
        ];
    }

    private function scopeSalesToBranch($query, int $businessId, int $branchId): void
    {
        $query->whereIn('user_id', User::query()
            ->where('business_id', $businessId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->pluck('id'));
    }

    public function branchFilterId(User $user, ApiTenantContext $ctx): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $ctx->branchId();
    }
}
