<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    private function billingRules(Request $request): array
    {
        $rules = [
            'billing_model' => ['nullable', Rule::in(['', Plan::BILLING_FIXED, Plan::BILLING_PROFIT_SHARE])],
            'billing_price' => 'nullable|numeric|min:0',
            'profit_share_percent' => 'nullable|numeric|min:0|max:100',
            'profit_share_basis' => ['nullable', Rule::in(['gross_profit', 'net_profit'])],
            'minimum_monthly_fee' => 'nullable|numeric|min:0',
        ];

        if ($request->filled('billing_model') && $request->input('billing_model') === Plan::BILLING_FIXED) {
            $rules['billing_price'] = 'required|numeric|min:0.01';
        }

        if ($request->filled('billing_model') && $request->input('billing_model') === Plan::BILLING_PROFIT_SHARE) {
            $rules['profit_share_percent'] = 'required|numeric|min:0.01|max:100';
            $rules['profit_share_basis'] = 'required|in:gross_profit,net_profit';
        }

        return $rules;
    }

    private function normalizeBillingInput(Request $request): array
    {
        if (! $request->filled('billing_model')) {
            return [
                'billing_model' => null,
                'billing_price' => null,
                'profit_share_percent' => null,
                'profit_share_basis' => null,
                'minimum_monthly_fee' => null,
            ];
        }

        if ($request->input('billing_model') === Plan::BILLING_FIXED) {
            return [
                'billing_model' => Plan::BILLING_FIXED,
                'billing_price' => $request->input('billing_price'),
                'profit_share_percent' => null,
                'profit_share_basis' => null,
                'minimum_monthly_fee' => null,
            ];
        }

        return [
            'billing_model' => Plan::BILLING_PROFIT_SHARE,
            'billing_price' => null,
            'profit_share_percent' => $request->input('profit_share_percent'),
            'profit_share_basis' => $request->input('profit_share_basis'),
            'minimum_monthly_fee' => $request->input('minimum_monthly_fee', 0),
        ];
    }

    public function index()
    {
        $businesses = Business::with(['plan', 'owner'])->latest()->get();
        $pendingRegistrations = $businesses->where('pending_approval', true)->values();
        $billingService = app(\App\Services\PlatformBillingService::class);
        $businessFees = $businesses->mapWithKeys(fn ($business) => [
            $business->id => $billingService->calculateFee($business),
        ]);

        return view('admin.businesses.index', compact('businesses', 'businessFees', 'pendingRegistrations'));
    }

    public function create()
    {
        $plans = Plan::all();

        return view('admin.businesses.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string',
            'tin_number' => 'nullable|string|max:50',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in(tanzania_districts($request->region))],
            'address' => 'required|string|max:1000',
            'plan_id' => 'required|exists:plans,id',
            'password' => 'required|string|min:6',
        ] + $this->billingRules($request));

        $plan = Plan::findOrFail($request->plan_id);
        $expiryDate = now()->addMonths(max(1, (int) $plan->duration_months));

        $business = Business::create(array_merge([
            'name' => $request->name,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'tin_number' => $request->tin_number,
            'region' => $request->region,
            'district' => $request->district,
            'address' => $request->address,
            'plan_id' => $plan->id,
            'expiry_date' => $expiryDate,
            'is_active' => true,
        ], $this->normalizeBillingInput($request)));

        \App\Models\Branch::createDefaultForBusiness($business);

        \App\Models\User::create([
            'name' => $request->contact_person,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'business_id' => $business->id,
            'role' => 'owner',
        ]);

        AuditLog::log(
            'CREATE_BUSINESS',
            "Registered new business: {$business->name} (Email: {$business->email}) — Plan: {$plan->name}, Expiry: {$expiryDate->format('Y-m-d')}"
        );

        return redirect()->route('admin.businesses.index')->with('success', 'Business and Owner account registered successfully.');
    }

    public function edit(Business $business)
    {
        $plans = Plan::all();
        return view('admin.businesses.edit', compact('business', 'plans'));
    }

    public function update(Request $request, Business $business)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email,' . $business->id,
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string',
            'tin_number' => 'nullable|string|max:50',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in(tanzania_districts($request->region))],
            'address' => 'required|string|max:1000',
            'plan_id' => 'required|exists:plans,id',
            'is_active' => 'required|boolean',
        ] + $this->billingRules($request));

        $plan = Plan::findOrFail($request->plan_id);

        if ((int) $business->plan_id !== (int) $plan->id) {
            $expiryDate = now()->addMonths(max(1, (int) $plan->duration_months));
        } else {
            $expiryDate = $business->expiry_date ?? now()->addMonths(max(1, (int) $plan->duration_months));
        }

        $business->update(array_merge([
            'name' => $request->name,
            'email' => $request->email,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'tin_number' => $request->tin_number,
            'region' => $request->region,
            'district' => $request->district,
            'address' => $request->address,
            'plan_id' => $plan->id,
            'expiry_date' => $expiryDate,
            'is_active' => $request->boolean('is_active'),
        ], $this->normalizeBillingInput($request)));

        AuditLog::log('UPDATE_BUSINESS', "Updated business: {$business->name} — Plan: {$plan->name}, Expiry: {$business->expiry_date->format('Y-m-d')}, Active: {$business->is_active}");

        return redirect()->route('admin.businesses.index')->with('success', 'Business subscription updated.');
    }

    public function toggleStatus(Business $business)
    {
        if ($business->isPendingApproval()) {
            return redirect()->back()->with('error', 'Approve or reject this registration first.');
        }

        $business->is_active = ! $business->is_active;
        $business->save();

        $status = $business->is_active ? 'Activated' : 'Suspended';
        AuditLog::log('TOGGLE_BUSINESS_STATUS', "{$status} business: {$business->name}");

        return redirect()->back()->with('success', "Business successfully {$status}.");
    }

    public function approveRegistration(Business $business)
    {
        if (! $business->isPendingApproval()) {
            return redirect()->back()->with('error', 'This business is not awaiting approval.');
        }

        $trialDays = max(1, (int) app(\App\Services\PlatformSettingsService::class)->get('default_trial_days', 30));

        $business->update([
            'is_active' => true,
            'pending_approval' => false,
            'expiry_date' => now()->addDays($trialDays),
        ]);

        AuditLog::log('APPROVE_BUSINESS_REGISTRATION', "Approved registration for: {$business->name}");

        $owner = $business->owner;
        if ($owner && $business->phone) {
            $loginPassword = User::generateRandomPassword(
                max(8, (int) app(\App\Services\PlatformSettingsService::class)->get('min_password_length', 8))
            );
            $owner->update(['password' => \Illuminate\Support\Facades\Hash::make($loginPassword)]);

            $phone = preg_replace('/\D/', '', $business->phone);
            if ($phone) {
                app(\App\Services\SmsService::class)->sendSms(
                    $phone,
                    "Mauzo Link: Your account is approved. Sign in with your phone number. Password: {$loginPassword}"
                );
            }
        }

        return redirect()->back()->with('success', "{$business->name} has been approved and can now sign in.");
    }

    public function rejectRegistration(Business $business)
    {
        if (! $business->isPendingApproval()) {
            return redirect()->back()->with('error', 'This business is not awaiting approval.');
        }

        $name = $business->name;
        $business->users()->delete();
        $business->branches()->delete();
        $business->delete();

        AuditLog::log('REJECT_BUSINESS_REGISTRATION', "Rejected registration for: {$name}");

        return redirect()->back()->with('success', "Registration for {$name} was rejected and removed.");
    }
}
