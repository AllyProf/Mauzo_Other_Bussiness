<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('sale_source', 20)->default('pos')->after('reference_no');
            $table->boolean('stock_deducted')->default(true)->after('sale_source');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['sale_source', 'stock_deducted']);
        });
    }
};
