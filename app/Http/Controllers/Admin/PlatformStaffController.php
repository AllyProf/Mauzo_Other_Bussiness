<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\PlatformAdminRole;
use App\Models\User;
use App\Services\PlatformAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PlatformStaffController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(PlatformAdminService $platformAdmin)
    {
        $this->ensurePlatformAdmin('staff');

        $staff = User::query()
            ->with('platformAdminRole')
            ->whereIn('role', ['super_admin', 'platform_staff'])
            ->orderBy('name')
            ->get();

        return view('admin.staff.index', [
            'staff' => $staff,
            'roles' => $platformAdmin->assignableRoles(),
        ]);
    }

    public function store(Request $request, PlatformAdminService $platformAdmin)
    {
        $this->ensurePlatformAdmin('staff');

        $minPassword = max(8, (int) platform_settings('min_password_length', 8));
        $assignableRoleIds = $platformAdmin->assignableRoles()->pluck('id')->all();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'password' => "required|string|min:{$minPassword}|confirmed",
            'platform_admin_role_id' => ['required', Rule::in($assignableRoleIds)],
        ]);

        $role = PlatformAdminRole::findOrFail($validated['platform_admin_role_id']);
        $phone = filled($validated['phone'] ?? null) ? '+255'.$validated['phone'] : null;

        $staffUser = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $phone,
            'password' => Hash::make($validated['password']),
            'role' => 'platform_staff',
            'platform_admin_role_id' => $role->id,
            'platform_admin_role' => $role->slug,
            'is_active' => true,
        ]);

        if ($phone) {
            app(\App\Services\PlatformSmsService::class)->sendStaffWelcome($staffUser, $validated['password']);
            app(\App\Services\PlatformMailService::class)->sendStaffWelcome($staffUser, $validated['password']);
        }

        AuditLog::log('CREATE_PLATFORM_STAFF', 'Created platform staff '.$validated['email'].' ('.$role->name.')');

        return back()->with('success', 'Platform staff member created.');
    }

    public function update(Request $request, User $user, PlatformAdminService $platformAdmin)
    {
        $this->ensurePlatformAdmin('staff');

        if (! in_array($user->role, ['super_admin', 'platform_staff'], true)) {
            abort(404);
        }

        if ($user->id === Auth::id() && ! $request->boolean('is_active', true)) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $assignableRoleIds = $platformAdmin->assignableRoles()->pluck('id')->all();

        $validated = $request->validate([
            'platform_admin_role_id' => ['required', Rule::in($assignableRoleIds)],
            'is_active' => 'nullable|boolean',
        ]);

        if ($user->role === 'super_admin') {
            return back()->with('error', 'Super admin access cannot be changed here.');
        }

        $role = PlatformAdminRole::findOrFail($validated['platform_admin_role_id']);

        $user->update([
            'platform_admin_role_id' => $role->id,
            'platform_admin_role' => $role->slug,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::log('UPDATE_PLATFORM_STAFF', 'Updated platform staff '.$user->email.' ('.$role->name.')');

        return back()->with('success', 'Staff member updated.');
    }
}
