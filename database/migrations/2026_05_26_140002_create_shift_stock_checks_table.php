<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_stock_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->enum('check_type', ['opening', 'closing']);
            $table->decimal('system_stock', 15, 2);
            $table->decimal('counted_stock', 15, 2);
            $table->decimal('variance', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['shift_id', 'item_id', 'check_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_stock_checks');
    }
};
