<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceMaterial;
use App\Models\ServiceMaterialReceipt;
use App\Services\ServiceTemplateImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ServiceCatalogController extends Controller
{
    public function __construct(private ServiceTemplateImportService $templateImporter)
    {
    }

    public function index()
    {
        return redirect()->route('services.categories');
    }

    public function register()
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'view_inventory', 'process_sales', 'add_items']);

        return view('services.register', $this->buildPageContext());
    }

    public function categories()
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'view_inventory', 'process_sales']);

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

        $serviceMaterials = ServiceMaterial::query()
            ->where('business_id', $businessId)
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->orderBy('name')
            ->get(['id', 'name', 'unit_label', 'current_stock']);

        $materialsStock = $serviceMaterials->map(fn ($m) => [
            'name' => $m->name,
            'stock_label' => $m->stockLabel(),
            'current_stock' => (float) $m->current_stock,
        ])->values();

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
            'serviceMaterials',
            'materialsStock',
        );
    }

    private function redirectAfterWrite(string $route = 'services.categories')
    {
        return redirect()->route($route);
    }

    public function materials()
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'view_inventory', 'add_items', 'process_sales']);

        $context = $this->buildPageContext();
        $businessId = $this->currentBusinessId();
        $branchFilterId = $context['branchFilterId'];

        $materialsQuery = ServiceMaterial::query()
            ->where('business_id', $businessId)
            ->with(['branch:id,name'])
            ->withCount('receipts')
            ->orderBy('name');

        if ($branchFilterId) {
            $materialsQuery->where('branch_id', $branchFilterId);
        }

        $materials = $materialsQuery->get();

        return view('services.materials', array_merge($context, compact('materials')));
    }

    public function storeMaterial(Request $request)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->withInput()->with('error', 'Select a branch for this material.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'unit_label' => 'required|string|max:64',
            'branch_id' => $this->branchValidationRule(),
        ]);

        ServiceMaterial::firstOrCreate(
            [
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'name' => $request->name,
            ],
            [
                'unit_label' => $request->unit_label,
            ]
        );

        $this->focusActiveBranchAfterWrite($branchId);

        return redirect()->route('services.materials')->with('success', 'Service material "'.$request->name.'" added.');
    }

    public function receiveMaterial(Request $request, ServiceMaterial $material)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'add_items', 'receive_stock']);
        $this->ensureServiceMaterialAccess($material);

        $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'total_cost' => 'nullable|numeric|min:0',
            'received_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $quantity = (float) $request->quantity;
        $totalCost = (float) ($request->total_cost ?? 0);

        ServiceMaterialReceipt::create([
            'service_material_id' => $material->id,
            'user_id' => auth()->id(),
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'received_date' => $request->received_date,
            'notes' => $request->notes,
        ]);

        $material->current_stock = (float) $material->current_stock + $quantity;
        if ($quantity > 0 && $totalCost > 0) {
            $material->last_cost_per_unit = round($totalCost / $quantity, 4);
        }
        $material->save();

        return redirect()->route('services.materials')->with(
            'success',
            'Received '.$quantity.' '.$material->unit_label.' of '.$material->name.'. Stock now: '.$material->stockLabel().'.'
        );
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'add_items']);

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
        $this->authorizeAny(['manage_services', 'manage_categories', 'add_items', 'edit_items']);

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
        $this->authorizeAny(['manage_services', 'manage_categories', 'edit_items']);
        $this->ensureServiceAccess($service);

        $request->validate([
            'name' => 'required|string|max:255',
            'unit_label' => 'required|string|max:64',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'service_material_id' => [
                'nullable',
                'integer',
                Rule::exists('service_materials', 'id')->where(fn ($q) => $q
                    ->where('business_id', $service->business_id)
                    ->where('branch_id', $service->branch_id)),
            ],
            'consumable_units_per_unit' => 'nullable|numeric|min:0',
        ]);

        $service->update([
            'name' => $request->name,
            'unit_label' => $request->unit_label,
            'price' => $request->price,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'service_material_id' => $request->service_material_id ?: null,
            'consumable_item_id' => null,
            'consumable_units_per_unit' => (float) ($request->consumable_units_per_unit ?? 0),
        ]);

        return $this->redirectAfterWrite()->with('success', 'Service updated.');
    }

    public function destroyService(Service $service)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'delete_items']);
        $this->ensureServiceAccess($service);
        $business = $this->currentBusinessForWrite();
        $service->delete();
        $business->syncServiceBusinessTypesFromCategories();

        return redirect()->back()->with('success', 'Service removed.');
    }

    public function destroyCategory(ServiceCategory $category)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'delete_items']);
        $this->ensureServiceCategoryAccess($category);
        $business = $this->currentBusinessForWrite();

        $serviceIds = $category->services()->pluck('id');
        $soldCount = $serviceIds->isEmpty()
            ? 0
            : SaleItem::query()->whereIn('service_id', $serviceIds)->count();

        $category->delete();
        $business->syncServiceBusinessTypesFromCategories();

        $message = 'Service category and its services removed.';
        if ($soldCount > 0) {
            $message .= ' Past sales records are kept; only the catalog entries were removed.';
        }

        return redirect()->back()->with('success', $message);
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_services', 'manage_categories', 'add_items']);

        $business = $this->currentBusinessForWrite();
        $branchId = $this->resolveBranchIdFromRequest($request);
        if (! $branchId) {
            return redirect()->back()->with('error', 'Select which branch to import services for.');
        }

        $typesToImport = array_values(array_filter(
            $request->input('template_types', $request->template_type ? [$request->template_type] : [])
        ));

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

            try {
                $result = $this->templateImporter->importCustomForBranch(
                    $business,
                    $branchId,
                    $request->custom_business_name,
                    $categoryNames,
                    $this->parseCustomServiceLines($request->input('custom_services')),
                );
                $this->focusActiveBranchAfterWrite($branchId);

                return $this->redirectAfterWrite()->with(
                    'success',
                    $this->importSuccessMessage(
                        $request->custom_business_name,
                        $result['categories'],
                        $result['services'],
                        $result['category_names'],
                    )
                );
            } catch (\Throwable $e) {
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
            $result = $this->templateImporter->importForBranch($business, $branchId, $typesToImport);
            $this->focusActiveBranchAfterWrite($branchId);

            $label = count($result['labels']) === 1
                ? $result['labels'][0]
                : implode(', ', $result['labels']);

            return $this->redirectAfterWrite()->with(
                'success',
                $this->importSuccessMessage(
                    $label,
                    $result['categories'],
                    $result['services'],
                    $result['category_names'],
                    $result['materials'] ?? 0,
                    $result['linked_services'] ?? 0,
                )
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param  list<string>  $categoryNames
     */
    private function importSuccessMessage(string $label, int $categories, int $services, array $categoryNames, int $materials = 0, int $linkedServices = 0): string
    {
        $parts = ["Imported \"{$label}\"."];

        if ($categories > 0) {
            $parts[] = "{$categories} categor".($categories === 1 ? 'y' : 'ies').' created: '.implode(', ', $categoryNames).'.';
        }

        if ($materials > 0) {
            $parts[] = "{$materials} material".($materials === 1 ? '' : 's').' ready under Services → Materials.';
        }

        if ($linkedServices > 0) {
            $parts[] = "{$linkedServices} service".($linkedServices === 1 ? '' : 's').' linked to materials (paper deducts on sale).';
        }

        if ($services > 0) {
            $parts[] = "{$services} service".($services === 1 ? '' : 's').' added with default prices.';
        }

        return implode(' ', $parts);
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

    private function ensureServiceMaterialAccess(ServiceMaterial $material): void
    {
        if ($material->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        if ($this->branchFilterId() && (int) $material->branch_id !== $this->branchFilterId()) {
            abort(403);
        }
    }

    private function ensureServiceCategoryAccess(ServiceCategory $category): void
    {
        if ($category->business_id !== $this->currentBusinessId()) {
            abort(403);
        }

        if ($this->branchFilterId() && (int) $category->branch_id !== $this->branchFilterId()) {
            abort(403);
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
    private function parseLineList(?string $input): array
    {
        $input = trim($input ?? '');
        if ($input === '') {
            return [];
        }

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
    private function parseCustomServiceLines(?string $input): array
    {
        $input = trim($input ?? '');
        if ($input === '') {
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
