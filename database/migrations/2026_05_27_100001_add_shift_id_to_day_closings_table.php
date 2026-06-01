<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->foreignId('shift_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('day_closings', function (Blueprint $table) {
            $table->index('business_id', 'day_closings_business_id_lookup');
            $table->dropUnique(['business_id', 'closing_date']);
            $table->unique('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropUnique(['shift_id']);
            $table->unique(['business_id', 'closing_date']);
            $table->dropIndex('day_closings_business_id_lookup');
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
