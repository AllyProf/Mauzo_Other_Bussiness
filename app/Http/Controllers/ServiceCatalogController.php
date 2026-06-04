<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceCatalogController extends Controller
{
    public function index()
    {
        return redirect()->route('services.categories');
    }

    public function register()
    {
        $this->authorizeAny(['manage_categories', 'view_inventory', 'process_sales', 'add_items']);

        return view('services.register', $this->buildPageContext());
    }

    public function categories()
    {
        $this->authorizeAny(['manage_categories', 'view_inventory', 'process_sales']);

        return view('services.categories', $this->buildPageContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPageContext(): array
    {
        $businessId = $this->currentBusinessId();
        $branchFilterId = $this->branchFilterId();
        $business = $this->currentBusinessForWrite();
        $serviceTemplates = config('service_templates', []);

        $categoriesQuery = ServiceCategory::query()
            ->where('business_id', $businessId)
            ->with(['branch:id,name', 'services'])
            ->withCount('services')
            ->orderBy('name');

        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }

        $categories = $categoriesQuery->get();
        $importedTypes = $business->importedServiceTypesFromCategories($categories);
        $categoryCountsByType = $categories->groupBy(fn ($c) => $c->source_service_type_key ?: 'other')->map->count();
        $writableBranches = $this->writableBranches();
        $canPickBranch = $this->actsAsBusinessWideViewer() && $writableBranches->count() > 1;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        $services = Service::query()
            ->where('business_id', $businessId)
            ->with('category')
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->orderBy('name')
            ->get();

        $consumableItems = \App\Models\Item::query()
            ->where('business_id', $businessId)
            ->when($branchFilterId, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('branch_id', $branchFilterId)))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return compact(
            'categories',
            'services',
            'serviceTemplates',
            'business',
            'importedTypes',
            'categoryCountsByType',
            'branchFilterId',
            'activeBranchName',
            'writableBranches',
            'canPickBranch',
            'consumableItems',
        );
    }

    private function redirectAfterWrite(string $route = 'services.categories')
    {
        return redirect()->route($route);
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->withInput()->with('error', 'Select a branch for this service category.');
        }

        $allowedKeys = collect($business->importedServiceTypesForBranch($branchId))->pluck('key')->all();
        if (empty($allowedKeys)) {
            return redirect()->back()->with('error', 'Import a service business template first.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'source_service_type_key' => 'required|string|max:255|in:'.implode(',', $allowedKeys),
            'branch_id' => $this->branchValidationRule(),
        ]);

        ServiceCategory::create([
            'business_id' => $business->id,
            'branch_id' => $branchId,
            'name' => $request->name,
            'source_service_type_key' => $request->source_service_type_key,
        ]);

        $business->syncServiceBusinessTypesFromCategories();
        $this->focusActiveBranchAfterWrite($branchId);

        return $this->redirectAfterWrite()->with('success', 'Service category added.');
    }

    public function storeService(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items', 'edit_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->resolveBranchIdFromRequest($request) ?? $this->branchFilterId();
        if (! $branchId) {
            return redirect()->back()->withInput()->with('error', 'Select a branch.');
        }

        $request->validate([
            'service_category_id' => [
                'required',
                Rule::exists('service_categories', 'id')->where(fn ($q) => $q
                    ->where('business_id', $business->id)
                    ->where('branch_id', $branchId)),
            ],
            'name' => 'required|string|max:255',
            'unit_label' => 'required|string|max:64',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        Service::create([
            'business_id' => $business->id,
            'branch_id' => $branchId,
            'service_category_id' => $request->service_category_id,
            'name' => $request->name,
            'unit_label' => $request->unit_label,
            'price' => $request->price,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return $this->redirectAfterWrite()->with('success', 'Service added with price configured.');
    }

    public function updateService(Request $request, Service $service)
    {
        $this->authorizeAny(['manage_categories', 'edit_items']);
        $this->ensureServiceAccess($service);

        $request->validate([
            'name' => 'required|string|max:255',
            'unit_label' => 'required|string|max:64',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'consumable_item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where('business_id', $service->business_id),
            ],
            'consumable_units_per_unit' => 'nullable|numeric|min:0',
        ]);

        $service->update([
            'name' => $request->name,
            'unit_label' => $request->unit_label,
            'price' => $request->price,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'consumable_item_id' => $request->consumable_item_id ?: null,
            'consumable_units_per_unit' => (float) ($request->consumable_units_per_unit ?? 0),
        ]);

        return $this->redirectAfterWrite()->with('success', 'Service updated.');
    }

    public function destroyService(Service $service)
    {
        $this->authorizeAny(['manage_categories', 'delete_items']);
        $this->ensureServiceAccess($service);
        $business = $this->currentBusinessForWrite();
        $service->delete();
        $business->syncServiceBusinessTypesFromCategories();

        return redirect()->back()->with('success', 'Service removed.');
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $businessId = $business->id;
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->with('error', 'Select which branch to import services for.');
        }

        $typesToImport = array_values(array_filter(
            $request->input('template_types', $request->template_type ? [$request->template_type] : [])
        ));
        $templates = config('service_templates', []);

        if ($request->input('template_type') === 'custom' || $request->filled('custom_business_name')) {
            $request->validate([
                'custom_business_name' => 'required|string|max:255',
                'custom_categories' => 'required|string|max:5000',
                'custom_services' => 'nullable|string|max:10000',
                'branch_id' => $this->branchValidationRule(),
            ]);

            $branchId = $this->resolveBranchIdFromRequest($request);
            if (! $branchId) {
                return redirect()->back()->with('error', 'Select a branch.');
            }

            $categoryNames = $this->parseLineList($request->custom_categories);
            if (empty($categoryNames)) {
                return redirect()->back()->with('error', 'Enter at least one category.');
            }

            $customKey = 'custom:'.\Illuminate\Support\Str::slug($request->custom_business_name);

            try {
                DB::beginTransaction();
                $business->registerServiceBusinessType($customKey, $request->custom_business_name, $categoryNames);

                $categoryMap = [];
                foreach ($categoryNames as $catName) {
                    $categoryMap[$catName] = $this->upsertServiceCategory($businessId, $branchId, $catName, $customKey);
                }

                foreach ($this->parseCustomServiceLines($request->input('custom_services', '')) as $row) {
                    $cat = $categoryMap[$row['category']] ?? null;
                    if (! $cat) {
                        continue;
                    }
                    Service::updateOrCreate(
                        [
                            'business_id' => $businessId,
                            'branch_id' => $branchId,
                            'service_category_id' => $cat->id,
                            'name' => $row['name'],
                        ],
                        [
                            'unit_label' => $row['unit_label'],
                            'price' => $row['price'],
                            'is_active' => true,
                        ]
                    );
                }

                DB::commit();
                $this->focusActiveBranchAfterWrite($branchId);

                return $this->redirectAfterWrite('services.register')->with('success', 'Custom service template "'.$request->custom_business_name.'" imported.');
            } catch (\Throwable $e) {
                DB::rollBack();

                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        if (empty($typesToImport)) {
            return redirect()->back()->with('error', 'Select at least one service business template.');
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
                    throw new \InvalidArgumentException('Unknown service template selected.');
                }

                $template = $templates[$templateKey];
                $label = $template['label'] ?? ucfirst(str_replace('_', ' ', $templateKey));
                $categoryBlocks = $template['categories'] ?? [];
                $categoryNames = collect($categoryBlocks)->pluck('name')->filter()->all();

                $business->registerServiceBusinessType($templateKey, $label, $categoryNames);

                foreach ($categoryBlocks as $block) {
                    $catName = (string) ($block['name'] ?? '');
                    if ($catName === '') {
                        continue;
                    }

                    $category = $this->upsertServiceCategory($businessId, $branchId, $catName, $templateKey);

                    foreach ($block['services'] ?? [] as $svc) {
                        $svcName = (string) ($svc['name'] ?? '');
                        if ($svcName === '') {
                            continue;
                        }

                        Service::updateOrCreate(
                            [
                                'business_id' => $businessId,
                                'branch_id' => $branchId,
                                'service_category_id' => $category->id,
                                'name' => $svcName,
                            ],
                            [
                                'unit_label' => (string) ($svc['unit_label'] ?? 'per service'),
                                'price' => (float) ($svc['default_price'] ?? 0),
                                'is_active' => true,
                            ]
                        );
                    }
                }

                $importedLabels[] = $label;
            }

            DB::commit();
            $this->focusActiveBranchAfterWrite($branchId);

            return $this->redirectAfterWrite('services.register')->with(
                'success',
                'Imported service templates: '.implode(', ', $importedLabels).'. Configure categories and prices next.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function upsertServiceCategory(int $businessId, int $branchId, string $name, string $typeKey): ServiceCategory
    {
        return ServiceCategory::firstOrCreate(
            [
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'name' => $name,
                'source_service_type_key' => $typeKey,
            ],
            [
                'name' => $name,
            ]
        );
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

    private function focusActiveBranchAfterWrite(int $branchId): void
    {
        if ($this->actsAsBusinessWideViewer()) {
            active_branch_service()->setActiveBranch($branchId);
        }
    }

    private function ensureServiceAccess(Service $service): void
    {
        if ($service->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        if ($this->branchFilterId() && (int) $service->branch_id !== $this->branchFilterId()) {
            abort(403);
        }
    }

    /**
     * @return list<string>
     */
    private function parseLineList(string $input): array
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

    /**
     * Lines: Category | Service name | unit label | price
     *
     * @return list<array{category: string, name: string, unit_label: string, price: float}>
     */
    private function parseCustomServiceLines(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', $input) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2) {
                continue;
            }

            $category = $parts[0];
            $name = $parts[1];
            $unit = $parts[2] ?? 'per service';
            $price = isset($parts[3]) ? (float) preg_replace('/[^0-9.]/', '', $parts[3]) : 0;

            if ($category === '' || $name === '') {
                continue;
            }

            $rows[] = [
                'category' => $category,
                'name' => $name,
                'unit_label' => $unit,
                'price' => $price,
            ];
        }

        return $rows;
    }
}
