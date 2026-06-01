<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_categories', 'view_inventory']);
        $categories = Category::where('business_id', Auth::user()->business_id)->orderBy('name')->get();
        $businessTemplates = config('category_templates', []);
        $business = Business::with('plan')->findOrFail(Auth::user()->business_id);
        $importedTypes = $business->categoryBusinessTypesList();
        $categoryCountsByType = $categories->groupBy(fn ($c) => $c->source_business_type_key ?: 'other')->map->count();

        return view('registration.categories.index', compact(
            'categories',
            'businessTemplates',
            'business',
            'importedTypes',
            'categoryCountsByType'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = Business::findOrFail(Auth::user()->business_id);
        $allowedKeys = collect($business->categoryBusinessTypesList())->pluck('key')->all();

        if (empty($allowedKeys)) {
            return redirect()->back()->with(
                'error',
                'Please import a business type first before adding categories manually.'
            );
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'source_business_type_key' => 'required|string|max:255|in:'.implode(',', $allowedKeys),
        ]);

        Category::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'source_business_type_key' => $request->source_business_type_key,
        ]);

        return redirect()->back()->with('success', 'Category added successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeAny(['manage_categories', 'edit_items']);
        if ($category->business_id != Auth::user()->business_id) {
            abort(403);
        }
        $request->validate(['name' => 'required|string|max:255']);
        $category->update(['name' => $request->name]);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeAny(['manage_categories', 'delete_items']);
        if ($category->business_id != Auth::user()->business_id) {
            abort(403);
        }
        $category->delete();

        return redirect()->back()->with('success', 'Category deleted.');
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = Business::with('plan')->findOrFail(Auth::user()->business_id);
        $businessId = $business->id;
        $type = $request->template_type;
        $templates = config('category_templates', []);

        if ($type === 'custom') {
            $request->validate([
                'custom_business_name' => 'required|string|max:255',
                'custom_categories' => 'required|string|max:5000',
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
                    $this->upsertCategory($businessId, $catName, $customKey);
                }

                DB::commit();
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();

                return redirect()->back()->with('error', $e->getMessage());
            }

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
                    $this->upsertCategory($businessId, $catName, $templateKey);
                }

                $importedLabels[] = $label;
            }

            DB::commit();
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = count($importedLabels) === 1
            ? $importedLabels[0].' categories imported successfully!'
            : count($importedLabels).' business types imported: '.implode(', ', $importedLabels);

        return redirect()->back()->with('success', $message);
    }

    public function clearAll()
    {
        $this->authorizeAny(['manage_categories', 'delete_items']);

        $business = Business::findOrFail(Auth::user()->business_id);

        Category::where('business_id', $business->id)->delete();
        $business->clearCategoryBusinessTypes();

        return redirect()->back()->with('success', 'All categories have been cleared. You can now import a fresh template.');
    }

    private function upsertCategory(int $businessId, string $name, string $sourceKey): void
    {
        Category::updateOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            ['source_business_type_key' => $sourceKey]
        );
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
