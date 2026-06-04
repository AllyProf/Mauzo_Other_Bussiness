<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_owner_expenses', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->string('business_type_key', 100)->nullable()->after('branch_id');
            $table->index(['business_id', 'branch_id', 'business_type_key', 'expense_date'], 'owner_expenses_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('business_owner_expenses', function (Blueprint $table) {
            $table->dropIndex('owner_expenses_scope_idx');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('business_type_key');
        });
    }
};
