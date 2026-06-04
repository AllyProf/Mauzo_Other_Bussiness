<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_sms_logs', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_communication_campaigns')
                ->nullOnDelete();
            $table->string('recipient_email')->nullable()->after('phone');
            $table->string('phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_sms_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn('recipient_email');
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
