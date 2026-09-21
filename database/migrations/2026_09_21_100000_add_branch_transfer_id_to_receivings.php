<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            if (! Schema::hasColumn('receivings', 'branch_transfer_id')) {
                $table->foreignId('branch_transfer_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('branch_transfers')
                    ->nullOnDelete();
                $table->unique('branch_transfer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receivings', function (Blueprint $table) {
            if (Schema::hasColumn('receivings', 'branch_transfer_id')) {
                $table->dropUnique(['branch_transfer_id']);
                $table->dropConstrainedForeignId('branch_transfer_id');
            }
        });
    }
};
