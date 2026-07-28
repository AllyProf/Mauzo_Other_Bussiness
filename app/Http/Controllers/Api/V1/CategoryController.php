<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'view_inventory'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $businessId = (int) $business->id;
        $branchFilterId = $this->branchFilterId($request->user());
        $viewingAllBranches = $request->user()->seesBusinessWideData() && ! $branchFilterId;

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
        $totalBusinessCategories = Category::query()->where('business_id', $businessId)->count();
        $writableBranches = $this->writableBranches($request->user());
        $canPickBranch = $request->user()->seesBusinessWideData() && $writableBranches->count() > 1;

        $importedTypesMeta = collect($importedTypes)->map(function (array $type) use ($categories) {
            $branchNames = $categories
                ->where('source_business_type_key', $type['key'] ?? '')
                ->map(fn (Category $category) => $category->branch?->name)
                ->filter()
                ->unique()
                ->values()
                ->all();

            return array_merge($type, ['branch_names' => $branchNames]);
        })->values()->all();

        $templates = collect(category_templates())->map(fn (array $type, string $key) => [
            'key' => $key,
            'label' => (string) ($type['label'] ?? $key),
            'icon' => (string) ($type['icon'] ?? 'fa-store'),
            'categories' => array_values($type['categories'] ?? []),
        ])->values()->all();

        return $this->success([
            'categories' => $categories->map(fn (Category $c) => $this->categoryPayload($c))->values(),
            'meta' => [
                'branch_filter_id' => $branchFilterId,
                'active_branch_name' => $branchFilterId
                    ? ($this->tenantContext()->branch()?->name ?? Branch::find($branchFilterId)?->name)
                    : null,
                'viewing_all_branches' => $viewingAllBranches,
                'can_pick_branch' => $canPickBranch,
                'writable_branches' => $writableBranches->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'is_default' => (bool) $b->is_default,
                ])->values(),
                'total_business_categories' => $totalBusinessCategories,
                'categories_hidden_by_branch_filter' => $branchFilterId
                    && $categories->isEmpty()
                    && $totalBusinessCategories > 0,
                'business_types_used' => $business->categoryBusinessTypesUsed(),
                'max_business_types' => $business->maxBusinessTypesAllowed(),
                'imported_types' => $importedTypesMeta,
                'templates' => $templates,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'add_items'])) {
            return $deny;
        }

        try {
            $business = $this->requireBusiness();
            $branchId = $this->resolveBranchIdFromRequest($request);

            if (! $branchId) {
                return $this->error(
                    $this->writableBranches($request->user())->isEmpty()
                        ? 'Register a branch before adding categories.'
                        : 'Please select which branch this business type belongs to.',
                    422
                );
            }

            $allowedKeys = collect($business->importedTypesForBranch($branchId))->pluck('key')->all();

            if ($allowedKeys === []) {
                return $this->error(
                    'Import at least one business type for this branch before adding categories manually.',
                    422
                );
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'source_business_type_key' => ['required', 'string', 'max:255', Rule::in($allowedKeys)],
                'branch_id' => $this->branchValidationRule($request->user()),
            ]);

            $category = Category::create([
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'source_business_type_key' => $validated['source_business_type_key'],
            ]);

            $category->load('branch:id,name')->loadCount('items');

            return $this->success([
                'category' => $this->categoryPayload($category),
            ], 'Category added successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'edit_items'])) {
            return $deny;
        }

        if ($deny = $this->ensureCategoryAccess($category, $request->user())) {
            return $deny;
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $category->update(['name' => $validated['name']]);
            $category->load('branch:id,name')->loadCount('items');

            return $this->success([
                'category' => $this->categoryPayload($category),
            ], 'Category updated.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'delete_items'])) {
            return $deny;
        }

        if ($deny = $this->ensureCategoryAccess($category, $request->user())) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $category->delete();
        $business->syncCategoryBusinessTypesFromCategories();

        return $this->success(null, 'Category deleted.');
    }

    public function importTemplates(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'add_items'])) {
            return $deny;
        }

        try {
            $business = $this->requireBusiness();
            $businessId = (int) $business->id;
            $branchId = $this->resolveBranchIdFromRequest($request);

            if (! $branchId) {
                return $this->error(
                    $this->writableBranches($request->user())->isEmpty()
                        ? 'Register a branch before importing categories.'
                        : 'Please select which branch this business type belongs to.',
                    422
                );
            }

            $type = $request->input('template_type');
            $templates = category_templates();

            if ($type === 'custom') {
                $validated = $request->validate([
                    'custom_business_name' => 'required|string|max:255',
                    'custom_categories' => 'required|string|max:5000',
                    'branch_id' => $this->branchValidationRule($request->user()),
                ]);

                $categoryNames = $this->parseCategoryNames($validated['custom_categories']);

                if ($categoryNames === []) {
                    return $this->error('Please enter at least one category name.', 422);
                }

                $customKey = 'custom:'.Str::slug($validated['custom_business_name']);

                try {
                    DB::beginTransaction();
                    $business->assertCanAddCategoryBusinessType($customKey);
                    $business->registerCategoryBusinessType(
                        $customKey,
                        $validated['custom_business_name'],
                        $categoryNames
                    );

                    foreach ($categoryNames as $catName) {
                        $this->upsertCategory($businessId, $branchId, $catName, $customKey);
                    }

                    DB::commit();
                } catch (\InvalidArgumentException $e) {
                    DB::rollBack();

                    return $this->error($e->getMessage(), 422);
                }

                return $this->success([
                    'imported_type' => [
                        'key' => $customKey,
                        'label' => $validated['custom_business_name'],
                        'categories' => $categoryNames,
                    ],
                    'branch_id' => $branchId,
                ], 'Custom categories for "'.$validated['custom_business_name'].'" imported successfully!');
            }

            $typesToImport = array_values(array_filter(
                $request->input('template_types', $type ? [$type] : [])
            ));

            if ($typesToImport === []) {
                return $this->error('Please select at least one business type to import.', 422);
            }

            $request->validate([
                'template_types' => 'sometimes|array|min:1',
                'template_types.*' => 'string',
                'template_type' => 'sometimes|nullable|string',
                'branch_id' => $this->branchValidationRule($request->user()),
            ]);

            try {
                DB::beginTransaction();

                $importedLabels = [];
                $importedKeys = [];

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
                    $importedKeys[] = $templateKey;
                }

                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return $this->error($e->getMessage(), 422);
            }

            $message = count($importedLabels) === 1
                ? $importedLabels[0].' categories imported successfully!'
                : count($importedLabels).' business types imported: '.implode(', ', $importedLabels);

            return $this->success([
                'imported_keys' => $importedKeys,
                'imported_labels' => $importedLabels,
                'branch_id' => $branchId,
            ], $message);
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function clearAll(Request $request): JsonResponse
    {
        if ($deny = $this->authorizeApiAny(['manage_categories', 'delete_items'])) {
            return $deny;
        }

        $business = $this->requireBusiness();
        $branchId = $this->branchFilterId($request->user());

        $query = Category::query()->where('business_id', $business->id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $deleted = $query->delete();

        $business->syncCategoryBusinessTypesFromCategories();

        $remainingTypes = count($business->fresh()->categoryBusinessTypesList());
        $message = $branchId
            ? 'All categories for this branch have been cleared.'
            : 'All categories have been cleared. You can now import a fresh template.';

        if ($remainingTypes === 0) {
            $message .= ' Imported business types were reset.';
        }

        return $this->success([
            'deleted_count' => $deleted,
            'branch_id' => $branchId,
            'remaining_business_types' => $remainingTypes,
        ], $message);
    }

    private function requireBusiness(): Business
    {
        $business = $this->apiBusiness();
        if (! $business) {
            abort(404, 'Business not found.');
        }

        return $business->loadMissing('plan');
    }

    private function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'source_business_type_key' => $category->source_business_type_key,
            'branch' => $category->branch ? [
                'id' => $category->branch->id,
                'name' => $category->branch->name,
            ] : null,
            'items_count' => (int) ($category->items_count ?? 0),
        ];
    }

    private function branchFilterId($user): ?int
    {
        if (! $user->seesBusinessWideData()) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        return $this->tenantContext()->branchId();
    }

    private function resolveBranchIdFromRequest(Request $request): ?int
    {
        $user = $request->user();
        $branches = $this->writableBranches($user);

        if ($branches->isEmpty()) {
            return null;
        }

        if ($user->seesBusinessWideData() && $branches->count() > 1) {
            $branchId = (int) $request->input('branch_id');

            if (! in_array($branchId, $branches->pluck('id')->map(fn ($id) => (int) $id)->all(), true)) {
                return null;
            }

            return $branchId;
        }

        return (int) $branches->first()->id;
    }

    private function branchValidationRule($user): array
    {
        $branches = $this->writableBranches($user);

        if ($user->seesBusinessWideData() && $branches->count() > 1) {
            return ['required', Rule::in($branches->pluck('id')->all())];
        }

        return ['nullable'];
    }

    private function writableBranches($user): Collection
    {
        $businessId = $this->apiBusinessId();

        if ($user->seesBusinessWideData()) {
            $branches = $this->tenantContext()->ownerBranches();

            if ($branches->isNotEmpty()) {
                return $branches;
            }

            return Branch::query()
                ->where('is_active', true)
                ->where(function ($query) use ($businessId) {
                    $query->whereHas('businesses', fn ($q) => $q->where('businesses.id', $businessId))
                        ->orWhere('business_id', $businessId);
                })
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        $branchId = (int) ($user->branch_id ?? 0);
        if (! $branchId) {
            return collect();
        }

        return Branch::query()
            ->where('id', $branchId)
            ->where('is_active', true)
            ->get();
    }

    private function ensureCategoryAccess(Category $category, $user): ?JsonResponse
    {
        if ((int) $category->business_id !== $this->apiBusinessId()) {
            return $this->forbidden();
        }

        $branchFilterId = $this->branchFilterId($user);
        if ($branchFilterId && (int) $category->branch_id !== $branchFilterId) {
            return $this->forbidden('Category belongs to another branch.');
        }

        return null;
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

    /**
     * @return list<string>
     */
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
