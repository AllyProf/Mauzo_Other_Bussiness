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
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('id');
            $table->date('expiry_date')->nullable()->after('email');
            
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'expiry_date']);
        });
    }
};
