<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_packagings', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->after('selling_price');
            $table->index('barcode');
        });

        // Backfill existing packaging rows with unique barcodes.
        $rows = DB::table('item_packagings')
            ->join('items', 'items.id', '=', 'item_packagings.item_id')
            ->select('item_packagings.id', 'items.business_id')
            ->whereNull('item_packagings.barcode')
            ->orderBy('item_packagings.id')
            ->get();

        foreach ($rows as $row) {
            $code = sprintf('ML%04d%08d', (int) $row->business_id, (int) $row->id);
            DB::table('item_packagings')->where('id', $row->id)->update(['barcode' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('item_packagings', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
