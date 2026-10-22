<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update([
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);
    }

    public function down(): void
    {
        // No restore of prior lock state.
    }
};
