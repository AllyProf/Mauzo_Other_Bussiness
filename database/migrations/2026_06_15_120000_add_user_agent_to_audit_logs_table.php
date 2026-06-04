<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->index(['business_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropColumn('user_agent');
        });
    }
};
