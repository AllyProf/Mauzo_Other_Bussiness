<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'paid', 'debt'])->default('pending');
            $table->string('payment_provider')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->date('due_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_provider',
                'transaction_reference',
                'customer_name',
                'customer_phone',
                'due_date'
            ]);
        });
    }
};
