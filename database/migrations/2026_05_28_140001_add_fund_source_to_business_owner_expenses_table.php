<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_owner_expenses', function (Blueprint $table) {
            $table->enum('fund_source', ['circulation', 'profit'])->default('circulation')->after('category');
        });

        DB::statement("ALTER TABLE business_owner_expenses MODIFY category ENUM('restock', 'operational', 'payment', 'salary', 'other') NOT NULL DEFAULT 'restock'");
    }

    public function down(): void
    {
        Schema::table('business_owner_expenses', function (Blueprint $table) {
            $table->dropColumn('fund_source');
        });

        DB::statement("ALTER TABLE business_owner_expenses MODIFY category ENUM('restock', 'operational', 'other') NOT NULL DEFAULT 'restock'");
    }
};
