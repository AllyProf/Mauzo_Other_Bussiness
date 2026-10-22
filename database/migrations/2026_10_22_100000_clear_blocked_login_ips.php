<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blocked_ips')) {
            return;
        }

        DB::table('blocked_ips')->update([
            'failed_attempts' => 0,
            'blocked_until' => null,
            'cleared_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
