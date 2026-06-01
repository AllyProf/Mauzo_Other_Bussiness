<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $roles = Role::where('business_id', Auth::user()->business_id)->get();
        return view('staff.roles.index', compact('roles'));
    }

    public function create()
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        return view('staff.roles.create');
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Role::create([
            'business_id' => Auth::user()->business_id,
            'name' => $request->name,
            'permissions' => $this->normalizePermissions($request->input('permissions', [])),
        ]);

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($role->business_id != Auth::user()->business_id) abort(403);
        return view('staff.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($role->business_id != Auth::user()->business_id) abort(403);

        $request->validate(['name' => 'required|string|max:255']);

        $role->update([
            'name' => $request->name,
            'permissions' => $this->normalizePermissions($request->input('permissions', [])),
        ]);

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    private function normalizePermissions(?array $submitted): array
    {
        $allowed = collect(config('permissions.groups', []))
            ->flatMap(fn ($group) => array_keys($group))
            ->unique()
            ->values()
            ->all();

        return array_values(array_intersect($allowed, $submitted ?? []));
    }

    public function destroy(Role $role)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage_staff');
        if ($role->business_id != Auth::user()->business_id) abort(403);
        $role->delete();
        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }
}
