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
            $table->unsignedInteger('max_sms')->default(100)->after('max_branches');
            $table->unsignedInteger('max_email_sms')->default(200)->after('max_sms');
        });

        if (Schema::hasTable('plans')) {
            DB::table('plans')->where('name', 'Basic')->update([
                'max_sms' => 50,
                'max_email_sms' => 100,
            ]);
            DB::table('plans')->where('name', 'Professional')->update([
                'max_sms' => 200,
                'max_email_sms' => 500,
            ]);
            DB::table('plans')->where('name', 'Enterprise')->update([
                'max_sms' => 0,
                'max_email_sms' => 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_sms', 'max_email_sms']);
        });
    }
};
