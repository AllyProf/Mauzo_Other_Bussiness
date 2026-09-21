<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'expiry_reminder_sent_at')) {
                $table->timestamp('expiry_reminder_sent_at')->nullable()->after('expiry_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'expiry_reminder_sent_at')) {
                $table->dropColumn('expiry_reminder_sent_at');
            }
        });
    }
};
