<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('billing_model', 30)->default('fixed_monthly')->after('price');
            $table->decimal('profit_share_percent', 5, 2)->default(0)->after('billing_model');
            $table->string('profit_share_basis', 30)->default('net_profit')->after('profit_share_percent');
            $table->decimal('minimum_monthly_fee', 12, 2)->default(0)->after('profit_share_basis');
        });

        if (Schema::hasTable('plans')) {
            DB::table('plans')->update([
                'billing_model' => 'fixed_monthly',
                'profit_share_percent' => 0,
                'profit_share_basis' => 'net_profit',
                'minimum_monthly_fee' => 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'billing_model',
                'profit_share_percent',
                'profit_share_basis',
                'minimum_monthly_fee',
            ]);
        });
    }
};
