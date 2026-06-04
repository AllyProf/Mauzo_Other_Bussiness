<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
        });

        foreach (DB::table('categories')->orderBy('id')->get() as $category) {
            $branchId = DB::table('receiving_items')
                ->join('receivings', 'receiving_items.receiving_id', '=', 'receivings.id')
                ->join('items', 'receiving_items.item_id', '=', 'items.id')
                ->where('items.category_id', $category->id)
                ->where('receivings.status', '!=', 'cancelled')
                ->select('receivings.branch_id', DB::raw('COUNT(*) as usage_count'))
                ->groupBy('receivings.branch_id')
                ->orderByDesc('usage_count')
                ->value('branch_id');

            if (! $branchId) {
                $branchId = DB::table('branches')
                    ->where('business_id', $category->business_id)
                    ->where('is_default', true)
                    ->value('id');

                if (! $branchId) {
                    $branchId = DB::table('branch_business')
                        ->where('business_id', $category->business_id)
                        ->where('is_default', true)
                        ->value('branch_id');
                }

                if (! $branchId) {
                    $branchId = DB::table('branches')
                        ->where('business_id', $category->business_id)
                        ->orderBy('id')
                        ->value('id');
                }
            }

            if ($branchId) {
                DB::table('categories')->where('id', $category->id)->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
