<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'consumable_item_id')) {
                $table->foreignId('consumable_item_id')->nullable()->after('is_active')->constrained('items')->nullOnDelete();
            }
            if (! Schema::hasColumn('services', 'consumable_units_per_unit')) {
                $table->decimal('consumable_units_per_unit', 12, 4)->default(0)->after('consumable_item_id');
            }
        });

        if (! Schema::hasColumn('sales', 'consumables_deducted')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->boolean('consumables_deducted')->default(false)->after('stock_deducted');
            });
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'consumable_item_id')) {
                $table->dropForeign(['consumable_item_id']);
                $table->dropColumn(['consumable_item_id', 'consumable_units_per_unit']);
            }
        });

        if (Schema::hasColumn('sales', 'consumables_deducted')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('consumables_deducted');
            });
        }
    }
};
