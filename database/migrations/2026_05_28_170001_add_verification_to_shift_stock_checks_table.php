<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_stock_checks', function (Blueprint $table) {
            $table->foreignId('verified_by')->nullable()->after('recorded_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('owner_notes')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('shift_stock_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified_at', 'owner_notes']);
        });
    }
};
