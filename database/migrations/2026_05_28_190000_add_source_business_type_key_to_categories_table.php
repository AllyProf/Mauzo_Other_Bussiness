<?php

use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('source_business_type_key')->nullable()->after('name');
        });

        $templates = config('category_templates', []);

        Business::query()->whereNotNull('category_business_types')->each(function (Business $business) use ($templates) {
            foreach ($business->categoryBusinessTypesList() as $type) {
                $key = $type['key'] ?? null;
                if (! $key) {
                    continue;
                }

                $names = $type['categories'] ?? ($templates[$key]['categories'] ?? []);

                if (empty($names)) {
                    continue;
                }

                Category::where('business_id', $business->id)
                    ->whereIn('name', $names)
                    ->where(function ($query) use ($key) {
                        $query->whereNull('source_business_type_key')
                            ->orWhere('source_business_type_key', $key);
                    })
                    ->update(['source_business_type_key' => $key]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('source_business_type_key');
        });
    }
};
