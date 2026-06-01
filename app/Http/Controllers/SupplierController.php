<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index()
    {
        Gate::authorize('manage_suppliers');
        $suppliers = Supplier::where('business_id', Auth::user()->business_id)->get();
        return view('registration.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        Gate::authorize('manage_suppliers');
        return view('registration.suppliers.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('manage_suppliers');
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'region' => 'nullable|string|max:100',
        ]);

        Supplier::create([
            'business_id' => Auth::user()->business_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => '+255' . $request->phone,
            'region' => $request->region,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier registered successfully.');
    }

    public function edit(Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        if ($supplier->business_id != Auth::user()->business_id) abort(403);
        return view('registration.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        if ($supplier->business_id != Auth::user()->business_id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'region' => 'nullable|string|max:100',
        ]);

        $data = $request->all();
        $data['phone'] = '+255' . $request->phone;
        $supplier->update($data);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        Gate::authorize('manage_suppliers');
        if ($supplier->business_id != Auth::user()->business_id) abort(403);
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier removed.');
    }
}
