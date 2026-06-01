<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('location')->nullable()->after('address');
            $table->string('leader_name')->nullable()->after('location');
            $table->string('leader_phone')->nullable()->after('leader_name');
            $table->string('leader_email')->nullable()->after('leader_phone');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['location', 'leader_name', 'leader_phone', 'leader_email']);
        });
    }
};
