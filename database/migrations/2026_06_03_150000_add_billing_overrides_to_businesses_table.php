<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('billing_model', 30)->nullable()->after('plan_id');
            $table->decimal('billing_price', 12, 2)->nullable()->after('billing_model');
            $table->decimal('profit_share_percent', 5, 2)->nullable()->after('billing_price');
            $table->string('profit_share_basis', 30)->nullable()->after('profit_share_percent');
            $table->decimal('minimum_monthly_fee', 12, 2)->nullable()->after('profit_share_basis');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'billing_model',
                'billing_price',
                'profit_share_percent',
                'profit_share_basis',
                'minimum_monthly_fee',
            ]);
        });
    }
};
