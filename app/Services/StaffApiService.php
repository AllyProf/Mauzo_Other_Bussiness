<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffApiService
{
    public function __construct(
        private BusinessStaffSmsService $staffSms,
        private BusinessStaffMailService $staffMail,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function listRoles(int $businessId): array
    {
        $roles = Role::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get();

        return [
            'roles' => $roles->map(fn (Role $role) => $this->formatRole($role))->values()->all(),
            'meta' => [
                'roles_count' => $roles->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRoleForm(): array
    {
        $groups = collect(config('permissions.groups', []))
            ->map(function (array $permissions, string $groupName) {
                return [
                    'group' => $groupName,
                    'permissions' => collect($permissions)->map(fn ($label, $key) => [
                        'key' => $key,
                        'label' => $label,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $presets = collect(config('permissions.presets', []))
            ->map(fn (array $permissions, string $name) => [
                'name' => $name,
                'permissions' => array_values($permissions),
            ])
            ->values()
            ->all();

        return [
            'permission_groups' => $groups,
            'presets' => $presets,
            'fields' => [
                'name' => ['required' => true, 'max' => 255],
                'permissions' => ['required' => false, 'type' => 'array'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeRole(int $businessId, array $payload): array
    {
        $validated = validator($payload, [
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ])->validate();

        $role = Role::create([
            'business_id' => $businessId,
            'name' => $validated['name'],
            'permissions' => $this->normalizePermissions($validated['permissions'] ?? []),
        ]);

        return [
            'role' => $this->formatRole($role),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listEmployees(User $actor, int $businessId, ?int $branchFilterId): array
    {
        $business = Business::findOrFail($businessId);
        $query = User::query()
            ->where('business_id', $businessId)
            ->where('role', '!=', 'super_admin')
            ->with(['role_relation', 'branch'])
            ->orderBy('name');

        $this->scopeEmployeesToBranch($query, $actor, $branchFilterId);

        $staff = $query->get();
        $maxUsers = (int) ($business->plan?->max_users ?? 0);
        $currentUsers = User::where('business_id', $businessId)->count();
        $assignableBranches = $this->assignableBranches($actor, $businessId);

        return [
            'employees' => $staff->map(fn (User $employee) => $this->formatEmployee($employee))->values()->all(),
            'meta' => [
                'employees_count' => $staff->count(),
                'branch_filter_id' => $branchFilterId,
                'viewing_all_branches' => $actor->seesBusinessWideData() && ! $branchFilterId,
                'has_branches' => $assignableBranches->isNotEmpty(),
                'has_roles' => Role::where('business_id', $businessId)->exists(),
                'can_add_employee' => $this->canAddEmployee($business, $currentUsers, $assignableBranches),
                'staff_limit' => [
                    'max_users' => $maxUsers,
                    'current_users' => $currentUsers,
                    'remaining' => $maxUsers > 0 ? max(0, $maxUsers - $currentUsers) : null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createEmployeeForm(User $actor, int $businessId, ?int $branchFilterId, ?int $selectedBranchId = null): array
    {
        $business = Business::findOrFail($businessId);
        $assignableBranches = $this->assignableBranches($actor, $businessId)
            ->filter(fn (Branch $branch) => $this->branchServesBusiness((int) $branch->id, $businessId))
            ->values();

        $roles = Role::where('business_id', $businessId)->orderBy('name')->get();
        $defaultBranchId = $selectedBranchId
            ?: $branchFilterId
            ?: ($assignableBranches->first()?->id ? (int) $assignableBranches->first()->id : null);

        $importedTypesByBranch = $this->importedTypesByBranch($business, $assignableBranches);
        $importedTypes = $defaultBranchId ? ($importedTypesByBranch[$defaultBranchId] ?? []) : [];

        return [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'branches' => $assignableBranches->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_default' => (bool) $branch->is_default,
            ])->values()->all(),
            'default_branch_id' => $defaultBranchId,
            'can_pick_branch' => $actor->seesBusinessWideData() && $assignableBranches->count() > 1,
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values()->all(),
            'imported_types_by_branch' => $importedTypesByBranch,
            'imported_types' => $importedTypes,
            'default_business_type_keys' => count($importedTypes) === 1
                ? [($importedTypes[0]['key'] ?? null)]
                : [],
            'phone_hint' => 'Enter the last 9 digits (e.g. 712345678). Stored as +255…',
            'fields' => [
                'branch_id' => ['required' => true],
                'business_type_keys' => ['required' => true, 'type' => 'array', 'min' => 1],
                'name' => ['required' => true, 'max' => 255],
                'email' => ['required' => true, 'email' => true],
                'phone' => ['required' => false, 'digits' => 9],
                'role_id' => ['required' => true],
                'password' => ['required' => true, 'min' => 6],
                'password_confirmation' => ['required' => true, 'min' => 6],
            ],
            'blockers' => [
                'no_branches' => $assignableBranches->isEmpty(),
                'no_roles' => $roles->isEmpty(),
                'no_business_types_for_branch' => $defaultBranchId
                    && empty($importedTypesByBranch[$defaultBranchId] ?? []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function storeEmployee(User $actor, int $businessId, array $payload): array
    {
        $business = Business::findOrFail($businessId);
        $assignableBranchIds = $this->assignableBranches($actor, $businessId)
            ->filter(fn (Branch $branch) => $this->branchServesBusiness((int) $branch->id, $businessId))
            ->pluck('id')
            ->all();

        if ($assignableBranchIds === []) {
            throw ValidationException::withMessages([
                'branch_id' => 'No branches are linked to this business yet.',
            ]);
        }

        $branchId = (int) ($payload['branch_id'] ?? 0);
        $allowedTypeKeys = $this->allowedBusinessTypeKeysForBranch($business, $branchId);

        if ($allowedTypeKeys === []) {
            throw ValidationException::withMessages([
                'business_type_keys' => 'Import at least one business type for the selected branch on Categories before adding employees.',
            ]);
        }

        $roleIds = Role::where('business_id', $businessId)->pluck('id')->all();
        if ($roleIds === []) {
            throw ValidationException::withMessages([
                'role_id' => 'Create at least one role before adding employees.',
            ]);
        }

        $validated = validator($payload, [
            'branch_id' => ['required', Rule::in($assignableBranchIds)],
            'business_type_keys' => ['required', 'array', 'min:1'],
            'business_type_keys.*' => ['required', 'string', Rule::in($allowedTypeKeys)],
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
            'role_id' => ['required', Rule::in($roleIds)],
            'password' => 'required|string|min:6|confirmed',
        ])->validate();

        if (! $this->branchServesBusiness($branchId, $businessId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch is not linked to the active business.',
            ]);
        }

        $maxUsers = (int) ($business->plan?->max_users ?? 0);
        $currentUsers = User::where('business_id', $businessId)->count();
        if ($maxUsers > 0 && $currentUsers >= $maxUsers) {
            throw ValidationException::withMessages([
                'staff_limit' => "You have reached the maximum limit of {$maxUsers} users for {$business->name}.",
            ]);
        }

        $businessTypeKeys = $this->normalizeBusinessTypeKeys($validated['business_type_keys'], $allowedTypeKeys);
        $phone = filled($validated['phone'] ?? null) ? '+255'.$validated['phone'] : null;
        $plainPassword = $validated['password'];

        $employee = User::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phone,
            'password' => $plainPassword,
            'role_id' => $validated['role_id'],
            'role' => 'staff',
            'is_active' => true,
        ]);
        $employee->syncBusinessTypeAssignments($businessTypeKeys);
        $employee->save();
        $employee->load(['role_relation', 'branch']);

        $smsSent = false;
        $emailSent = false;
        if ($phone) {
            $smsSent = $this->staffSms->sendStaffWelcome($business, $actor, $employee, $plainPassword);
        }
        if (filled($employee->email)) {
            $emailSent = $this->staffMail->sendStaffWelcome($business, $employee, $plainPassword);
        }

        return [
            'employee' => $this->formatEmployee($employee),
            'notifications' => [
                'sms_sent' => $smsSent,
                'email_sent' => $emailSent,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateEmployee(User $actor, int $businessId, User $employee, array $payload): array
    {
        $this->ensureEmployeeAccess($actor, $employee, $businessId);

        $branchId = (int) ($payload['branch_id'] ?? $employee->branch_id);
        $business = Business::findOrFail($businessId);
        $assignableBranchIds = $this->assignableBranches($actor, $businessId)
            ->filter(fn (Branch $branch) => $this->branchServesBusiness((int) $branch->id, $businessId))
            ->pluck('id')
            ->all();
        $allowedTypeKeys = $this->allowedBusinessTypeKeysForBranch($business, $branchId);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$employee->id,
            'phone' => ['nullable', 'string', 'max:9', 'regex:/^[678]\d{8}$/'],
        ];

        if ($employee->role === 'staff') {
            $rules['branch_id'] = ['required', Rule::in($assignableBranchIds)];
            if (! empty($allowedTypeKeys)) {
                $rules['business_type_keys'] = ['required', 'array', 'min:1'];
                $rules['business_type_keys.*'] = ['required', 'string', Rule::in($allowedTypeKeys)];
            }
        }

        $validated = validator($payload, $rules)->validate();

        if ($employee->role === 'staff' && ! $this->branchServesBusiness($branchId, $businessId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch is not linked to the active business.',
            ]);
        }

        $roleIds = Role::where('business_id', $businessId)->pluck('id')->all();
        validator($payload, ['role_id' => ['required', Rule::in($roleIds)]])->validate();

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => filled($validated['phone'] ?? null) ? '+255'.$validated['phone'] : null,
            'role_id' => $payload['role_id'],
        ];

        if ($employee->role === 'staff') {
            $data['branch_id'] = $branchId;
            if (! empty($allowedTypeKeys)) {
                $keys = $this->normalizeBusinessTypeKeys($validated['business_type_keys'] ?? [], $allowedTypeKeys);
                $data['business_type_keys'] = $keys;
                $data['business_type_key'] = $keys[0] ?? null;
            }
        }

        $smsSent = false;
        $emailSent = false;
        if (! empty($payload['password']) && is_string($payload['password'])) {
            validator($payload, ['password' => 'string|min:6|confirmed'])->validate();
            $data['password'] = $payload['password'];
        }

        $employee->update($data);
        $employee->refresh()->load(['role_relation', 'branch', 'business']);

        if (! empty($payload['password'])) {
            if (filled($employee->phone)) {
                $smsSent = $this->staffSms->sendPasswordReset($business, $actor, $employee, $payload['password']);
            }
            if (filled($employee->email)) {
                $emailSent = $this->staffMail->sendPasswordReset($business, $employee, $payload['password']);
            }
        }

        return [
            'employee' => $this->formatEmployee($employee),
            'notifications' => [
                'password_sms_sent' => $smsSent,
                'password_email_sent' => $emailSent,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resetPassword(User $actor, int $businessId, User $employee): array
    {
        $this->ensureEmployeeAccess($actor, $employee, $businessId);

        if (in_array($employee->role, ['owner', 'super_admin'], true)) {
            throw ValidationException::withMessages([
                'employee' => 'Cannot reset the business owner password from this endpoint.',
            ]);
        }

        $password = User::generateRandomPassword();
        $employee->update(['password' => $password]);

        $business = $employee->business ?? Business::findOrFail($businessId);
        $smsSent = $this->staffSms->sendPasswordReset($business, $actor, $employee->fresh(), $password);
        $emailSent = filled($employee->email)
            ? $this->staffMail->sendPasswordReset($business, $employee->fresh(), $password)
            : false;

        return [
            'employee' => $this->formatEmployee($employee->fresh()->load(['role_relation', 'branch'])),
            'generated_password' => $password,
            'notifications' => [
                'sms_sent' => $smsSent,
                'email_sent' => $emailSent,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleStatus(User $actor, int $businessId, User $employee): array
    {
        $this->ensureEmployeeAccess($actor, $employee, $businessId);

        if ($employee->id === $actor->id) {
            throw ValidationException::withMessages([
                'employee' => 'You cannot deactivate your own account.',
            ]);
        }

        if (in_array($employee->role, ['owner', 'super_admin'], true)) {
            throw ValidationException::withMessages([
                'employee' => 'This account cannot be deactivated.',
            ]);
        }

        $employee->update(['is_active' => ! $employee->is_active]);
        $newStatus = $employee->is_active ? 'activated' : 'deactivated';

        $business = $employee->business ?? Business::findOrFail($businessId);
        try {
            if ($employee->is_active) {
                $this->staffSms->sendAccountActivated($business, $actor, $employee);
                $this->staffMail->sendAccountActivated($business, $employee);
            } else {
                $this->staffSms->sendAccountDeactivated($business, $actor, $employee);
                $this->staffMail->sendAccountDeactivated($business, $employee);
            }
        } catch (\Throwable) {
        }

        return [
            'employee' => $this->formatEmployee($employee->fresh()->load(['role_relation', 'branch'])),
            'status' => $newStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroyEmployee(User $actor, int $businessId, User $employee): array
    {
        $this->ensureEmployeeAccess($actor, $employee, $businessId);

        if ($employee->id === $actor->id) {
            throw ValidationException::withMessages([
                'employee' => 'You cannot delete yourself.',
            ]);
        }

        if (in_array($employee->role, ['owner', 'super_admin'], true)) {
            throw ValidationException::withMessages([
                'employee' => 'Owner and super-admin accounts cannot be deleted.',
            ]);
        }

        $deletedId = (int) $employee->id;
        $deletedName = $employee->name;
        $employee->delete();

        return [
            'deleted_employee_id' => $deletedId,
            'deleted_employee_name' => $deletedName,
        ];
    }

    private function ensureEmployeeAccess(User $actor, User $employee, int $businessId): void
    {
        if ((int) $employee->business_id !== $businessId) {
            throw ValidationException::withMessages([
                'employee' => 'Employee not found for this business.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRole(Role $role): array
    {
        $labels = collect(config('permissions.groups', []))->flatMap(fn ($group) => $group);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => array_values($role->permissions ?? []),
            'permission_labels' => collect($role->permissions ?? [])
                ->map(fn (string $key) => [
                    'key' => $key,
                    'label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key)),
                ])
                ->values()
                ->all(),
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEmployee(User $employee): array
    {
        $employee->loadMissing(['role_relation', 'branch', 'business']);

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'role' => $employee->role,
            'role_id' => $employee->role_id,
            'role_name' => $employee->displayRoleName(),
            'branch_id' => $employee->branch_id ? (int) $employee->branch_id : null,
            'branch_name' => $employee->branch?->name,
            'business_type_keys' => $employee->assignedBusinessTypeKeys(),
            'business_type_labels' => $employee->displayBusinessTypeLabels(),
            'is_active' => $employee->isActiveAccount(),
            'is_owner' => $employee->role === 'owner',
            'can_reset_password' => ! in_array($employee->role, ['owner', 'super_admin'], true),
            'can_toggle_status' => ! in_array($employee->role, ['owner', 'super_admin'], true),
            'created_at' => $employee->created_at?->toIso8601String(),
        ];
    }

    public function branchFilterId(User $user, ApiTenantContext $ctx): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $ctx->branchId();
    }

    /**
     * @param  list<string>  $submitted
     * @return list<string>
     */
    public function normalizePermissions(array $submitted): array
    {
        $allowed = collect(config('permissions.groups', []))
            ->flatMap(fn ($group) => array_keys($group))
            ->unique()
            ->values()
            ->all();

        return array_values(array_intersect($allowed, $submitted));
    }

    private function scopeEmployeesToBranch(Builder $query, User $actor, ?int $branchFilterId): void
    {
        if (! $actor->seesBusinessWideData() && $actor->branch_id) {
            $query->where('branch_id', (int) $actor->branch_id);

            return;
        }

        if ($branchFilterId) {
            $query->where('branch_id', $branchFilterId);
        }
    }

    private function canAddEmployee(Business $business, int $currentUsers, Collection $assignableBranches): bool
    {
        $maxUsers = (int) ($business->plan?->max_users ?? 0);

        if ($assignableBranches->isEmpty()) {
            return false;
        }

        if (! Role::where('business_id', $business->id)->exists()) {
            return false;
        }

        return $maxUsers === 0 || $currentUsers < $maxUsers;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function assignableBranches(User $user, int $businessId): Collection
    {
        if ($user->role === 'owner') {
            return Branch::query()
                ->where('is_active', true)
                ->where(function ($query) use ($user, $businessId) {
                    $query->where('owner_user_id', $user->id)
                        ->orWhere('business_id', $businessId)
                        ->orWhereHas('businesses', fn ($businessQuery) => $businessQuery->where('businesses.id', $businessId));
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

    /**
     * @return Collection<int, Branch>
     */
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

    private function branchServesBusiness(int $branchId, int $businessId): bool
    {
        return $this->businessesForBranch($branchId)->contains('id', $businessId);
    }

    /**
     * @return Collection<int, Business>
     */
    private function businessesForBranch(int $branchId): Collection
    {
        $branch = Branch::query()
            ->with(['businesses' => fn ($query) => $query->where('businesses.is_active', true)->orderBy('name')])
            ->find($branchId);

        if (! $branch) {
            return collect();
        }

        if ($branch->businesses->isNotEmpty()) {
            return $branch->businesses->values();
        }

        if ($branch->business_id) {
            return Business::query()
                ->where('id', $branch->business_id)
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return array<int, list<array{key: string, label: string, categories: list<string>}>>
     */
    private function importedTypesByBranch(Business $business, Collection $branches): array
    {
        $map = [];

        foreach ($branches as $branch) {
            $map[(int) $branch->id] = $business->importedTypesForBranch((int) $branch->id);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function allowedBusinessTypeKeysForBranch(Business $business, int $branchId): array
    {
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
