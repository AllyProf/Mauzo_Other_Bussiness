<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('list_unit_price', 15, 2)->nullable()->after('unit_price');
            $table->string('adjustment_mode')->nullable()->after('subtotal');
            $table->string('discount_type')->nullable()->after('adjustment_mode');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'list_unit_price',
                'adjustment_mode',
                'discount_type',
                'discount_value',
                'discount_amount',
            ]);
        });
    }
};
