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
            $table->json('enabled_features')->nullable()->after('features');
        });

        if (Schema::hasTable('plans')) {
            $allFeatures = collect(config('plan_features.groups', []))
                ->flatMap(fn ($group) => array_keys($group))
                ->values()
                ->all();

            DB::table('plans')->whereNull('enabled_features')->update([
                'enabled_features' => json_encode($allFeatures),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('enabled_features');
        });
    }
};
