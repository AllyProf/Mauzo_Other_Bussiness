<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_amount', 15, 2);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_type_key')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'period_type', 'period_start', 'branch_id', 'business_type_key', 'user_id'],
                'sales_targets_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
