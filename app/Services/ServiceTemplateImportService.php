<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

class ServiceTemplateImportService
{
    /**
     * @param  list<string>  $templateKeys
     */
    public function importForBranch(Business $business, int $branchId, array $templateKeys): array
    {
        $templates = config('service_templates', []);
        $typesToImport = array_values(array_filter($templateKeys));

        if ($typesToImport === []) {
            return [];
        }

        $importedLabels = [];

        DB::transaction(function () use ($business, $branchId, $templates, $typesToImport, &$importedLabels) {
            foreach ($typesToImport as $templateKey) {
                if (! isset($templates[$templateKey])) {
                    throw new \InvalidArgumentException('Unknown service template: '.$templateKey);
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

                    $category = $this->upsertServiceCategory($business->id, $branchId, $catName, $templateKey);

                    foreach ($block['services'] ?? [] as $svc) {
                        $svcName = (string) ($svc['name'] ?? '');
                        if ($svcName === '') {
                            continue;
                        }

                        Service::updateOrCreate(
                            [
                                'business_id' => $business->id,
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
        });

        return $importedLabels;
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
