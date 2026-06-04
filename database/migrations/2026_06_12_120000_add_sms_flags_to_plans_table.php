<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('allow_sms_sending')->default(true)->after('max_email_sms');
            $table->boolean('allow_email_sms')->default(true)->after('allow_sms_sending');
        });

        if (Schema::hasTable('plans')) {
            $featureKey = 'customer_communication';
            foreach (\App\Models\Plan::all() as $plan) {
                $features = $plan->enabled_features ?? [];
                if ($features === null) {
                    continue;
                }
                if (! in_array($featureKey, $features, true)) {
                    $features[] = $featureKey;
                    $plan->update(['enabled_features' => $features]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['allow_sms_sending', 'allow_email_sms']);
        });
    }
};
