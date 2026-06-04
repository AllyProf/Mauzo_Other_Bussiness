<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_short_settlements', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('recorded_by');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('money_short_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });
    }
};
