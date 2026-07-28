<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Packaging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackagingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'view_inventory'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $branchFilterId = $this->branchFilterId($request->user());
        $viewingAllBranches = $request->user()->seesBusinessWideData() && ! $branchFilterId;

        if ($branchFilterId) {
            $importedTypes = $business->importedTypesForBranch($branchFilterId);
            $branchTypeKeys = collect($importedTypes)->pluck('key')->filter()->values()->all();
        } else {
            $importedTypes = $business->categoryBusinessTypesList();
            $branchTypeKeys = collect($importedTypes)->pluck('key')->filter()->values()->all();
        }

        $businessTemplates = category_templates();
        $packagingTemplates = packaging_templates();

        $allPackagings = Packaging::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        $packagings = $branchFilterId
            ? $allPackagings->filter(function (Packaging $packaging) use ($branchTypeKeys) {
                $key = $packaging->source_business_type_key ?: 'other';

                return $key !== 'other' && in_array($key, $branchTypeKeys, true);
            })->values()
            : $allPackagings;

        $packagingCountsByType = $packagings
            ->groupBy(fn (Packaging $packaging) => $packaging->source_business_type_key ?: 'other')
            ->map->count();

        $packagingTabs = collect($packagings)
            ->pluck('source_business_type_key')
            ->filter()
            ->unique()
            ->map(function (string $key) use ($importedTypes, $businessTemplates, $packagingCountsByType) {
                $configured = collect($importedTypes)->firstWhere('key', $key);

                return [
                    'key' => $key,
                    'label' => $configured['label']
                        ?? $businessTemplates[$key]['label']
                        ?? 'Business',
                    'count' => (int) ($packagingCountsByType[$key] ?? 0),
                    'is_custom' => str_starts_with($key, 'custom:'),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        $typesUsed = $business->categoryBusinessTypesUsed();
        $typesLimit = $business->maxBusinessTypesAllowed();

        return $this->success([
            'packagings' => $packagings->map(fn (Packaging $p) => $this->packagingPayload($p))->values(),
            'meta' => [
                'branch_filter_id' => $branchFilterId,
                'active_branch_name' => $branchFilterId
                    ? ($this->tenantContext()->branch()?->name ?? Branch::find($branchFilterId)?->name)
                    : null,
                'viewing_all_branches' => $viewingAllBranches,
                'branch_type_keys' => $branchTypeKeys,
                'imported_types' => collect($importedTypes)->map(fn (array $type) => [
                    'key' => $type['key'] ?? '',
                    'label' => $type['label'] ?? ($type['key'] ?? ''),
                ])->values()->all(),
                'tabs' => $packagingTabs,
                'other_count' => (int) ($packagingCountsByType['other'] ?? 0),
                'default_tab' => count($packagingTabs) === 1
                    ? ($packagingTabs[0]['key'] ?? 'all')
                    : 'all',
                'business_types_used' => $typesUsed,
                'max_business_types' => $typesLimit,
                'templates' => collect($packagingTemplates)
                    ->filter(fn ($units, $key) => $key !== '_default')
                    ->map(fn (array $units, string $key) => [
                        'key' => $key,
                        'label' => $businessTemplates[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key)),
                        'units' => array_values($units),
                    ])->values()->all(),
                'default_units' => array_values($packagingTemplates['_default'] ?? []),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'add_items'])) {
            return $deny;
        }

        try {
            $business = $this->requireBusiness();
            $allowedKeys = $this->allowedBusinessTypeKeys($business, $request->user());

            $rules = [
                'name' => 'required|string|max:255',
            ];

            if ($allowedKeys !== []) {
                $rules['source_business_type_key'] = [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::in(array_merge($allowedKeys, ['other'])),
                ];
            } else {
                $rules['source_business_type_key'] = 'nullable|string|max:255';
            }

            $validated = $request->validate($rules);

            $sourceKey = $validated['source_business_type_key'] ?? null;
            $sourceKey = $sourceKey === 'other' || $sourceKey === '' ? null : $sourceKey;

            if ($sourceKey && ! in_array($sourceKey, $allowedKeys, true)) {
                return $this->error('The selected business type is not available for the active branch.', 422);
            }

            $packaging = Packaging::create([
                'business_id' => $business->id,
                'name' => $validated['name'],
                'source_business_type_key' => $sourceKey,
            ]);

            return $this->success([
                'packaging' => $this->packagingPayload($packaging),
            ], 'Packaging unit added successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function update(Request $request, Packaging $packaging): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'edit_items'])) {
            return $deny;
        }

        if ($deny = $this->ensurePackagingAccess($packaging, $request->user())) {
            return $deny;
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $packaging->update(['name' => $validated['name']]);

            return $this->success([
                'packaging' => $this->packagingPayload($packaging->fresh()),
            ], 'Packaging unit updated.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function destroy(Request $request, Packaging $packaging): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'delete_items'])) {
            return $deny;
        }

        if ($deny = $this->ensurePackagingAccess($packaging, $request->user())) {
            return $deny;
        }

        $packaging->delete();

        return $this->success(null, 'Packaging unit deleted.');
    }

    public function importTemplates(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'add_items'])) {
            return $deny;
        }

        try {
            $business = $this->requireBusiness();
            $branchFilterId = $this->branchFilterId($request->user());
            $branchImportedTypes = $branchFilterId
                ? $business->importedTypesForBranch($branchFilterId)
                : $business->categoryBusinessTypesList();
            $templateKeys = array_keys(config('category_templates', []));
            $customKeys = collect($branchImportedTypes)
                ->pluck('key')
                ->filter(fn ($key) => str_starts_with((string) $key, 'custom:'))
                ->all();
            $allowedKeys = array_merge($templateKeys, $customKeys);

            $validated = $request->validate([
                'business_type_key' => 'required|string|max:255',
            ]);

            $businessTypeKey = $validated['business_type_key'];
            $branchTypeKeys = collect($branchImportedTypes)->pluck('key')->filter()->values()->all();

            if ($businessTypeKey === 'all') {
                $keysToImport = $branchTypeKeys;

                if ($keysToImport === []) {
                    return $this->error(
                        $branchFilterId
                            ? 'No business types are configured for this branch yet. Import categories for the branch first.'
                            : 'No business types configured yet. Set up your business type under Categories first.',
                        422
                    );
                }
            } elseif (in_array($businessTypeKey, $allowedKeys, true)) {
                if ($branchFilterId && ! in_array($businessTypeKey, $branchTypeKeys, true)) {
                    return $this->error('That business type is not configured for the active branch.', 422);
                }

                $keysToImport = [$businessTypeKey];
            } else {
                return $this->error('Please select a valid business type.', 422);
            }

            $businessTemplates = config('category_templates', []);

            try {
                DB::beginTransaction();

                $totalCreated = 0;
                $totalSkipped = 0;
                $importedKeys = [];

                foreach ($keysToImport as $key) {
                    if (! $business->hasCategoryBusinessType($key)) {
                        $business->assertCanAddCategoryBusinessType($key);

                        $label = collect($branchImportedTypes)->firstWhere('key', $key)['label']
                            ?? $businessTemplates[$key]['label']
                            ?? 'Business';

                        $business->registerCategoryBusinessType($key, $label, []);
                    }

                    foreach ($this->unitsForBusinessType($key) as $unit) {
                        $packaging = Packaging::firstOrCreate([
                            'business_id' => $business->id,
                            'name' => $unit,
                            'source_business_type_key' => $key,
                        ]);

                        if ($packaging->wasRecentlyCreated) {
                            $totalCreated++;
                        } else {
                            $totalSkipped++;
                        }
                    }

                    $importedKeys[] = $key;
                }

                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return $this->error($e->getMessage(), 422);
            }

            $label = $businessTypeKey === 'all'
                ? ($branchFilterId ? 'all business types for this branch' : 'all your business types')
                : (
                    collect($branchImportedTypes)->firstWhere('key', $businessTypeKey)['label']
                    ?? $businessTemplates[$businessTypeKey]['label']
                    ?? 'business type'
                );

            $detail = $totalCreated > 0
                ? "{$totalCreated} unit(s) added".($totalSkipped > 0 ? ", {$totalSkipped} already existed for their business type" : '').'.'
                : 'All units for this selection were already imported.';

            return $this->success([
                'imported_keys' => $importedKeys,
                'created' => $totalCreated,
                'skipped' => $totalSkipped,
            ], "Packaging units for {$label} imported successfully. {$detail}");
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function clearAll(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_packaging', 'delete_items'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $branchFilterId = $this->branchFilterId($request->user());

        if ($branchFilterId) {
            $branchTypeKeys = collect($business->importedTypesForBranch($branchFilterId))
                ->pluck('key')
                ->filter()
                ->values()
                ->all();

            if ($branchTypeKeys === []) {
                return $this->success([
                    'deleted_count' => 0,
                    'branch_id' => $branchFilterId,
                ], 'No packaging units to clear for this branch.');
            }

            $deleted = Packaging::query()
                ->where('business_id', $business->id)
                ->whereIn('source_business_type_key', $branchTypeKeys)
                ->delete();

            return $this->success([
                'deleted_count' => $deleted,
                'branch_id' => $branchFilterId,
            ], 'All packaging units for this branch have been cleared.');
        }

        $deleted = Packaging::query()->where('business_id', $business->id)->delete();

        return $this->success([
            'deleted_count' => $deleted,
            'branch_id' => null,
        ], 'All packaging units have been cleared.');
    }

    private function requireBusiness(): Business
    {
        $business = $this->apiBusiness();
        if (! $business) {
            abort(404, 'Business not found.');
        }

        return $business->loadMissing('plan');
    }

    private function packagingPayload(Packaging $packaging): array
    {
        return [
            'id' => $packaging->id,
            'name' => $packaging->name,
            'source_business_type_key' => $packaging->source_business_type_key,
        ];
    }

    private function branchFilterId($user): ?int
    {
        if (! $user->seesBusinessWideData()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        return $this->tenantContext()->branchId();
    }

    private function ensurePackagingAccess(Packaging $packaging, $user): ?JsonResponse
    {
        if ((int) $packaging->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $branchFilterId = $this->branchFilterId($user);
        if (! $branchFilterId) {
            return null;
        }

        $branchTypeKeys = collect($this->requireBusiness()->importedTypesForBranch($branchFilterId))
            ->pluck('key')
            ->filter()
            ->all();

        $key = $packaging->source_business_type_key ?: 'other';

        if (! in_array($key, $branchTypeKeys, true)) {
            return $this->forbidden('Packaging unit is not available for the active branch.');
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function unitsForBusinessType(string $key): array
    {
        $packagingTemplates = packaging_templates();

        return $packagingTemplates[$key] ?? $packagingTemplates['_default'] ?? [];
    }

    /**
     * @return list<string>
     */
    private function allowedBusinessTypeKeys(Business $business, $user): array
    {
        $branchFilterId = $this->branchFilterId($user);

        if ($branchFilterId) {
            return collect($business->importedTypesForBranch($branchFilterId))
                ->pluck('key')
                ->filter()
                ->values()
                ->all();
        }

        $templateKeys = array_keys(config('category_templates', []));
        $customKeys = collect($business->categoryBusinessTypesList())
            ->pluck('key')
            ->filter(fn ($key) => str_starts_with((string) $key, 'custom:'))
            ->all();

        return array_values(array_unique(array_merge($templateKeys, $customKeys)));
    }
}
