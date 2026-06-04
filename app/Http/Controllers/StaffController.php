<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Models\Role;
use App\Services\ActiveBusinessService;
use App\Services\BusinessStaffMailService;
use App\Services\BusinessStaffSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(
        private BusinessStaffSmsService $staffSms,
        private BusinessStaffMailService $staffMail,
    ) {
    }

    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $staffQuery = User::where('business_id', $this->currentBusinessId())
            ->where('role', '!=', 'super_admin');

        $this->scopeStaffToActiveBranch($staffQuery);

        $staff = $staffQuery->with(['role_relation', 'branch'])->get();

        return view('staff.employees.index', compact('staff'));
    }

    public function create()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $business = $this->currentBusiness();
        $businessId = $this->currentBusinessId();
        $assignableBranches = $this->assignableBranches()
            ->filter(fn (Branch $branch) => $this->branchServesBusiness($branch->id, $businessId))
            ->values();
        $selectedBranchId = (int) old('branch_id', active_branch_id() ?? $assignableBranches->first()?->id);
        $roles = $this->rolesForBusiness($businessId);
        $importedTypesByBranch = $this->importedTypesByBranch($business, $assignableBranches);
        $importedTypes = $importedTypesByBranch[$selectedBranchId] ?? [];
        $defaultBusinessTypeKeys = array_values(array_filter(old(
            'business_type_keys',
            count($importedTypes) === 1 ? [($importedTypes[0]['key'] ?? null)] : []
        )));

        return view('staff.employees.create', compact(
            'business',
            'assignableBranches',
            'roles',
            'selectedBranchId',
            'importedTypes',
            'importedTypesByBranch',
            'defaultBusinessTypeKeys'
        ));
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $businessId = $this->currentBusinessId();
        $this->ensureCanAssignToBusiness($businessId);

        $assignableBranchIds = $this->assignableBranches()
            ->filter(fn (Branch $branch) => $this->branchServesBusiness($branch->id, $businessId))
            ->pluck('id')
            ->all();

        $branchId = (int) $request->branch_id;
        $business = Business::findOrFail($businessId);
        $allowedTypeKeys = $this->allowedBusinessTypeKeysForBranch($business, $branchId);

        if (empty($allowedTypeKeys)) {
            return redirect()->route('employees.create')->withInput()->with(
                'error',
                'Import at least one business type for the selected branch on Categories before adding employees.'
            );
        }

        $request->validate([
            'branch_id' => ['required', Rule::in($assignableBranchIds)],
            'business_type_keys' => ['required', 'array', 'min:1'],
            'business_type_keys.*' => ['required', 'string', Rule::in($allowedTypeKeys)],
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (! $this->branchServesBusiness($branchId, $businessId)) {
            return redirect()->route('employees.create')
                ->withInput()
                ->withErrors(['branch_id' => 'The selected branch is not linked to the active business.']);
        }

        $business = Business::findOrFail($businessId);
        $maxUsers = $business->plan?->max_users ?? 0;

        if ($maxUsers > 0) {
            $currentStaffCount = User::where('business_id', $business->id)->count();
            if ($currentStaffCount >= $maxUsers) {
                return redirect()->route('employees.index')->with('error', "You have reached the maximum limit of {$maxUsers} users for {$business->name}.");
            }
        }

        $roleIds = $this->rolesForBusiness($businessId)->pluck('id')->all();

        if (empty($roleIds)) {
            return redirect()->route('employees.create')->with(
                'error',
                'Please create at least one role for the selected business before adding employees.'
            );
        }

        $request->validate([
            'role_id' => ['required', Rule::in($roleIds)],
        ]);

        $businessTypeKeys = $this->normalizeBusinessTypeKeys($request->input('business_type_keys', []), $allowedTypeKeys);
        $phone = filled($request->phone) ? '+255'.$request->phone : null;
        $plainPassword = $request->password;

        $employee = User::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => $plainPassword,
            'role_id' => $request->role_id,
            'role' => 'staff',
            'is_active' => true,
        ]);
        $employee->syncBusinessTypeAssignments($businessTypeKeys);
        $employee->save();

        $smsSent = false;
        $emailSent = false;
        if ($phone) {
            $smsSent = $this->staffSms->sendStaffWelcome($business, Auth::user(), $employee, $plainPassword);
        }
        if (filled($employee->email)) {
            $emailSent = $this->staffMail->sendStaffWelcome($business, $employee, $plainPassword);
        }

        $message = 'Staff member added successfully.';
        if ($phone && $smsSent) {
            $message .= ' Login details were sent by SMS.';
        } elseif ($phone && ! $smsSent) {
            $message .= ' SMS could not be sent — check staff SMS settings or your SMS quota.';
        }
        if (filled($employee->email) && $emailSent) {
            $message .= ' Login details were also sent by email.';
        }

        return redirect()->route('employees.index')->with('success', $message);
    }

    public function edit(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        $this->ensureCanManageEmployee($employee);

        $business = $employee->business ?? Business::find($employee->business_id);
        $businessId = (int) $employee->business_id;
        $assignableBranches = $this->assignableBranches()
            ->filter(fn (Branch $branch) => $this->branchServesBusiness($branch->id, $businessId))
            ->values();
        $selectedBranchId = (int) old('branch_id', $employee->branch_id ?: active_branch_id() ?? $assignableBranches->first()?->id);
        $roles = $this->rolesForBusiness($businessId);
        $importedTypesByBranch = $this->importedTypesByBranch($business, $assignableBranches);
        $importedTypes = $importedTypesByBranch[$selectedBranchId] ?? [];
        $defaultBusinessTypeKeys = array_values(array_filter(old(
            'business_type_keys',
            $employee->assignedBusinessTypeKeys()
        )));

        return view('staff.employees.edit', compact(
            'employee',
            'business',
            'assignableBranches',
            'roles',
            'selectedBranchId',
            'importedTypes',
            'importedTypesByBranch',
            'defaultBusinessTypeKeys'
        ));
    }

    public function update(Request $request, User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        $this->ensureCanManageEmployee($employee);

        $businessId = (int) $employee->business_id;
        $assignableBranchIds = $this->assignableBranches()
            ->filter(fn (Branch $branch) => $this->branchServesBusiness($branch->id, $businessId))
            ->pluck('id')
            ->all();
        $branchId = (int) $request->input('branch_id', $employee->branch_id);

        $business = $employee->business ?? Business::find($businessId);
        $allowedTypeKeys = $this->allowedBusinessTypeKeysForBranch($business, $branchId);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
        ];

        if ($employee->role === 'staff') {
            $rules['branch_id'] = ['required', Rule::in($assignableBranchIds)];

            if (! empty($allowedTypeKeys)) {
                $rules['business_type_keys'] = ['required', 'array', 'min:1'];
                $rules['business_type_keys.*'] = ['required', 'string', Rule::in($allowedTypeKeys)];
            }
        }

        $request->validate($rules);

        if ($employee->role === 'staff' && ! $this->branchServesBusiness($branchId, $businessId)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['branch_id' => 'The selected branch is not linked to this employee\'s business.']);
        }

        $roleIds = $this->rolesForBusiness($businessId)->pluck('id')->all();

        $request->validate([
            'role_id' => ['required', Rule::in($roleIds)],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => filled($request->phone) ? '+255'.$request->phone : null,
            'role_id' => $request->role_id,
        ];

        if ($employee->role === 'staff') {
            $data['branch_id'] = $branchId;

            if (! empty($allowedTypeKeys)) {
                $keys = $this->normalizeBusinessTypeKeys($request->input('business_type_keys', []), $allowedTypeKeys);
                $data['business_type_keys'] = $keys;
                $data['business_type_key'] = $keys[0] ?? null;
            }
        }

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:6|confirmed']);
            $data['password'] = $request->password;
        }

        $employee->update($data);

        $message = 'Staff member updated successfully.';
        if ($request->filled('password') && filled($employee->phone)) {
            $smsSent = $this->staffSms->sendPasswordReset(
                $business ?? Business::find($businessId),
                Auth::user(),
                $employee->fresh(),
                $request->password
            );
            if ($smsSent) {
                $message .= ' New password sent by SMS.';
            }
        }
        if ($request->filled('password') && filled($employee->email)) {
            if ($this->staffMail->sendPasswordReset($business ?? Business::find($businessId), $employee->fresh(), $request->password)) {
                $message .= ' New password sent by email.';
            }
        }

        return redirect()->route('employees.index')->with('success', $message);
    }

    public function destroy(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        $this->ensureCanManageEmployee($employee);
        if ($employee->id == Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete yourself.');
        }
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Staff member removed.');
    }

    public function resetPassword(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        $this->ensureEmployeeAccess($employee);

        if (in_array($employee->role, ['owner', 'super_admin'], true)) {
            return redirect()->route('employees.index')
                ->with('error', 'Reset the business owner password from the edit page.');
        }

        $password = User::generateRandomPassword();

        $employee->update(['password' => $password]);

        $business = $employee->business ?? Business::find($employee->business_id);
        $smsSent = $business
            ? $this->staffSms->sendPasswordReset($business, Auth::user(), $employee->fresh(), $password)
            : false;
        $emailSent = $business && filled($employee->email)
            ? $this->staffMail->sendPasswordReset($business, $employee->fresh(), $password)
            : false;

        $message = "New password generated for {$employee->name}.";
        if ($smsSent) {
            $message .= ' An SMS was sent to the staff phone number.';
        }
        if ($emailSent) {
            $message .= ' An email was sent to the staff email address.';
        }

        return redirect()->route('employees.index')
            ->with('success', $message)
            ->with('generated_password', $password)
            ->with('generated_password_for', $employee->name);
    }

    public function toggleStatus(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        $this->ensureEmployeeAccess($employee);

        if ($employee->id === Auth::id()) {
            return redirect()->route('employees.index')
                ->with('error', 'You cannot deactivate your own account.');
        }

        if (in_array($employee->role, ['owner', 'super_admin'], true)) {
            return redirect()->route('employees.index')
                ->with('error', 'This account cannot be deactivated.');
        }

        $employee->update(['is_active' => ! $employee->is_active]);

        $status = $employee->is_active ? 'activated' : 'deactivated';
        $business = $employee->business ?? Business::find($employee->business_id);

        if ($business) {
            if ($employee->is_active) {
                $this->staffSms->sendAccountActivated($business, Auth::user(), $employee);
                $this->staffMail->sendAccountActivated($business, $employee);
            } else {
                $this->staffSms->sendAccountDeactivated($business, Auth::user(), $employee);
                $this->staffMail->sendAccountDeactivated($business, $employee);
            }
        }

        return redirect()->route('employees.index')
            ->with('success', "{$employee->name} has been {$status}.");
    }

    private function ensureEmployeeAccess(User $employee): void
    {
        $this->ensureCanManageEmployee($employee);
    }

    private function assignableBusinesses(): Collection
    {
        $user = auth()->user();

        if ($user->role === 'owner') {
            return app(ActiveBusinessService::class)->businesses();
        }

        if ($user->business_id) {
            return Business::query()
                ->where('id', $user->business_id)
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }

    private function assignableBusinessIds(): array
    {
        return $this->assignableBusinesses()->pluck('id')->all();
    }

    private function ensureCanAssignToBusiness(int $businessId): void
    {
        if (! in_array($businessId, $this->assignableBusinessIds(), true)) {
            abort(403, 'You do not have access to assign staff to this business.');
        }
    }

    private function ensureCanManageEmployee(User $employee): void
    {
        if (! in_array((int) $employee->business_id, $this->assignableBusinessIds(), true)) {
            abort(403);
        }
    }

    private function assignableBranches(): Collection
    {
        $user = auth()->user();
        $businessIds = $this->assignableBusinessIds();

        if ($user->role === 'owner' && ! empty($businessIds)) {
            return Branch::query()
                ->where('is_active', true)
                ->where(function ($query) use ($user, $businessIds) {
                    $query->where('owner_user_id', $user->id)
                        ->orWhereIn('business_id', $businessIds)
                        ->orWhereHas('businesses', fn ($businessQuery) => $businessQuery->whereIn('businesses.id', $businessIds));
                })
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->unique('id')
                ->values();
        }

        if ($user->business_id) {
            return $this->branchesForBusiness((int) $user->business_id);
        }

        return collect();
    }

    private function businessesForBranch(int $branchId): Collection
    {
        $branch = Branch::query()
            ->with(['businesses' => fn ($query) => $query->where('businesses.is_active', true)->orderBy('name')])
            ->find($branchId);

        if (! $branch) {
            return collect();
        }

        $assignableIds = $this->assignableBusinessIds();

        $businesses = $branch->businesses
            ->filter(fn (Business $business) => in_array($business->id, $assignableIds, true))
            ->values();

        if ($businesses->isNotEmpty()) {
            return $businesses;
        }

        if ($branch->business_id && in_array((int) $branch->business_id, $assignableIds, true)) {
            return Business::query()
                ->where('id', $branch->business_id)
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }

    private function branchServesBusiness(int $branchId, int $businessId): bool
    {
        return $this->businessesForBranch($branchId)->contains('id', $businessId);
    }

    private function branchesForBusiness(int $businessId): Collection
    {
        return Branch::query()
            ->where('is_active', true)
            ->where(function ($query) use ($businessId) {
                $query->whereHas('businesses', fn ($businessQuery) => $businessQuery->where('businesses.id', $businessId))
                    ->orWhere('business_id', $businessId);
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function rolesForBusiness(int $businessId): Collection
    {
        return Role::where('business_id', $businessId)->orderBy('name')->get();
    }

    /**
     * @return array<int, list<array{key: string, label: string, categories: list<string>}>>
     */
    private function importedTypesByBranch(?Business $business, Collection $branches): array
    {
        if (! $business) {
            return [];
        }

        $map = [];

        foreach ($branches as $branch) {
            $map[(int) $branch->id] = $business->importedTypesForBranch((int) $branch->id);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function allowedBusinessTypeKeysForBranch(?Business $business, int $branchId): array
    {
        if (! $business) {
            return [];
        }

        return collect($business->importedTypesForBranch($branchId))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $allowedTypeKeys
     * @return list<string>
     */
    private function normalizeBusinessTypeKeys(array $submittedKeys, array $allowedTypeKeys): array
    {
        return collect($submittedKeys)
            ->filter(fn ($key) => is_string($key) && $key !== '' && in_array($key, $allowedTypeKeys, true))
            ->unique()
            ->values()
            ->all();
    }
}
