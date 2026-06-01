<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('closing_date');
            $table->string('status')->default('submitted');
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('amount_collected', 15, 2)->default(0);
            $table->decimal('outstanding_sales', 15, 2)->default(0);
            $table->decimal('payments_received', 15, 2)->default(0);
            $table->decimal('cash_received', 15, 2)->default(0);
            $table->decimal('mobile_received', 15, 2)->default(0);
            $table->decimal('bank_received', 15, 2)->default(0);
            $table->unsignedInteger('cancelled_sales')->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->text('report_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_closings');
    }
};
