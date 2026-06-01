<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_daily_reports', function (Blueprint $table) {
            $table->decimal('opening_profit', 15, 2)->default(0)->after('net_profit');
            $table->decimal('closing_profit', 15, 2)->default(0)->after('opening_profit');
        });
    }

    public function down(): void
    {
        Schema::table('owner_daily_reports', function (Blueprint $table) {
            $table->dropColumn(['opening_profit', 'closing_profit']);
        });
    }
};
