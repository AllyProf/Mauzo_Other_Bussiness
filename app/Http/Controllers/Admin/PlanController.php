<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanFeatureService;
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
            'max_storage_value' => 'required|numeric|min:0',
            'max_storage_unit' => ['required', Rule::in(['gb', 'mb'])],
            'max_sms' => 'required|integer|min:0',
            'max_email_sms' => 'required|integer|min:0',
            'allow_sms_sending' => 'nullable|boolean',
            'allow_email_sms' => 'nullable|boolean',
            'features' => 'nullable|string',
            'enabled_features' => 'nullable|array',
            'enabled_features.*' => ['string', Rule::in(app(PlanFeatureService::class)->allKeys())],
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

        $storageValue = (float) ($data['max_storage_value'] ?? 0);
        if ($storageValue <= 0) {
            $data['max_storage_mb'] = 0;
        } elseif (($data['max_storage_unit'] ?? 'gb') === 'mb') {
            $data['max_storage_mb'] = (int) round($storageValue);
        } else {
            $data['max_storage_mb'] = (int) round($storageValue * 1024);
        }
        unset($data['max_storage_value'], $data['max_storage_unit']);

        $data['allow_sms_sending'] = $request->boolean('allow_sms_sending');
        $data['allow_email_sms'] = $request->boolean('allow_email_sms');

        if (! $data['allow_sms_sending']) {
            $data['max_sms'] = 0;
        }
        if (! $data['allow_email_sms']) {
            $data['max_email_sms'] = 0;
        }

        $data['enabled_features'] = app(PlanFeatureService::class)->normalizeSelection(
            $data['enabled_features'] ?? []
        );

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
