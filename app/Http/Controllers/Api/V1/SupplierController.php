<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierController extends ApiController
{
    /** Regions shown on web /suppliers/create */
    private const FORM_REGIONS = [
        'Arusha',
        'Dar es Salaam',
        'Dodoma',
        'Mbeya',
        'Mwanza',
        'Morogoro',
        'Tanga',
        'Kilimanjaro',
        'Zanzibar',
    ];

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        $businessId = $this->apiBusinessId();
        $branchFilterId = $this->branchFilterId($request->user());
        $branches = $this->allBusinessBranches();

        $query = Supplier::query()
            ->where('business_id', $businessId)
            ->with('branch:id,name')
            ->orderBy('name');

        if ($branchFilterId) {
            $query->where('branch_id', $branchFilterId);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->get();

        return $this->success([
            'suppliers' => $suppliers->map(fn (Supplier $s) => $this->supplierPayload($s))->values(),
            'meta' => [
                'branch_filter_id' => $branchFilterId,
                'active_branch_name' => $branchFilterId
                    ? ($this->tenantContext()->branch()?->name ?? Branch::find($branchFilterId)?->name)
                    : null,
                'viewing_all_branches' => $request->user()->seesBusinessWideData() && ! $branchFilterId,
                'can_migrate_from_branch' => $branches->count() > 1,
                'branches' => $branches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'is_default' => (bool) $b->is_default,
                ])->values(),
            ],
        ]);
    }

    public function createForm(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        $branches = $this->writableBranches($request->user());
        $defaultBranchId = $this->defaultBranchIdForForm($request->user());

        return $this->success([
            'branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_default' => (bool) $b->is_default,
            ])->values(),
            'default_branch_id' => $defaultBranchId,
            'can_pick_branch' => $request->user()->seesBusinessWideData() && $branches->count() > 1,
            'regions' => self::FORM_REGIONS,
            'phone_hint' => 'Enter the last 9 digits (e.g. 712345678). Stored as +255…',
            'fields' => [
                'name' => ['required' => true, 'max' => 255],
                'phone' => ['required' => true, 'digits' => 9],
                'email' => ['required' => false, 'max' => 255],
                'region' => ['required' => false],
                'branch_id' => ['required' => $request->user()->seesBusinessWideData() && $branches->count() > 1],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => ['required', 'string', 'max:20'],
                'region' => 'nullable|string|max:100',
                'branch_id' => $this->branchValidationRule($request->user()),
            ]);

            $branchId = $this->resolveBranchIdFromRequest($request);
            if (! $branchId) {
                return $this->error('Select a branch for this supplier.', 422);
            }

            $phone = $this->normalizePhone($validated['phone']);
            if ($phone === null) {
                return $this->error('Phone must be 9 digits (e.g. 712345678).', 422, [
                    'phone' => ['Phone must be 9 digits (e.g. 712345678).'],
                ]);
            }

            $supplier = Supplier::create([
                'business_id' => $this->apiBusinessId(),
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $phone,
                'region' => $validated['region'] ?? null,
            ]);

            $supplier->load('branch:id,name');

            return $this->success([
                'supplier' => $this->supplierPayload($supplier),
            ], 'Supplier registered successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($supplier, $request->user())) {
            return $deny;
        }

        $supplier->load('branch:id,name');

        return $this->success([
            'supplier' => $this->supplierPayload($supplier),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($supplier, $request->user())) {
            return $deny;
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => ['required', 'string', 'max:20'],
                'region' => 'nullable|string|max:100',
                'branch_id' => $this->branchValidationRule($request->user()),
            ]);

            $branchId = $this->resolveBranchIdFromRequest($request) ?: (int) $supplier->branch_id;
            if (! $branchId) {
                return $this->error('Select a branch for this supplier.', 422);
            }

            $phone = $this->normalizePhone($validated['phone']);
            if ($phone === null) {
                return $this->error('Phone must be 9 digits (e.g. 712345678).', 422, [
                    'phone' => ['Phone must be 9 digits (e.g. 712345678).'],
                ]);
            }

            $supplier->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $phone,
                'region' => $validated['region'] ?? null,
                'branch_id' => $branchId,
            ]);

            $supplier->load('branch:id,name');

            return $this->success([
                'supplier' => $this->supplierPayload($supplier),
            ], 'Supplier updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        if ($deny = $this->ensureAccess($supplier, $request->user())) {
            return $deny;
        }

        $supplier->delete();

        return $this->success(null, 'Supplier removed.');
    }

    public function listForBranch(Request $request, Branch $branch): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        if ($deny = $this->ensureBranchInBusiness($branch)) {
            return $deny;
        }

        $suppliers = Supplier::query()
            ->where('business_id', $this->apiBusinessId())
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get();

        return $this->success([
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'suppliers' => $suppliers->map(fn (Supplier $s) => $this->supplierPayload($s, false))->values(),
        ]);
    }

    public function migrateFromBranch(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_suppliers'])) {
            return $deny;
        }

        try {
            $user = $request->user();
            $branchIds = $this->allBusinessBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();

            $validated = $request->validate([
                'from_branch_id' => ['required', 'integer', Rule::in($branchIds)],
                'to_branch_id' => ['required', 'integer', Rule::in($branchIds)],
                'mode' => ['required', Rule::in(['copy', 'move'])],
                'supplier_ids' => ['nullable', 'array'],
                'supplier_ids.*' => ['integer'],
            ]);

            $fromId = (int) $validated['from_branch_id'];
            $toId = (int) $validated['to_branch_id'];

            if ($fromId === $toId) {
                return $this->error('Choose a different destination branch.', 422);
            }

            if (! $user->seesBusinessWideData() && $user->branch_id) {
                if ((int) $user->branch_id !== $toId) {
                    return $this->error('You can only migrate suppliers into your own branch.', 403);
                }
            }

            $query = Supplier::query()
                ->where('business_id', $this->apiBusinessId())
                ->where('branch_id', $fromId);

            if (! empty($validated['supplier_ids'])) {
                $query->whereIn('id', $validated['supplier_ids']);
            }

            $sourceSuppliers = $query->get();
            if ($sourceSuppliers->isEmpty()) {
                return $this->error('No suppliers found on the source branch.', 422);
            }

            $existingNames = Supplier::query()
                ->where('business_id', $this->apiBusinessId())
                ->where('branch_id', $toId)
                ->pluck('name')
                ->filter()
                ->map(fn ($name) => mb_strtolower(trim((string) $name)))
                ->all();

            $copied = 0;
            $moved = 0;
            $skipped = 0;

            foreach ($sourceSuppliers as $supplier) {
                $nameKey = mb_strtolower(trim((string) ($supplier->name ?? '')));
                $alreadyExists = $nameKey !== '' && in_array($nameKey, $existingNames, true);

                if ($validated['mode'] === 'copy') {
                    if ($alreadyExists) {
                        $skipped++;
                        continue;
                    }

                    Supplier::create([
                        'business_id' => $supplier->business_id,
                        'branch_id' => $toId,
                        'name' => $supplier->name,
                        'contact_person' => $supplier->contact_person,
                        'phone' => $supplier->phone,
                        'email' => $supplier->email,
                        'address' => $supplier->address,
                        'region' => $supplier->region,
                    ]);
                    if ($nameKey !== '') {
                        $existingNames[] = $nameKey;
                    }
                    $copied++;
                    continue;
                }

                if ($alreadyExists) {
                    $skipped++;
                    continue;
                }

                $supplier->update(['branch_id' => $toId]);
                if ($nameKey !== '') {
                    $existingNames[] = $nameKey;
                }
                $moved++;
            }

            $parts = [];
            if ($copied > 0) {
                $parts[] = "{$copied} copied";
            }
            if ($moved > 0) {
                $parts[] = "{$moved} moved";
            }
            if ($skipped > 0) {
                $parts[] = "{$skipped} skipped (already on destination)";
            }

            return $this->success([
                'copied' => $copied,
                'moved' => $moved,
                'skipped' => $skipped,
                'from_branch_id' => $fromId,
                'to_branch_id' => $toId,
                'mode' => $validated['mode'],
            ], 'Supplier migrate complete: '.implode(', ', $parts ?: ['nothing changed']).'.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    private function supplierPayload(Supplier $supplier, bool $withBranch = true): array
    {
        $phone = (string) ($supplier->phone ?? '');
        $phoneLocal = str_starts_with($phone, '+255')
            ? substr($phone, 4)
            : (str_starts_with($phone, '255') ? substr($phone, 3) : $phone);

        $payload = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'phone' => $phone,
            'phone_local' => $phoneLocal,
            'email' => $supplier->email,
            'region' => $supplier->region,
            'contact_person' => $supplier->contact_person,
            'address' => $supplier->address,
            'branch_id' => $supplier->branch_id ? (int) $supplier->branch_id : null,
        ];

        if ($withBranch) {
            $payload['branch'] = $supplier->branch ? [
                'id' => $supplier->branch->id,
                'name' => $supplier->branch->name,
            ] : null;
        }

        return $payload;
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[678]\d{8}$/', $digits)) {
            return null;
        }

        return '+255'.$digits;
    }

    private function branchFilterId($user): ?int
    {
        if (! $user->seesBusinessWideData()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        return $this->tenantContext()->branchId();
    }

    private function ensureAccess(Supplier $supplier, $user): ?JsonResponse
    {
        if ((int) $supplier->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $branchFilterId = $this->branchFilterId($user);
        if ($branchFilterId && (int) $supplier->branch_id !== $branchFilterId) {
            return $this->forbidden('Supplier belongs to another branch.');
        }

        return null;
    }

    private function ensureBranchInBusiness(Branch $branch): ?JsonResponse
    {
        $allowed = $this->allBusinessBranches()->contains(fn (Branch $b) => (int) $b->id === (int) $branch->id);

        return $allowed ? null : $this->forbidden('Branch not found for this business.');
    }

    private function allBusinessBranches(): Collection
    {
        $businessId = $this->apiBusinessId();
        $ctx = $this->tenantContext();

        if ($ctx->user()?->role === 'owner') {
            $branches = $ctx->ownerBranches();
            if ($branches->isNotEmpty()) {
                return $branches;
            }
        }

        return Branch::query()
            ->where('is_active', true)
            ->where(function ($query) use ($businessId) {
                $query->where('business_id', $businessId)
                    ->orWhereHas('businesses', fn ($q) => $q->where('businesses.id', $businessId));
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function writableBranches($user): Collection
    {
        $branches = $this->allBusinessBranches();

        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return $branches->where('id', (int) $user->branch_id)->values();
        }

        return $branches;
    }

    private function branchValidationRule($user): array
    {
        $branches = $this->writableBranches($user);

        if ($user->seesBusinessWideData() && $branches->count() > 1) {
            return ['required', Rule::in($branches->pluck('id')->all())];
        }

        return ['nullable'];
    }

    private function resolveBranchIdFromRequest(Request $request): ?int
    {
        $user = $request->user();
        $branches = $this->writableBranches($user);

        if ($branches->isEmpty()) {
            return $this->branchFilterId($user);
        }

        if ($user->seesBusinessWideData() && $branches->count() > 1) {
            $branchId = (int) $request->input('branch_id');
            if (! $branches->contains(fn ($b) => (int) $b->id === $branchId)) {
                return null;
            }

            return $branchId;
        }

        return (int) ($this->branchFilterId($user) ?: $branches->first()->id);
    }

    private function defaultBranchIdForForm($user): ?int
    {
        return $this->branchFilterId($user)
            ?: (int) ($this->writableBranches($user)->first()?->id ?: 0)
            ?: null;
    }
}
