<?php

use App\Models\Business;
use App\Models\Packaging;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packagings', function (Blueprint $table) {
            $table->string('source_business_type_key')->nullable()->after('name');
        });

        $packagingTemplates = config('packaging_templates', []);
        $templateKeys = array_keys(config('category_templates', []));

        Business::query()->each(function (Business $business) use ($packagingTemplates, $templateKeys) {
            $packagings = Packaging::where('business_id', $business->id)->get();

            foreach ($packagings as $packaging) {
                if ($packaging->source_business_type_key) {
                    continue;
                }

                $matchedKey = null;

                foreach ($templateKeys as $key) {
                    $units = $packagingTemplates[$key] ?? [];

                    if (in_array($packaging->name, $units, true)) {
                        $matchedKey = $key;
                        break;
                    }
                }

                if ($matchedKey) {
                    $packaging->update(['source_business_type_key' => $matchedKey]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('packagings', function (Blueprint $table) {
            $table->dropColumn('source_business_type_key');
        });
    }
};
