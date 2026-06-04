<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\PlatformAdminRole;
use App\Services\PlatformAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformAdminRoleController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index()
    {
        $this->ensurePlatformAdmin('platform_roles');

        $roles = PlatformAdminRole::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.platform_roles.index', compact('roles'));
    }

    public function create()
    {
        $this->ensurePlatformAdmin('platform_roles');

        return view('admin.platform_roles.create');
    }

    public function store(Request $request, PlatformAdminService $platformAdmin)
    {
        $this->ensurePlatformAdmin('platform_roles');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($platformAdmin->allPermissionKeys())],
        ]);

        $slug = $this->uniqueSlug($validated['name']);
        $permissions = $platformAdmin->normalizePermissions($validated['permissions'] ?? []);

        PlatformAdminRole::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'permissions' => $permissions,
            'is_system' => false,
        ]);

        AuditLog::log('CREATE_PLATFORM_ADMIN_ROLE', 'Created admin role '.$validated['name']);

        return redirect()->route('admin.platform-roles.index')->with('success', 'Admin role created.');
    }

    public function edit(PlatformAdminRole $platformRole)
    {
        $this->ensurePlatformAdmin('platform_roles');

        return view('admin.platform_roles.edit', ['role' => $platformRole]);
    }

    public function update(Request $request, PlatformAdminRole $platformRole, PlatformAdminService $platformAdmin)
    {
        $this->ensurePlatformAdmin('platform_roles');

        if ($platformRole->slug === 'full') {
            return back()->with('error', 'The Full Access role cannot be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($platformAdmin->allPermissionKeys())],
        ]);

        $platformRole->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $platformAdmin->normalizePermissions($validated['permissions'] ?? []),
        ]);

        AuditLog::log('UPDATE_PLATFORM_ADMIN_ROLE', 'Updated admin role '.$platformRole->name);

        return redirect()->route('admin.platform-roles.index')->with('success', 'Admin role updated.');
    }

    public function destroy(PlatformAdminRole $platformRole)
    {
        $this->ensurePlatformAdmin('platform_roles');

        if ($platformRole->is_system) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }

        if ($platformRole->users()->exists()) {
            return back()->with('error', 'Remove staff from this role before deleting it.');
        }

        $name = $platformRole->name;
        $platformRole->delete();

        AuditLog::log('DELETE_PLATFORM_ADMIN_ROLE', 'Deleted admin role '.$name);

        return back()->with('success', 'Admin role deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (PlatformAdminRole::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
