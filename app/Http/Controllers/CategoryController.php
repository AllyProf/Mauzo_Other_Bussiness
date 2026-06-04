<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_categories', 'view_inventory']);

        $businessId = $this->currentBusinessId();
        $branchFilterId = $this->branchFilterId();
        $business = $this->currentBusinessForWrite();
        $businessTemplates = config('category_templates', []);
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;

        $categoriesQuery = Category::query()
            ->where('business_id', $businessId)
            ->with('branch:id,name')
            ->withCount('items')
            ->orderBy('name');

        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }

        $categories = $categoriesQuery->get();
        $importedTypes = $business->importedTypesFromCategories($categories);
        $businessTypesUsed = $business->categoryBusinessTypesUsed();
        $totalBusinessCategories = Category::query()
            ->where('business_id', $businessId)
            ->count();
        $categoriesHiddenByBranchFilter = $branchFilterId
            && $categories->isEmpty()
            && $totalBusinessCategories > 0;
        $categoryCountsByType = $categories->groupBy(fn ($category) => $category->source_business_type_key ?: 'other')->map->count();
        $writableBranches = $this->writableBranches();
        $canPickBranch = $this->actsAsBusinessWideViewer() && $writableBranches->count() > 1;
        $defaultBranchId = old('branch_id');
        $importedTypesMeta = collect($importedTypes)->map(function ($type) use ($categories) {
            $branchNames = $categories
                ->where('source_business_type_key', $type['key'] ?? '')
                ->map(fn (Category $category) => $category->branch?->name)
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_merge($type, ['branch_names' => $branchNames]);
        })->all();
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        return view('registration.categories.index', compact(
            'categories',
            'businessTemplates',
            'business',
            'importedTypes',
            'importedTypesMeta',
            'categoryCountsByType',
            'branchFilterId',
            'activeBranchName',
            'viewingAllBranches',
            'writableBranches',
            'canPickBranch',
            'defaultBranchId',
            'categoriesHiddenByBranchFilter',
            'totalBusinessCategories',
            'businessTypesUsed'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->withInput()->with(
                'error',
                $this->writableBranches()->isEmpty()
                    ? 'Register a branch before adding categories.'
                    : 'Please select which branch this business type belongs to.'
            );
        }
        $allowedKeys = collect(
            $business->importedTypesForBranch($branchId)
        )->pluck('key')->all();

        if (empty($allowedKeys)) {
            return redirect()->back()->withInput()->with(
                'error',
                'Import at least one business type for this branch on Categories before adding categories manually.'
            );
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'source_business_type_key' => 'required|string|max:255|in:'.implode(',', $allowedKeys),
            'branch_id' => $this->branchValidationRule(),
        ]);

        Category::create([
            'business_id' => $business->id,
            'branch_id' => $branchId,
            'name' => $request->name,
            'source_business_type_key' => $request->source_business_type_key,
        ]);

        $this->focusActiveBranchAfterWrite($branchId);

        return redirect()->back()->with('success', 'Category added successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeAny(['manage_categories', 'edit_items']);
        $this->ensureCategoryAccess($category);

        $request->validate(['name' => 'required|string|max:255']);
        $category->update(['name' => $request->name]);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeAny(['manage_categories', 'delete_items']);
        $this->ensureCategoryAccess($category);
        $business = $this->currentBusinessForWrite();
        $category->delete();
        $business->syncCategoryBusinessTypesFromCategories();

        return redirect()->back()->with('success', 'Category deleted.');
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $businessId = $business->id;
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->withInput()->with(
                'error',
                $this->writableBranches()->isEmpty()
                    ? 'Register a branch before importing categories.'
                    : 'Please select which branch this business type belongs to.'
            );
        }
        $type = $request->template_type;
        $templates = config('category_templates', []);

        if ($type === 'custom') {
            $request->validate([
                'custom_business_name' => 'required|string|max:255',
                'custom_categories' => 'required|string|max:5000',
                'branch_id' => $this->branchValidationRule(),
            ]);

            $categoryNames = $this->parseCategoryNames($request->custom_categories);

            if (empty($categoryNames)) {
                return redirect()->back()->with('error', 'Please enter at least one category name.');
            }

            $customKey = 'custom:' . Str::slug($request->custom_business_name);

            try {
                DB::beginTransaction();
                $business->assertCanAddCategoryBusinessType($customKey);
                $business->registerCategoryBusinessType($customKey, $request->custom_business_name, $categoryNames);

                foreach ($categoryNames as $catName) {
                    $this->upsertCategory($businessId, $branchId, $catName, $customKey);
                }

                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return redirect()->back()->with('error', $e->getMessage());
            }

            $this->focusActiveBranchAfterWrite($branchId);

            return redirect()->back()->with(
                'success',
                'Custom categories for "'.$request->custom_business_name.'" imported successfully!'
            );
        }

        $typesToImport = array_values(array_filter(
            $request->input('template_types', $request->template_type ? [$request->template_type] : [])
        ));

        if (empty($typesToImport)) {
            return redirect()->back()->with('error', 'Please select at least one business type to import.');
        }

        $request->validate([
            'template_types' => 'sometimes|array|min:1',
            'template_types.*' => 'string',
            'branch_id' => $this->branchValidationRule(),
        ]);

        try {
            DB::beginTransaction();

            $importedLabels = [];

            foreach ($typesToImport as $templateKey) {
                if (! isset($templates[$templateKey])) {
                    throw new \InvalidArgumentException('One or more selected business types were not found.');
                }

                $business->assertCanAddCategoryBusinessType($templateKey);
                $label = $templates[$templateKey]['label'] ?? ucfirst(str_replace('_', ' ', $templateKey));
                $templateCategories = $templates[$templateKey]['categories'];
                $business->registerCategoryBusinessType($templateKey, $label, $templateCategories);

                foreach ($templateCategories as $catName) {
                    $this->upsertCategory($businessId, $branchId, $catName, $templateKey);
                }

                $importedLabels[] = $label;
            }

            DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->focusActiveBranchAfterWrite($branchId);

        $message = count($importedLabels) === 1
            ? $importedLabels[0].' categories imported successfully!'
            : count($importedLabels).' business types imported: '.implode(', ', $importedLabels);

        return redirect()->back()->with('success', $message);
    }

    public function clearAll()
    {
        $this->authorizeAny(['manage_categories', 'delete_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->branchFilterId();

        $query = Category::where('business_id', $business->id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $query->delete();

        $business->syncCategoryBusinessTypesFromCategories();

        $remainingTypes = count($business->fresh()->categoryBusinessTypesList());
        $message = $branchId
            ? 'All categories for this branch have been cleared.'
            : 'All categories have been cleared. You can now import a fresh template.';

        if ($remainingTypes === 0) {
            $message .= ' Imported business types were reset.';
        }

        return redirect()->back()->with('success', $message);
    }

    private function currentBusinessForWrite(): Business
    {
        return Business::with('plan')->findOrFail($this->currentBusinessId());
    }

    private function branchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer()) {
            $branchId = auth()->user()?->branch_id;

            return $branchId ? (int) $branchId : null;
        }

        return active_branch_id();
    }

    private function resolveBranchIdFromRequest(Request $request): ?int
    {
        $branches = $this->writableBranches();

        if ($branches->isEmpty()) {
            return null;
        }

        if ($this->actsAsBusinessWideViewer() && $branches->count() > 1) {
            $branchId = (int) $request->input('branch_id');

            if (! in_array($branchId, $branches->pluck('id')->map(fn ($id) => (int) $id)->all(), true)) {
                return null;
            }

            return $branchId;
        }

        return (int) $branches->first()->id;
    }

    private function branchValidationRule(): array
    {
        $branches = $this->writableBranches();

        if ($this->actsAsBusinessWideViewer() && $branches->count() > 1) {
            return ['required', Rule::in($branches->pluck('id')->all())];
        }

        return ['nullable'];
    }

    private function writableBranches(): Collection
    {
        if ($this->actsAsBusinessWideViewer()) {
            $branches = active_branch_service()->branches();

            if ($branches->isNotEmpty()) {
                return $branches;
            }

            return $this->branchesForBusiness($this->currentBusinessId());
        }

        $branchId = (int) (auth()->user()?->branch_id ?? 0);

        if (! $branchId) {
            return collect();
        }

        return Branch::query()
            ->where('id', $branchId)
            ->where('is_active', true)
            ->get();
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

    private function resolveBranchIdForWrite(): ?int
    {
        if ($this->actsAsBusinessWideViewer()) {
            return active_branch_id();
        }

        $branchId = (int) auth()->user()->branch_id;

        return $branchId ?: null;
    }

    private function ensureCategoryAccess(Category $category): void
    {
        if ((int) $category->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        $branchFilterId = $this->branchFilterId();
        if ($branchFilterId && (int) $category->branch_id !== $branchFilterId) {
            abort(403);
        }
    }

    private function upsertCategory(int $businessId, int $branchId, string $name, string $sourceKey): void
    {
        Category::updateOrCreate(
            [
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'name' => $name,
            ],
            ['source_business_type_key' => $sourceKey]
        );
    }

    private function focusActiveBranchAfterWrite(int $branchId): void
    {
        if ($this->actsAsBusinessWideViewer()) {
            active_branch_service()->setActiveBranch($branchId);
        }
    }

    private function parseCategoryNames(string $input): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $names = [];

        foreach ($parts as $part) {
            $name = trim($part);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
