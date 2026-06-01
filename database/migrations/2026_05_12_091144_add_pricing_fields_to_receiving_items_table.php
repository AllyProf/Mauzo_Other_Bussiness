<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->default(0)->after('cost_price');
            $table->string('discount_type')->nullable()->after('selling_price'); // 'fixed' or 'percent'
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('receiving_items', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
