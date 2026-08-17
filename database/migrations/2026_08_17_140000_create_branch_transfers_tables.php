<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->unique();
            $table->date('transfer_date');
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('total_pieces', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->index(['business_id', 'transfer_date']);
            $table->index(['from_branch_id', 'to_branch_id']);
        });

        Schema::create('branch_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_transfer_id')->constrained('branch_transfers')->cascadeOnDelete();
            $table->foreignId('from_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('to_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('from_stock_before', 12, 2)->default(0);
            $table->decimal('to_stock_before', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfer_items');
        Schema::dropIfExists('branch_transfers');
    }
};
