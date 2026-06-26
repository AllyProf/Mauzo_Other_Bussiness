<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->integer('custom_sms_limit')->nullable()->after('plan_id');
            $table->integer('custom_storage_limit')->nullable()->after('custom_sms_limit');
            $table->json('feature_overrides')->nullable()->after('custom_storage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['custom_sms_limit', 'custom_storage_limit', 'feature_overrides']);
        });
    }
};
