<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
        });

        if (! Schema::hasTable('receivings')) {
            return;
        }

        $receivings = DB::table('receivings')->select('id', 'business_id', 'user_id')->get();

        foreach ($receivings as $receiving) {
            $branchId = DB::table('users')->where('id', $receiving->user_id)->value('branch_id');

            if (! $branchId) {
                $branchId = DB::table('branches')
                    ->where('business_id', $receiving->business_id)
                    ->where('is_default', true)
                    ->value('id');
            }

            if (! $branchId) {
                $branchId = DB::table('branches')
                    ->where('business_id', $receiving->business_id)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($branchId) {
                DB::table('receivings')->where('id', $receiving->id)->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
