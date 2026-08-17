<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_transfers', function (Blueprint $table) {
            $table->foreignId('received_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('branch_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('received_at');
        });
    }
};
