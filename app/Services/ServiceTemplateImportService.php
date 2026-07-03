<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceMaterial;
use Illuminate\Support\Facades\DB;

class ServiceTemplateImportService
{
    /**
     * @param  list<string>  $templateKeys
     * @return array{labels: list<string>, categories: int, services: int, category_names: list<string>, materials: int, linked_services: int}
     */
    public function importForBranch(Business $business, int $branchId, array $templateKeys): array
    {
        $templates = config('service_templates', []);
        $typesToImport = array_values(array_filter($templateKeys));

        if ($typesToImport === []) {
            return ['labels' => [], 'categories' => 0, 'services' => 0, 'category_names' => [], 'materials' => 0, 'linked_services' => 0];
        }

        $importedLabels = [];
        $categoryNames = [];
        $categoriesCount = 0;
        $servicesCount = 0;
        $materialsCount = 0;
        $linkedServices = 0;

        DB::transaction(function () use ($business, $branchId, $templates, $typesToImport, &$importedLabels, &$categoryNames, &$categoriesCount, &$servicesCount, &$materialsCount, &$linkedServices) {
            foreach ($typesToImport as $templateKey) {
                if (! isset($templates[$templateKey])) {
                    throw new \InvalidArgumentException('Unknown service template: '.$templateKey);
                }

                $template = $templates[$templateKey];
                $label = $template['label'] ?? ucfirst(str_replace('_', ' ', $templateKey));
                $categoryBlocks = $template['categories'] ?? [];
                $namesFromTemplate = collect($categoryBlocks)->pluck('name')->filter()->all();

                $business->registerServiceBusinessType($templateKey, $label, $namesFromTemplate);

                $materialMap = [];
                foreach ($template['materials'] ?? [] as $materialRow) {
                    $matName = (string) ($materialRow['name'] ?? '');
                    if ($matName === '') {
                        continue;
                    }

                    $material = ServiceMaterial::firstOrCreate(
                        [
                            'business_id' => $business->id,
                            'branch_id' => $branchId,
                            'name' => $matName,
                        ],
                        [
                            'unit_label' => (string) ($materialRow['unit_label'] ?? 'piece'),
                        ]
                    );

                    if ($material->wasRecentlyCreated) {
                        $materialsCount++;
                    }

                    $materialMap[$matName] = $material->id;
                }

                foreach ($categoryBlocks as $block) {
                    $catName = (string) ($block['name'] ?? '');
                    if ($catName === '') {
                        continue;
                    }

                    $category = $this->upsertServiceCategory($business->id, $branchId, $catName, $templateKey);
                    $categoriesCount++;
                    $categoryNames[] = $catName;

                    foreach ($block['services'] ?? [] as $svc) {
                        $svcName = (string) ($svc['name'] ?? '');
                        if ($svcName === '') {
                            continue;
                        }

                        $serviceAttrs = [
                            'unit_label' => (string) ($svc['unit_label'] ?? 'per service'),
                            'price' => (float) ($svc['default_price'] ?? 0),
                            'is_active' => true,
                        ];

                        $matName = (string) ($svc['material'] ?? '');
                        if ($matName !== '' && isset($materialMap[$matName])) {
                            $serviceAttrs['service_material_id'] = $materialMap[$matName];
                            $serviceAttrs['consumable_units_per_unit'] = (float) ($svc['material_units'] ?? 1);
                            $linkedServices++;
                        }

                        Service::updateOrCreate(
                            [
                                'business_id' => $business->id,
                                'branch_id' => $branchId,
                                'service_category_id' => $category->id,
                                'name' => $svcName,
                            ],
                            $serviceAttrs
                        );
                        $servicesCount++;
                    }
                }

                $importedLabels[] = $label;
            }

            $business->syncServiceBusinessTypesFromCategories();
        });

        return [
            'labels' => $importedLabels,
            'categories' => $categoriesCount,
            'services' => $servicesCount,
            'category_names' => array_values(array_unique($categoryNames)),
            'materials' => $materialsCount,
            'linked_services' => $linkedServices,
        ];
    }

    /**
     * Link existing services to template materials (for businesses imported before auto-linking).
     *
     * @return array{materials: int, linked: int}
     */
    public function syncMaterialLinksForBranch(Business $business, int $branchId, ?string $templateKey = null): array
    {
        $templates = config('service_templates', []);
        $keys = $templateKey ? [$templateKey] : array_keys($templates);
        $materialsCreated = 0;
        $linked = 0;

        DB::transaction(function () use ($business, $branchId, $templates, $keys, &$materialsCreated, &$linked) {
            foreach ($keys as $key) {
                if (! isset($templates[$key])) {
                    continue;
                }

                $template = $templates[$key];
                $materialMap = [];

                foreach ($template['materials'] ?? [] as $materialRow) {
                    $matName = (string) ($materialRow['name'] ?? '');
                    if ($matName === '') {
                        continue;
                    }

                    $material = ServiceMaterial::firstOrCreate(
                        [
                            'business_id' => $business->id,
                            'branch_id' => $branchId,
                            'name' => $matName,
                        ],
                        [
                            'unit_label' => (string) ($materialRow['unit_label'] ?? 'piece'),
                        ]
                    );

                    if ($material->wasRecentlyCreated) {
                        $materialsCreated++;
                    }

                    $materialMap[$matName] = $material->id;
                }

                foreach ($template['categories'] ?? [] as $block) {
                    $catName = (string) ($block['name'] ?? '');
                    if ($catName === '') {
                        continue;
                    }

                    $category = ServiceCategory::query()
                        ->where('business_id', $business->id)
                        ->where('branch_id', $branchId)
                        ->where('source_service_type_key', $key)
                        ->where('name', $catName)
                        ->first();

                    if (! $category) {
                        continue;
                    }

                    foreach ($block['services'] ?? [] as $svc) {
                        $svcName = (string) ($svc['name'] ?? '');
                        $matName = (string) ($svc['material'] ?? '');
                        if ($svcName === '' || $matName === '' || ! isset($materialMap[$matName])) {
                            continue;
                        }

                        $updated = Service::query()
                            ->where('business_id', $business->id)
                            ->where('branch_id', $branchId)
                            ->where('service_category_id', $category->id)
                            ->where('name', $svcName)
                            ->update([
                                'service_material_id' => $materialMap[$matName],
                                'consumable_units_per_unit' => (float) ($svc['material_units'] ?? 1),
                            ]);

                        if ($updated) {
                            $linked++;
                        }
                    }
                }
            }
        });

        return ['materials' => $materialsCreated, 'linked' => $linked];
    }

    /**
     * @return array{categories: int, services: int, category_names: list<string>}
     */
    public function importCustomForBranch(
        Business $business,
        int $branchId,
        string $businessName,
        array $categoryNames,
        array $serviceRows,
    ): array {
        $customKey = 'custom:'.\Illuminate\Support\Str::slug($businessName);
        $categoriesCount = 0;
        $servicesCount = 0;

        DB::transaction(function () use ($business, $branchId, $businessName, $categoryNames, $serviceRows, $customKey, &$categoriesCount, &$servicesCount) {
            $business->registerServiceBusinessType($customKey, $businessName, $categoryNames);

            $categoryMap = [];
            foreach ($categoryNames as $catName) {
                $categoryMap[$catName] = $this->upsertServiceCategory($business->id, $branchId, $catName, $customKey);
                $categoriesCount++;
            }

            foreach ($serviceRows as $row) {
                $cat = $categoryMap[$row['category']] ?? null;
                if (! $cat) {
                    continue;
                }

                Service::updateOrCreate(
                    [
                        'business_id' => $business->id,
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
                $servicesCount++;
            }

            $business->syncServiceBusinessTypesFromCategories();
        });

        return [
            'categories' => $categoriesCount,
            'services' => $servicesCount,
            'category_names' => $categoryNames,
        ];
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
}
