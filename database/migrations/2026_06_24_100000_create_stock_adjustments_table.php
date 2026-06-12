<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->unique();
            $table->date('adjustment_date');
            $table->string('reason', 50);
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('net_adjustment', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('completed');
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_stock', 15, 2);
            $table->decimal('new_stock', 15, 2);
            $table->decimal('adjustment_qty', 15, 2);
            $table->string('line_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
    }
};
