<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_business_types')->default(1)->after('max_users');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->json('category_business_types')->nullable()->after('automation_settings');
        });

        if (Schema::hasColumn('businesses', 'category_business_type')) {
            foreach (DB::table('businesses')->whereNotNull('category_business_type')->get() as $business) {
                DB::table('businesses')->where('id', $business->id)->update([
                    'category_business_types' => json_encode([[
                        'key' => $business->category_template_key ?? 'custom',
                        'label' => $business->category_business_type,
                    ]]),
                ]);
            }

            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn(['category_business_type', 'category_template_key']);
            });
        }

        if (Schema::hasTable('plans')) {
            DB::table('plans')->where('name', 'Basic')->update(['max_business_types' => 1]);
            DB::table('plans')->where('name', 'Professional')->update(['max_business_types' => 2]);
            DB::table('plans')->where('name', 'Enterprise')->update(['max_business_types' => 0]);
        }
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('category_business_type')->nullable()->after('automation_settings');
            $table->string('category_template_key')->nullable()->after('category_business_type');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_business_types');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('category_business_types');
        });
    }
};
