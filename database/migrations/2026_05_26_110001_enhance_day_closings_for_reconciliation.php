<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->json('payment_breakdown')->nullable()->after('bank_received');
            $table->foreignId('verified_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('dispute_reason')->nullable()->after('verified_at');
        });

        Schema::table('day_closing_expenses', function (Blueprint $table) {
            $table->string('payment_method')->default('cash')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('day_closing_expenses', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });

        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['payment_breakdown', 'verified_by', 'verified_at', 'dispute_reason']);
        });
    }
};
