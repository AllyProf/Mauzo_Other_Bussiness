<?php

namespace App\Concerns;

use App\Models\ServiceCategory;

trait ManagesServiceBusinessTypes
{
    public function serviceBusinessTypesList(): array
    {
        return $this->service_business_types ?? [];
    }

    public function hasServiceBusinessType(string $key): bool
    {
        foreach ($this->serviceBusinessTypesList() as $type) {
            if (($type['key'] ?? '') === $key) {
                return true;
            }
        }

        return false;
    }

    public function registerServiceBusinessType(string $key, string $label, array $categoryNames = []): void
    {
        $types = $this->serviceBusinessTypesList();

        foreach ($types as &$type) {
            if (($type['key'] ?? '') === $key) {
                $existing = $type['categories'] ?? [];
                $type['label'] = $label;
                $type['categories'] = array_values(array_unique(array_merge($existing, $categoryNames)));
                $this->update(['service_business_types' => $types]);

                return;
            }
        }
        unset($type);

        $types[] = [
            'key' => $key,
            'label' => $label,
            'categories' => array_values(array_unique($categoryNames)),
        ];

        $this->update(['service_business_types' => $types]);
    }

    public function importedServiceTypesForBranch(int $branchId): array
    {
        $categories = ServiceCategory::query()
            ->where('business_id', $this->id)
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get(['name', 'source_service_type_key']);

        return $this->importedServiceTypesFromCategories($categories);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceCategory>|\Illuminate\Database\Eloquent\Collection<int, ServiceCategory>  $categories
     * @return list<array{key: string, label: string, categories: list<string>}>
     */
    public function importedServiceTypesFromCategories($categories): array
    {
        $templates = config('service_templates', []);
        $registered = collect($this->serviceBusinessTypesList())->keyBy(fn ($type) => (string) ($type['key'] ?? ''));

        return collect($categories)
            ->groupBy(fn (ServiceCategory $category) => $category->source_service_type_key ?: 'other')
            ->filter(fn ($group, $key) => $key !== 'other' && $key !== '')
            ->map(function ($group, $key) use ($registered, $templates) {
                $registeredType = $registered->get((string) $key);

                return [
                    'key' => (string) $key,
                    'label' => (string) ($registeredType['label'] ?? $templates[$key]['label'] ?? ucfirst(str_replace('_', ' ', (string) $key))),
                    'categories' => $group->pluck('name')->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function syncServiceBusinessTypesFromCategories(): void
    {
        $remaining = ServiceCategory::query()
            ->where('business_id', $this->id)
            ->orderBy('name')
            ->get(['name', 'source_service_type_key']);

        if ($remaining->isEmpty()) {
            $this->update(['service_business_types' => null]);

            return;
        }

        $templates = config('service_templates', []);
        $existingTypes = collect($this->serviceBusinessTypesList())->keyBy(fn ($type) => (string) ($type['key'] ?? ''));
        $types = [];

        foreach ($remaining->groupBy(fn ($category) => $category->source_service_type_key ?: 'other') as $key => $categories) {
            if ($key === 'other' || $key === '') {
                continue;
            }

            $existing = $existingTypes->get($key);
            $label = (string) ($existing['label'] ?? $templates[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key)));

            $types[] = [
                'key' => $key,
                'label' => $label,
                'categories' => $categories->pluck('name')->unique()->values()->all(),
            ];
        }

        $this->update(['service_business_types' => empty($types) ? null : $types]);
    }

    public function serviceTypeLabel(string $key): string
    {
        foreach ($this->serviceBusinessTypesList() as $type) {
            if (($type['key'] ?? '') === $key) {
                return (string) ($type['label'] ?? $key);
            }
        }

        $templates = config('service_templates', []);

        return (string) ($templates[$key]['label'] ?? $key);
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public function servicePosTypesMeta(): array
    {
        $templates = config('service_templates', []);

        return collect($this->serviceBusinessTypesList())
            ->map(function ($type) use ($templates) {
                $key = (string) ($type['key'] ?? '');

                return [
                    'key' => $key,
                    'label' => (string) ($type['label'] ?? 'Services'),
                    'icon' => $templates[$key]['icon'] ?? 'fa-briefcase',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public function branchServicePosTypesMeta(int $branchId): array
    {
        $templates = config('service_templates', []);

        return collect($this->importedServiceTypesForBranch($branchId))
            ->map(function ($type) use ($templates) {
                $key = (string) ($type['key'] ?? '');

                return [
                    'key' => $key,
                    'label' => (string) ($type['label'] ?? $key),
                    'icon' => $templates[$key]['icon'] ?? 'fa-briefcase',
                ];
            })
            ->values()
            ->all();
    }
}
