<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    private function planRules(?Plan $plan = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'billing_model' => ['required', Rule::in([Plan::BILLING_FIXED, Plan::BILLING_PROFIT_SHARE])],
            'price' => 'nullable|numeric|min:0',
            'profit_share_percent' => 'nullable|numeric|min:0|max:100',
            'profit_share_basis' => ['nullable', Rule::in(['gross_profit', 'net_profit'])],
            'minimum_monthly_fee' => 'nullable|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'max_items' => 'required|integer|min:1',
            'max_users' => 'required|integer|min:1',
            'max_business_types' => 'required|integer|min:0',
            'max_branches' => 'required|integer|min:0',
            'max_sms' => 'required|integer|min:0',
            'max_email_sms' => 'required|integer|min:0',
            'features' => 'nullable|string',
        ];
    }

    private function normalizePlanInput(Request $request): array
    {
        $rules = $this->planRules();

        if ($request->input('billing_model') === Plan::BILLING_FIXED) {
            $rules['price'] = 'required|numeric|min:0';
        } else {
            $rules['profit_share_percent'] = 'required|numeric|min:0.01|max:100';
            $rules['profit_share_basis'] = 'required|in:gross_profit,net_profit';
        }

        $data = $request->validate($rules);

        if ($data['billing_model'] === Plan::BILLING_FIXED) {
            $data['profit_share_percent'] = 0;
            $data['profit_share_basis'] = 'net_profit';
            $data['minimum_monthly_fee'] = 0;
        } else {
            $data['price'] = (float) ($data['price'] ?? 0);
            $data['minimum_monthly_fee'] = (float) ($data['minimum_monthly_fee'] ?? 0);
        }

        return $data;
    }

    public function index()
    {
        $plans = Plan::all();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        Plan::create($this->normalizePlanInput($request));

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->normalizePlanInput($request));

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
    }
}
