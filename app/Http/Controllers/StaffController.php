<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $staffQuery = User::where('business_id', Auth::user()->business_id)
            ->where('role', '!=', 'super_admin');

        $this->scopeStaffToActiveBranch($staffQuery);

        $staff = $staffQuery->with(['role_relation', 'branch'])->get();

        return view('staff.employees.index', compact('staff'));
    }

    public function create()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $roles = Role::where('business_id', Auth::user()->business_id)->get();
        $branches = Branch::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('staff.employees.create', compact('roles', 'branches'));
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $business = Auth::user()->business;
        $maxUsers = $business->plan?->max_users ?? 0;

        if ($maxUsers > 0) {
            $currentStaffCount = User::where('business_id', $business->id)->count();
            if ($currentStaffCount >= $maxUsers) {
                return redirect()->route('employees.index')->with('error', "You have reached the maximum limit of {$maxUsers} users for your current plan.");
            }
        }

        $branchIds = Branch::where('business_id', $business->id)->where('is_active', true)->pluck('id')->all();

        if (empty($branchIds)) {
            return redirect()->route('employees.create')->with(
                'error',
                'Please register at least one branch before adding employees.'
            );
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => ['required', Rule::in($branchIds)],
        ]);

        User::create([
            'business_id' => Auth::user()->business_id,
            'branch_id' => $request->branch_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role_id' => $request->role_id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        return redirect()->route('employees.index')->with('success', 'Staff member added successfully.');
    }

    public function edit(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($employee->business_id != Auth::user()->business_id) {
            abort(403);
        }

        $roles = Role::where('business_id', Auth::user()->business_id)->get();
        $branches = Branch::where('business_id', Auth::user()->business_id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('staff.employees.edit', compact('employee', 'roles', 'branches'));
    }

    public function update(Request $request, User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($employee->business_id != Auth::user()->business_id) {
            abort(403);
        }

        $branchIds = Branch::where('business_id', Auth::user()->business_id)->where('is_active', true)->pluck('id')->all();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'role_id' => 'required|exists:roles,id',
        ];

        if ($employee->role === 'staff') {
            $rules['branch_id'] = ['required', Rule::in($branchIds)];
        }

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
        ];

        if ($employee->role === 'staff') {
            $data['branch_id'] = $request->branch_id;
        }

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:6|confirmed']);
            $data['password'] = $request->password;
        }

        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Staff member updated successfully.');
    }

    public function destroy(User $employee)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($employee->business_id != Auth::user()->business_id) {
            abort(403);
        }
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

        return redirect()->route('employees.index')
            ->with('success', "New password generated for {$employee->name}.")
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

        return redirect()->route('employees.index')
            ->with('success', "{$employee->name} has been {$status}.");
    }

    private function ensureEmployeeAccess(User $employee): void
    {
        if ($employee->business_id != Auth::user()->business_id) {
            abort(403);
        }
    }
}
