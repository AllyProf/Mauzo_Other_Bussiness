<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->enum('expense_deduct_from', ['circulation', 'profit'])->default('circulation')->after('is_active');
            $table->decimal('circulation_balance', 15, 2)->default(0)->after('expense_deduct_from');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['expense_deduct_from', 'circulation_balance']);
        });
    }
};
