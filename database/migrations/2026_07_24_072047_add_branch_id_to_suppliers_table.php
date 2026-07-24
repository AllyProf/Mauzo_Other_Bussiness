<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->index(['business_id', 'branch_id']);
        });

        // Backfill existing suppliers onto each business's default/first branch.
        $businessIds = DB::table('suppliers')->distinct()->pluck('business_id');
        foreach ($businessIds as $businessId) {
            $branchId = DB::table('branches')
                ->where('business_id', $businessId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');

            if (! $branchId) {
                $branchId = DB::table('branches')
                    ->where('business_id', $businessId)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($branchId) {
                DB::table('suppliers')
                    ->where('business_id', $businessId)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['business_id', 'branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
