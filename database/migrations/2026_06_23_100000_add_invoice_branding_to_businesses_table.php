<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('contact_person');
            $table->string('vat_number')->nullable()->after('tin_number');
            $table->decimal('vat_rate', 5, 2)->nullable()->after('vat_number');
            $table->boolean('invoice_show_vat')->default(false)->after('vat_rate');
            $table->boolean('invoice_vat_inclusive')->default(true)->after('invoice_show_vat');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'vat_number',
                'vat_rate',
                'invoice_show_vat',
                'invoice_vat_inclusive',
            ]);
        });
    }
};
