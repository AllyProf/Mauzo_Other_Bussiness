<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_packagings')) {
            return;
        }

        Schema::table('item_packagings', function (Blueprint $table) {
            if (! Schema::hasColumn('item_packagings', 'manufacturer_barcode')) {
                $table->string('manufacturer_barcode', 128)->nullable()->after('barcode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_packagings')) {
            return;
        }

        Schema::table('item_packagings', function (Blueprint $table) {
            if (Schema::hasColumn('item_packagings', 'manufacturer_barcode')) {
                $table->dropColumn('manufacturer_barcode');
            }
        });
    }
};
