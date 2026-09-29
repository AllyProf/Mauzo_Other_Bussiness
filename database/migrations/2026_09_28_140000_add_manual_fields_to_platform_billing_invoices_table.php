<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->index('business_id', 'pbi_business_id_index');
        });

        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'billing_month']);
            $table->boolean('is_manual')->default(false)->after('billing_month');
            $table->string('description', 255)->nullable()->after('is_manual');
            $table->unsignedInteger('quantity')->nullable()->after('description');
            $table->decimal('unit_price', 14, 2)->nullable()->after('quantity');
            $table->index(['business_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'billing_month']);
            $table->dropColumn(['is_manual', 'description', 'quantity', 'unit_price']);
            $table->unique(['business_id', 'billing_month']);
        });

        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->dropIndex('pbi_business_id_index');
        });
    }
};
