<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends ApiController
{
    /** Regions shown on web /customers register form */
    private const FORM_REGIONS = [
        'Arusha',
        'Dar es Salaam',
        'Dodoma',
        'Mbeya',
        'Mwanza',
        'Morogoro',
        'Tanga',
        'Kilimanjaro',
        'Zanzibar',
    ];

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        $businessId = $this->apiBusinessId();

        $query = Customer::query()
            ->where('business_id', $businessId)
            ->orderBy('name');

        if ($search = trim((string) $request->get('q', $request->get('search', '')))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->get('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $customers = $query->get();

        $outstandingByCustomer = $this->outstandingByCustomer($businessId);

        return $this->success([
            'customers' => $customers->map(function (Customer $customer) use ($outstandingByCustomer) {
                return $this->customerPayload($customer, (float) ($outstandingByCustomer[$customer->id] ?? 0));
            })->values(),
            'stats' => [
                'total' => Customer::where('business_id', $businessId)->count(),
                'active' => Customer::where('business_id', $businessId)->where('is_active', true)->count(),
                'with_debt' => $outstandingByCustomer->filter(fn ($amount) => $amount > 0)->count(),
            ],
            'meta' => [
                'customers_count' => $customers->count(),
            ],
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        return $this->success([
            'regions' => self::FORM_REGIONS,
            'phone_hint' => 'Enter the last 9 digits (e.g. 712345678). Stored as +255…',
            'defaults' => [
                'is_active' => true,
            ],
            'fields' => [
                'name' => ['required' => true, 'max' => 255],
                'phone' => ['required' => true, 'digits' => 9],
                'email' => ['required' => false, 'max' => 255],
                'address' => ['required' => false, 'max' => 500],
                'region' => ['required' => false],
                'notes' => ['required' => false, 'max' => 2000],
                'is_active' => ['required' => false, 'default' => true],
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers', 'process_sales', 'collect_payments', 'manage_debts'])) {
            return $deny;
        }

        $request->validate([
            'q' => 'nullable|string|max:120',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $businessId = $this->apiBusinessId();
        $q = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 30);

        $query = Customer::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $outstandingByCustomer = $this->outstandingByCustomer($businessId);

        $customers = $query->limit($limit)->get()->map(function (Customer $customer) use ($outstandingByCustomer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'outstanding_balance' => (float) ($outstandingByCustomer[$customer->id] ?? 0),
            ];
        })->values();

        return $this->success(['customers' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:500',
                'region' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:2000',
                'is_active' => 'nullable|boolean',
            ]);

            $businessId = $this->apiBusinessId();
            $phone = Customer::normalizePhone($data['phone']);

            if (! $phone) {
                return $this->error('Please enter a valid phone number.', 422, [
                    'phone' => ['Please enter a valid phone number (e.g. 712345678).'],
                ]);
            }

            if (Customer::where('business_id', $businessId)->where('phone', $phone)->exists()) {
                return $this->error('A customer with this phone number already exists.', 422, [
                    'phone' => ['A customer with this phone number already exists.'],
                ]);
            }

            $customer = Customer::create([
                'business_id' => $businessId,
                'name' => $data['name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'region' => $data['region'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return $this->success([
                'customer' => $this->customerPayload($customer, 0.0),
            ], 'Customer registered successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($customer)) {
            return $deny;
        }

        $sales = Sale::where('business_id', $this->apiBusinessId())
            ->where('customer_id', $customer->id)
            ->with('user:id,name')
            ->latest()
            ->get();

        $activeSales = $sales->where('payment_status', '!=', 'cancelled');
        $outstanding = $activeSales
            ->filter(fn ($sale) => (float) $sale->total_amount > (float) $sale->amount_paid)
            ->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid);

        return $this->success([
            'customer' => $this->customerPayload($customer, (float) $outstanding),
            'stats' => [
                'total_sales' => $activeSales->count(),
                'total_spent' => (float) $activeSales->sum('amount_paid'),
                'outstanding' => (float) $outstanding,
            ],
            'sales' => $activeSales->take(50)->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'sale_date' => $sale->sale_date,
                'total_amount' => (float) $sale->total_amount,
                'amount_paid' => (float) $sale->amount_paid,
                'balance' => max(0, (float) $sale->total_amount - (float) $sale->amount_paid),
                'payment_status' => $sale->payment_status,
                'sold_by' => $sale->user?->name,
            ])->values(),
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($customer)) {
            return $deny;
        }

        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:500',
                'region' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:2000',
                'is_active' => 'nullable|boolean',
            ]);

            $phone = Customer::normalizePhone($data['phone']);

            if (! $phone) {
                return $this->error('Please enter a valid phone number.', 422, [
                    'phone' => ['Please enter a valid phone number (e.g. 712345678).'],
                ]);
            }

            if (Customer::where('business_id', $customer->business_id)
                ->where('phone', $phone)
                ->where('id', '!=', $customer->id)
                ->exists()) {
                return $this->error('Another customer already uses this phone number.', 422, [
                    'phone' => ['Another customer already uses this phone number.'],
                ]);
            }

            $customer->update([
                'name' => $data['name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'region' => $data['region'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active', $customer->is_active),
            ]);

            Sale::where('customer_id', $customer->id)->update([
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
            ]);

            $outstanding = (float) ($this->outstandingByCustomer($this->apiBusinessId())[$customer->id] ?? 0);

            return $this->success([
                'customer' => $this->customerPayload($customer->fresh(), $outstanding),
            ], 'Customer updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_customers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($customer)) {
            return $deny;
        }

        if ($customer->sales()->exists()) {
            return $this->error(
                'This customer has sales records and cannot be deleted. You can mark them inactive instead.',
                422
            );
        }

        $customer->delete();

        return $this->success(null, 'Customer removed.');
    }

    private function ensureAccess(Customer $customer): ?JsonResponse
    {
        if ((int) $customer->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int|string, float>
     */
    private function outstandingByCustomer(int $businessId)
    {
        return Sale::where('business_id', $businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->whereNotNull('customer_id')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($sales) => (float) $sales->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid));
    }

    private function customerPayload(Customer $customer, float $outstanding = 0.0): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_display' => $customer->displayPhone(),
            'email' => $customer->email,
            'address' => $customer->address,
            'region' => $customer->region,
            'notes' => $customer->notes,
            'is_active' => (bool) $customer->is_active,
            'outstanding_balance' => $outstanding,
            'has_debt' => $outstanding > 0,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }
}
