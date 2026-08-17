<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_transfer_items', function (Blueprint $table) {
            $table->foreignId('item_packaging_id')->nullable()->after('to_item_id')->constrained('item_packagings')->nullOnDelete();
            $table->string('unit_name', 100)->nullable()->after('item_packaging_id');
            $table->decimal('unit_quantity', 12, 2)->nullable()->after('unit_name');
            $table->unsignedInteger('quantity_per_unit')->default(1)->after('unit_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('branch_transfer_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_packaging_id');
            $table->dropColumn(['unit_name', 'unit_quantity', 'quantity_per_unit']);
        });
    }
};
