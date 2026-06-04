<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use App\Services\Admin\BusinessDataPurgeService;
use App\Services\ServiceTemplateImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    private function ownerAssignmentRules(bool $required = true): array
    {
        $rules = [
            'owner_user_id' => [
                'nullable',
                'exists:users,id',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'owner')),
            ],
        ];

        if ($required) {
            $rules['owner_mode'] = ['required', Rule::in(['new', 'existing'])];
            $rules['existing_owner_id'] = [
                'required_if:owner_mode,existing',
                'nullable',
                'exists:users,id',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'owner')),
            ];
            $rules['password'] = 'required_if:owner_mode,new|nullable|string|min:6';
        }

        return $rules;
    }

    private function existingOwners()
    {
        return User::query()
            ->where('role', 'owner')
            ->withCount('ownedBusinesses')
            ->orderBy('name')
            ->get();
    }

    private function ownerBusinessCounts()
    {
        return Business::query()
            ->whereNotNull('owner_user_id')
            ->selectRaw('owner_user_id, COUNT(*) as business_count')
            ->groupBy('owner_user_id')
            ->pluck('business_count', 'owner_user_id');
    }

    private function operationModeRules(): array
    {
        return [
            'operation_mode' => ['required', Rule::in([
                Business::OPERATION_RETAIL,
                Business::OPERATION_SERVICES,
                Business::OPERATION_BOTH,
            ])],
            'service_template_types' => 'nullable|array',
            'service_template_types.*' => 'string',
        ];
    }

    private function finalizeBusinessSetup(Business $business, Request $request, Branch $branch): void
    {
        $mode = $request->input('operation_mode', Business::OPERATION_RETAIL);
        $business->update(['operation_mode' => $mode]);

        if (! in_array($mode, [Business::OPERATION_SERVICES, Business::OPERATION_BOTH], true)) {
            return;
        }

        $templateKeys = array_values(array_filter($request->input('service_template_types', [])));

        if ($templateKeys === []) {
            return;
        }

        $labels = app(ServiceTemplateImportService::class)->importForBranch(
            $business->fresh(),
            $branch->id,
            $templateKeys
        );

        if ($labels !== []) {
            AuditLog::log(
                'IMPORT_SERVICE_TEMPLATES',
                'Imported service templates for '.$business->name.': '.implode(', ', $labels),
                $business->id
            );
        }
    }

    private function syncBranchOwner(Business $business): void
    {
        if (! $business->owner_user_id) {
            return;
        }

        Branch::query()
            ->where('business_id', $business->id)
            ->whereNull('owner_user_id')
            ->update(['owner_user_id' => $business->owner_user_id]);
    }

    public function index()
    {
        $businesses = Business::with(['plan', 'ownerUser'])->latest()->get();
        $pendingRegistrations = $businesses->where('pending_approval', true)->values();
        $billingService = app(\App\Services\PlatformBillingService::class);
        $businessFees = $businesses->mapWithKeys(fn ($business) => [
            $business->id => $billingService->calculateFee($business),
        ]);
        $ownerBusinessCounts = $this->ownerBusinessCounts();

        return view('admin.businesses.index', compact('businesses', 'businessFees', 'pendingRegistrations', 'ownerBusinessCounts'));
    }

    public function create()
    {
        $plans = Plan::all();
        $existingOwners = $this->existingOwners();

        return view('admin.businesses.create', compact('plans', 'existingOwners'));
    }

    public function store(Request $request)
    {
        $linkExistingOwner = $request->input('owner_mode') === 'existing';

        $emailRules = ['required', 'email', 'unique:businesses,email'];
        if (! $linkExistingOwner) {
            $emailRules[] = Rule::unique('users', 'email');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => $emailRules,
            'contact_person' => 'required|string|max:255',
            'phone' => 'required|string',
            'tin_number' => 'nullable|string|max:50',
            'region' => ['required', 'string', Rule::in(tanzania_regions())],
            'district' => ['required', 'string', Rule::in(tanzania_districts($request->region))],
            'address' => 'required|string|max:1000',
            'plan_id' => 'required|exists:plans,id',
        ] + $this->billingRules($request) + $this->ownerAssignmentRules() + $this->operationModeRules());

        $plan = Plan::findOrFail($request->plan_id);
        $expiryDate = now()->addMonths(max(1, (int) $plan->duration_months));
        $ownerUserId = $linkExistingOwner ? (int) $request->existing_owner_id : null;

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
            'owner_user_id' => $ownerUserId,
        ], $this->normalizeBillingInput($request)));

        if ($linkExistingOwner) {
            $branch = Branch::createDefaultForBusiness($business);
            $this->finalizeBusinessSetup($business, $request, $branch);

            AuditLog::log(
                'CREATE_BUSINESS',
                "Registered new business under existing owner: {$business->name} (Owner ID: {$ownerUserId}) — Plan: {$plan->name}, Mode: {$business->fresh()->operationModeLabel()}, Expiry: {$expiryDate->format('Y-m-d')}",
                $business->id
            );

            return redirect()->route('admin.businesses.index')->with('success', 'Business linked to existing owner successfully.');
        }

        $owner = User::create([
            'name' => $request->contact_person,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'business_id' => $business->id,
            'role' => 'owner',
        ]);

        $business->update(['owner_user_id' => $owner->id]);
        $branch = Branch::createDefaultForBusiness($business->fresh());
        $this->finalizeBusinessSetup($business, $request, $branch);

        AuditLog::log(
            'CREATE_BUSINESS',
            "Registered new business: {$business->name} (Email: {$business->email}) — Plan: {$plan->name}, Mode: {$business->fresh()->operationModeLabel()}, Expiry: {$expiryDate->format('Y-m-d')}",
            $business->id
        );

        return redirect()->route('admin.businesses.index')->with('success', 'Business and owner account registered successfully.');
    }

    public function edit(Business $business)
    {
        $plans = Plan::all();
        $existingOwners = $this->existingOwners();
        $currentOwner = $business->resolveOwner();
        $ownerOtherBusinesses = $business->owner_user_id
            ? Business::query()
                ->where('owner_user_id', $business->owner_user_id)
                ->where('id', '!=', $business->id)
                ->orderBy('name')
                ->get()
            : collect();

        $purgeService = app(BusinessDataPurgeService::class);

        return view('admin.businesses.edit', [
            'business' => $business,
            'plans' => $plans,
            'existingOwners' => $existingOwners,
            'currentOwner' => $currentOwner,
            'ownerOtherBusinesses' => $ownerOtherBusinesses,
            'purgeScopes' => BusinessDataPurgeService::scopes(),
            'purgeCounts' => $purgeService->previewCounts($business),
            'purgeTotalRecords' => BusinessDataPurgeService::totalPreviewCount($purgeService->previewCounts($business)),
        ]);
    }

    public function purgeData(Request $request, Business $business)
    {
        $allowedScopes = array_merge(BusinessDataPurgeService::allScopeKeys(), ['all']);
        $purgeAll = $request->boolean('purge_all');

        $request->validate([
            'scopes' => $purgeAll ? 'nullable|array' : 'required|array|min:1',
            'scopes.*' => ['string', Rule::in($allowedScopes)],
            'confirm_business_name' => 'required|string',
            'purge_all' => 'nullable|boolean',
        ]);

        if (strcasecmp(trim($request->confirm_business_name), trim($business->name)) !== 0) {
            return redirect()
                ->back()
                ->with('error', 'Confirmation name did not match the business name. No data was deleted.');
        }

        $resolvedScopes = BusinessDataPurgeService::resolveScopes(
            $request->input('scopes', []),
            $purgeAll,
        );

        if ($resolvedScopes === []) {
            return redirect()->back()->with('error', 'Select at least one data area to clear, or use Clear ALL data.');
        }

        try {
            $deleted = app(BusinessDataPurgeService::class)->purge(
                $business,
                $resolvedScopes,
                $request->user(),
                $purgeAll,
            );

            $total = array_sum($deleted);
            $labels = $purgeAll
                ? 'all operational data'
                : collect($resolvedScopes)
                    ->map(fn ($s) => BusinessDataPurgeService::scopes()[$s]['label'] ?? $s)
                    ->implode(', ');

            return redirect()
                ->back()
                ->with('success', "Cleared {$total} record(s) for {$business->name} ({$labels}). Login, branches, plan, and staff accounts were kept.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Could not clear data: '.$e->getMessage());
        }
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
        ] + $this->billingRules($request) + $this->ownerAssignmentRules(false) + $this->operationModeRules());

        $plan = Plan::findOrFail($request->plan_id);

        if ((int) $business->plan_id !== (int) $plan->id) {
            $expiryDate = now()->addMonths(max(1, (int) $plan->duration_months));
        } else {
            $expiryDate = $business->expiry_date ?? now()->addMonths(max(1, (int) $plan->duration_months));
        }

        $previousOwnerId = $business->owner_user_id;

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
            'owner_user_id' => $request->input('owner_user_id') ?: $business->owner_user_id,
            'operation_mode' => $request->input('operation_mode', Business::OPERATION_BOTH),
        ], $this->normalizeBillingInput($request)));

        if ($request->filled('owner_user_id') && (int) $request->owner_user_id !== (int) $previousOwnerId) {
            $this->syncBranchOwner($business->fresh());
        }

        AuditLog::log('UPDATE_BUSINESS', "Updated business: {$business->name} — Plan: {$plan->name}, Mode: {$business->operationModeLabel()}, Expiry: {$business->expiry_date->format('Y-m-d')}, Active: {$business->is_active}", $business->id);

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
        AuditLog::log('TOGGLE_BUSINESS_STATUS', "{$status} business: {$business->name}", $business->id);

        $platformSms = app(\App\Services\PlatformSmsService::class);
        $platformMail = app(\App\Services\PlatformMailService::class);
        if ($business->is_active) {
            $platformSms->sendAccountReactivated($business);
            $platformMail->sendAccountReactivated($business);
        } else {
            $platformSms->sendAccountSuspended($business);
            $platformMail->sendAccountSuspended($business);
        }

        return redirect()->back()->with('success', "Business successfully {$status}.");
    }

    public function approveRegistration(Business $business)
    {
        if (! $business->isPendingApproval()) {
            return redirect()->back()->with('error', 'This business is not awaiting approval.');
        }

        $trialDays = max(1, (int) app(\App\Services\PlatformSettingsService::class)->get('default_trial_days', 30));
        $owner = $business->users()->where('role', 'owner')->first();

        $business->update([
            'is_active' => true,
            'pending_approval' => false,
            'expiry_date' => now()->addDays($trialDays),
            'owner_user_id' => $business->owner_user_id ?: $owner?->id,
        ]);

        $this->syncBranchOwner($business->fresh());

        AuditLog::log('APPROVE_BUSINESS_REGISTRATION', "Approved registration for: {$business->name}", $business->id);

        \App\Models\RegistrationFunnelEvent::create([
            'event' => 'registration_approved',
            'metadata' => ['business_id' => $business->id],
            'created_at' => now(),
        ]);

        $loginPassword = null;
        if ($owner) {
            $loginPassword = User::generateRandomPassword(
                max(8, (int) app(\App\Services\PlatformSettingsService::class)->get('min_password_length', 8))
            );
            $owner->update(['password' => Hash::make($loginPassword)]);

            $platformSms = app(\App\Services\PlatformSmsService::class);
            $platformMail = app(\App\Services\PlatformMailService::class);

            if ($business->phone) {
                $platformSms->sendRegistrationApproved($business, $loginPassword);
            }

            $platformMail->sendRegistrationApproved($business, $loginPassword, $owner->email);
        }

        return redirect()->back()->with('success', "{$business->name} has been approved and can now sign in.");
    }

    public function rejectRegistration(Business $business)
    {
        if (! $business->isPendingApproval()) {
            return redirect()->back()->with('error', 'This business is not awaiting approval.');
        }

        $name = $business->name;
        $phone = $business->phone;
        $email = $business->email;

        app(\App\Services\PlatformSmsService::class)->sendRegistrationRejected($phone, $name);
        app(\App\Services\PlatformMailService::class)->sendRegistrationRejected($email, $name);

        $business->users()->delete();

        Branch::query()
            ->where('business_id', $business->id)
            ->each(function (Branch $branch) {
                $branch->businesses()->detach();
                $branch->delete();
            });

        $business->branches()->detach();
        $business->delete();

        AuditLog::log('REJECT_BUSINESS_REGISTRATION', "Rejected registration for: {$name}");

        return redirect()->back()->with('success', "Registration for {$name} was rejected and removed.");
    }

    public function resetOwnerPassword(Request $request, Business $business)
    {
        $owner = $this->resolveBusinessOwner($business);

        if (! $owner) {
            return redirect()->back()->with('error', 'This business has no owner account linked.');
        }

        $minLength = max(6, (int) app(\App\Services\PlatformSettingsService::class)->get('min_password_length', 8));

        $request->validate([
            'password' => "nullable|string|min:{$minLength}|max:64",
            'send_sms' => 'nullable|boolean',
        ]);

        $password = $request->filled('password')
            ? $request->password
            : User::generateRandomPassword($minLength);

        $owner->update(['password' => $password]);

        AuditLog::log(
            'RESET_BUSINESS_OWNER_PASSWORD',
            "Reset login password for owner {$owner->name} (Business: {$business->name})",
            $business->id
        );

        $smsSent = false;
        if ($request->boolean('send_sms') && $business->phone) {
            $smsSent = app(\App\Services\PlatformSmsService::class)->sendPasswordReset($business, $password);
        }

        $emailSent = app(\App\Services\PlatformMailService::class)->sendPasswordReset($business, $password, $owner->email);

        $message = "New password set for {$owner->name}.";
        if ($smsSent) {
            $message .= ' An SMS was sent to the business phone number.';
        }
        if ($emailSent) {
            $message .= ' An email was sent to the business email address.';
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('generated_password', $password)
            ->with('generated_password_for', $owner->name);
    }

    private function resolveBusinessOwner(Business $business): ?User
    {
        $business->loadMissing('ownerUser');

        if ($business->ownerUser) {
            return $business->ownerUser;
        }

        return $business->users()->where('role', 'owner')->first();
    }
}
