<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy rows without scope are treated as retail handovers.
        DB::table('day_closings')
            ->whereNull('handover_scope')
            ->update(['handover_scope' => 'retail']);

        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropUnique(['shift_id']);
            $table->unique(['shift_id', 'handover_scope'], 'day_closings_shift_scope_unique');
            $table->foreign('shift_id')
                ->references('id')
                ->on('shifts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropUnique('day_closings_shift_scope_unique');
            $table->unique('shift_id');
            $table->foreign('shift_id')
                ->references('id')
                ->on('shifts')
                ->nullOnDelete();
        });
    }
};
