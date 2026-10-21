<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_packaging_unit_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_packaging_id')->constrained('item_packagings')->cascadeOnDelete();
            $table->string('barcode', 128);
            $table->timestamps();

            $table->unique(['business_id', 'barcode'], 'item_packaging_unit_barcodes_business_barcode_unique');
            $table->index(['item_packaging_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_packaging_unit_barcodes');
    }
};
