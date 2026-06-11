<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->string('qty_mode', 10)->default('pkg')->after('quantity');
            $table->string('cost_mode', 10)->default('pkg')->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->dropColumn(['qty_mode', 'cost_mode']);
        });
    }
};
