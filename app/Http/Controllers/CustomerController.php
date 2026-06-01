<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage_customers');

        $businessId = Auth::user()->business_id;

        $query = Customer::where('business_id', $businessId)->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $customers = $query->get();

        $outstandingByCustomer = Sale::where('business_id', $businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->whereNotNull('customer_id')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($sales) => $sales->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid));

        $stats = [
            'total' => Customer::where('business_id', $businessId)->count(),
            'active' => Customer::where('business_id', $businessId)->where('is_active', true)->count(),
            'with_debt' => $outstandingByCustomer->filter(fn ($amount) => $amount > 0)->count(),
        ];

        return view('customers.index', compact('customers', 'stats', 'outstandingByCustomer'));
    }

    public function create()
    {
        Gate::authorize('manage_customers');

        return view('customers.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage_customers');

        $businessId = Auth::user()->business_id;
        $phone = Customer::normalizePhone($request->phone);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'region' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! $phone) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid phone number.');
        }

        if (Customer::where('business_id', $businessId)->where('phone', $phone)->exists()) {
            return redirect()->back()->withInput()->with('error', 'A customer with this phone number already exists.');
        }

        Customer::create([
            'business_id' => $businessId,
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
            'address' => $request->address,
            'region' => $request->region,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('customers.index')->with('success', 'Customer registered successfully.');
    }

    public function show(Customer $customer)
    {
        Gate::authorize('manage_customers');
        $this->ensureAccess($customer);

        $sales = Sale::where('business_id', Auth::user()->business_id)
            ->where('customer_id', $customer->id)
            ->with('user')
            ->latest()
            ->get();

        $activeSales = $sales->where('payment_status', '!=', 'cancelled');
        $outstanding = $activeSales
            ->filter(fn ($sale) => (float) $sale->total_amount > (float) $sale->amount_paid)
            ->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid);

        $stats = [
            'total_sales' => $activeSales->count(),
            'total_spent' => (float) $activeSales->sum('amount_paid'),
            'outstanding' => $outstanding,
        ];

        return view('customers.show', compact('customer', 'sales', 'stats'));
    }

    public function edit(Customer $customer)
    {
        Gate::authorize('manage_customers');
        $this->ensureAccess($customer);

        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        Gate::authorize('manage_customers');
        $this->ensureAccess($customer);

        $phone = Customer::normalizePhone($request->phone);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'region' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! $phone) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid phone number.');
        }

        if (Customer::where('business_id', $customer->business_id)
            ->where('phone', $phone)
            ->where('id', '!=', $customer->id)
            ->exists()) {
            return redirect()->back()->withInput()->with('error', 'Another customer already uses this phone number.');
        }

        $customer->update([
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
            'address' => $request->address,
            'region' => $request->region,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active'),
        ]);

        Sale::where('customer_id', $customer->id)->update([
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('manage_customers');
        $this->ensureAccess($customer);

        if ($customer->sales()->exists()) {
            return redirect()->back()->with('error', 'This customer has sales records and cannot be deleted. You can mark them inactive instead.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer removed.');
    }

    private function ensureAccess(Customer $customer): void
    {
        if ($customer->business_id != Auth::user()->business_id) {
            abort(403);
        }
    }
}
