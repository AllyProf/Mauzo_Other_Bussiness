<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_closing_id')->nullable()->constrained()->nullOnDelete();
            $table->date('report_date');
            $table->decimal('opening_circulation', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('cost_of_goods', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('total_collected', 15, 2)->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->decimal('outstanding_debt', 15, 2)->default(0);
            $table->decimal('staff_expenses', 15, 2)->default(0);
            $table->decimal('owner_expenses', 15, 2)->default(0);
            $table->enum('expense_deduct_from', ['circulation', 'profit'])->default('circulation');
            $table->decimal('net_profit', 15, 2)->default(0);
            $table->decimal('closing_circulation', 15, 2)->default(0);
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->text('owner_notes')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'report_date']);
        });

        Schema::create('business_owner_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_daily_report_id')->nullable()->constrained()->nullOnDelete();
            $table->date('expense_date');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->enum('category', ['restock', 'operational', 'other'])->default('restock');
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owner_expenses');
        Schema::dropIfExists('owner_daily_reports');
    }
};
