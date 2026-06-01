<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_losses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->unique();
            $table->date('loss_date');
            $table->string('reason', 50);
            $table->decimal('total_quantity', 15, 2)->default(0);
            $table->decimal('total_cost_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('completed');
            $table->timestamps();
        });

        Schema::create('stock_loss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_loss_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('cost_value', 15, 2)->default(0);
            $table->string('line_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_loss_items');
        Schema::dropIfExists('stock_losses');
    }
};
