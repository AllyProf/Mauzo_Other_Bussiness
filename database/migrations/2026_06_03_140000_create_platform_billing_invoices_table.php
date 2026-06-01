<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('billing_month');
            $table->string('invoice_number')->unique();
            $table->string('billing_model', 30);
            $table->string('profit_basis', 30)->nullable();
            $table->decimal('profit_amount', 14, 2)->nullable();
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_billing_invoices');
    }
};
