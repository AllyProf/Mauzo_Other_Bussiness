<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->decimal('expected_handover', 15, 2)->nullable()->after('net_amount');
            $table->decimal('actual_received', 15, 2)->nullable()->after('expected_handover');
            $table->decimal('money_short', 15, 2)->default(0)->after('actual_received');
            $table->text('shortage_note')->nullable()->after('money_short');
        });
    }

    public function down(): void
    {
        Schema::table('day_closings', function (Blueprint $table) {
            $table->dropColumn(['expected_handover', 'actual_received', 'money_short', 'shortage_note']);
        });
    }
};
