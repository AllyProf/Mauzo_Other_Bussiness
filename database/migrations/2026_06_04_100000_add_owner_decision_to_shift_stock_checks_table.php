<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_stock_checks', function (Blueprint $table) {
            $table->string('owner_decision', 32)->nullable()->after('owner_notes');
        });
    }

    public function down(): void
    {
        Schema::table('shift_stock_checks', function (Blueprint $table) {
            $table->dropColumn('owner_decision');
        });
    }
};
