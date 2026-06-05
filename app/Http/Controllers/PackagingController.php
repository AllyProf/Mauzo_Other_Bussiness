<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Packaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackagingController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_packaging', 'view_inventory']);

        $business = Business::with('plan')->findOrFail($this->currentBusinessId());
        $branchFilterId = $this->branchFilterId();
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        if ($branchFilterId) {
            $importedTypes = $business->importedTypesForBranch($branchFilterId);
            $branchTypeKeys = collect($importedTypes)->pluck('key')->filter()->values()->all();
        } else {
            $importedTypes = $business->categoryBusinessTypesList();
            $branchTypeKeys = collect($importedTypes)->pluck('key')->filter()->values()->all();
        }

        $configuredKeys = $branchTypeKeys;
        $businessTemplates = category_templates();
        $packagingTemplates = packaging_templates();

        $allPackagings = Packaging::where('business_id', $business->id)->orderBy('name')->get();
        $packagings = $branchFilterId
            ? $allPackagings->filter(function (Packaging $packaging) use ($branchTypeKeys) {
                $key = $packaging->source_business_type_key ?: 'other';

                return $key === 'other'
                    ? false
                    : in_array($key, $branchTypeKeys, true);
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
                    'count' => $packagingCountsByType[$key] ?? 0,
                    'is_custom' => str_starts_with($key, 'custom:'),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        $otherCount = $packagingCountsByType['other'] ?? 0;
        $defaultPackagingTab = count($packagingTabs) === 1
            ? ($packagingTabs[0]['key'] ?? 'all')
            : 'all';

        $importedTypeKeys = collect($packagingTabs)->pluck('key')->all();

        $typesUsed = $business->categoryBusinessTypesUsed();
        $typesLimit = $business->maxBusinessTypesAllowed();
        $typesRemaining = $typesLimit === null ? null : max(0, $typesLimit - $typesUsed);

        return view('registration.packagings.index', compact(
            'packagings',
            'importedTypes',
            'configuredKeys',
            'branchTypeKeys',
            'businessTemplates',
            'packagingTemplates',
            'business',
            'packagingTabs',
            'packagingCountsByType',
            'otherCount',
            'defaultPackagingTab',
            'importedTypeKeys',
            'typesUsed',
            'typesLimit',
            'typesRemaining',
            'branchFilterId',
            'activeBranchName',
            'viewingAllBranches'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_packaging', 'add_items']);

        $business = Business::findOrFail($this->currentBusinessId());
        $allowedKeys = $this->allowedBusinessTypeKeys($business);

        $rules = [
            'name' => 'required|string|max:255',
        ];

        if ($allowedKeys !== []) {
            $rules['source_business_type_key'] = 'nullable|string|max:255|in:'.implode(',', array_merge($allowedKeys, ['other']));
        }

        $request->validate($rules);

        $sourceKey = $request->input('source_business_type_key');
        $sourceKey = $sourceKey === 'other' || $sourceKey === '' ? null : $sourceKey;

        if ($sourceKey && ! in_array($sourceKey, $allowedKeys, true)) {
            return redirect()->back()->withInput()->with(
                'error',
                'The selected business type is not available for the active branch.'
            );
        }

        Packaging::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'source_business_type_key' => $sourceKey,
        ]);

        return redirect()->back()->with('success', 'Packaging unit added successfully.');
    }

    public function update(Request $request, Packaging $packaging)
    {
        $this->authorizeAny(['manage_packaging', 'edit_items']);
        $this->ensurePackagingAccess($packaging);

        $request->validate(['name' => 'required|string|max:255']);
        $packaging->update(['name' => $request->name]);

        return redirect()->back()->with('success', 'Packaging unit updated.');
    }

    public function destroy(Packaging $packaging)
    {
        $this->authorizeAny(['manage_packaging', 'delete_items']);
        $this->ensurePackagingAccess($packaging);
        $packaging->delete();

        return redirect()->back()->with('success', 'Packaging unit deleted.');
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_packaging', 'add_items']);

        $business = Business::with('plan')->findOrFail($this->currentBusinessId());
        $branchFilterId = $this->branchFilterId();
        $branchImportedTypes = $branchFilterId
            ? $business->importedTypesForBranch($branchFilterId)
            : $business->categoryBusinessTypesList();
        $importedTypes = $branchImportedTypes;
        $templateKeys = array_keys(config('category_templates', []));
        $customKeys = collect($branchImportedTypes)
            ->pluck('key')
            ->filter(fn ($key) => str_starts_with((string) $key, 'custom:'))
            ->all();
        $allowedKeys = array_merge($templateKeys, $customKeys);
        $businessTypeKey = $request->input('business_type_key');
        $branchTypeKeys = collect($branchImportedTypes)->pluck('key')->filter()->values()->all();

        if ($businessTypeKey === 'all') {
            $keysToImport = $branchTypeKeys;

            if (empty($keysToImport)) {
                return redirect()->back()->with(
                    'error',
                    $branchFilterId
                        ? 'No business types are configured for this branch yet. Import categories for the branch first.'
                        : 'No business types configured yet. Select a template below or set up your business type under Categories.'
                );
            }
        } elseif (in_array($businessTypeKey, $allowedKeys, true)) {
            if ($branchFilterId && ! in_array($businessTypeKey, $branchTypeKeys, true)) {
                return redirect()->back()->with('error', 'That business type is not configured for the active branch.');
            }

            $keysToImport = [$businessTypeKey];
        } else {
            return redirect()->back()->with('error', 'Please select a valid business type.');
        }

        $businessTemplates = config('category_templates', []);

        try {
            DB::beginTransaction();

            $totalCreated = 0;
            $totalSkipped = 0;

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
            }

            DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
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

        return redirect()->back()->with('success', "Packaging units for {$label} imported successfully. {$detail}");
    }

    public function clearAll()
    {
        $this->authorizeAny(['manage_packaging', 'delete_items']);

        $business = Business::findOrFail($this->currentBusinessId());
        $branchFilterId = $this->branchFilterId();

        if ($branchFilterId) {
            $branchTypeKeys = collect($business->importedTypesForBranch($branchFilterId))
                ->pluck('key')
                ->filter()
                ->values()
                ->all();

            if (empty($branchTypeKeys)) {
                return redirect()->back()->with('success', 'No packaging units to clear for this branch.');
            }

            Packaging::where('business_id', $business->id)
                ->whereIn('source_business_type_key', $branchTypeKeys)
                ->delete();

            return redirect()->back()->with('success', 'All packaging units for this branch have been cleared.');
        }

        Packaging::where('business_id', $business->id)->delete();

        return redirect()->back()->with('success', 'All packaging units have been cleared.');
    }

    private function branchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer()) {
            $branchId = auth()->user()?->branch_id;

            return $branchId ? (int) $branchId : null;
        }

        return active_branch_id();
    }

    private function ensurePackagingAccess(Packaging $packaging): void
    {
        if ((int) $packaging->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        $branchFilterId = $this->branchFilterId();

        if (! $branchFilterId) {
            return;
        }

        $branchTypeKeys = collect(Business::findOrFail($this->currentBusinessId())->importedTypesForBranch($branchFilterId))
            ->pluck('key')
            ->filter()
            ->all();

        $key = $packaging->source_business_type_key ?: 'other';

        if (! in_array($key, $branchTypeKeys, true)) {
            abort(403);
        }
    }

    private function unitsForBusinessType(string $key): array
    {
        $packagingTemplates = packaging_templates();

        return $packagingTemplates[$key] ?? $packagingTemplates['_default'] ?? [];
    }

    /**
     * @return list<string>
     */
    private function allowedBusinessTypeKeys(Business $business): array
    {
        $branchFilterId = $this->branchFilterId();

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
