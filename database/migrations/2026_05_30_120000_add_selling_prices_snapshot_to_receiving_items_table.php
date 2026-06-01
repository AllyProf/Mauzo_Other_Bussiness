<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->json('selling_prices_snapshot')->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->dropColumn('selling_prices_snapshot');
        });
    }
};
