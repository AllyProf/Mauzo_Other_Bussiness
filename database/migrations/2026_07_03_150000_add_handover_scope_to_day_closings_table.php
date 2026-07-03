<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->string('handover_scope', 20)->nullable()->after('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropColumn('handover_scope');
        });
    }
};
