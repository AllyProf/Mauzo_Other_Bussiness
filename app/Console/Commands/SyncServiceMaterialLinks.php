<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\ServiceCategory;
use App\Services\ServiceTemplateImportService;
use Illuminate\Console\Command;

class SyncServiceMaterialLinks extends Command
{
    protected $signature = 'services:sync-material-links {--business= : Business ID} {--branch= : Branch ID} {--template= : Template key e.g. stationery_print}';

    protected $description = 'Create service materials and link print services for existing imports';

    public function handle(ServiceTemplateImportService $importer): int
    {
        $businessId = $this->option('business');
        $branchId = $this->option('branch');
        $templateKey = $this->option('template');

        $pairs = collect();

        if ($businessId && $branchId) {
            $pairs->push([(int) $businessId, (int) $branchId]);
        } else {
            $pairs = ServiceCategory::query()
                ->select('business_id', 'branch_id')
                ->whereNotNull('branch_id')
                ->distinct()
                ->get()
                ->map(fn ($row) => [(int) $row->business_id, (int) $row->branch_id]);
        }

        if ($pairs->isEmpty()) {
            $this->warn('No service categories found.');

            return self::SUCCESS;
        }

        $totalMaterials = 0;
        $totalLinked = 0;

        foreach ($pairs as [$bizId, $brId]) {
            $business = Business::find($bizId);
            if (! $business) {
                continue;
            }

            $result = $importer->syncMaterialLinksForBranch($business, $brId, $templateKey ?: null);
            $totalMaterials += $result['materials'];
            $totalLinked += $result['linked'];

            $this->line("Business {$bizId}, branch {$brId}: {$result['materials']} material(s) created, {$result['linked']} service(s) linked.");
        }

        $this->info("Done. {$totalMaterials} new material(s), {$totalLinked} service link(s) updated.");

        return self::SUCCESS;
    }
}
