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
        // In MySQL, to update an ENUM safely we use raw SQL
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE sales MODIFY COLUMN payment_status ENUM('pending', 'paid', 'debt', 'partial', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE sales MODIFY COLUMN payment_status ENUM('pending', 'paid', 'debt') DEFAULT 'pending'");
    }
};
