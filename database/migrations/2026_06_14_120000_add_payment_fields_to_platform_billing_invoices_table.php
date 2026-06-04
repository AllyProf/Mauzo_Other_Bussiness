<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('emailed_at');
            $table->string('payment_reference')->nullable()->after('paid_at');
            $table->text('payment_notes')->nullable()->after('payment_reference');
            $table->foreignId('marked_paid_by')->nullable()->after('payment_notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marked_paid_by');
            $table->dropColumn(['paid_at', 'payment_reference', 'payment_notes']);
        });
    }
};
