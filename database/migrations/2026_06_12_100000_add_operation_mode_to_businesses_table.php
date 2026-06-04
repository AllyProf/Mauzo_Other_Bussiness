<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('businesses', 'operation_mode')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('operation_mode', 20)->default('both')->after('service_business_types');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('businesses', 'operation_mode')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('operation_mode');
            });
        }
    }
};
