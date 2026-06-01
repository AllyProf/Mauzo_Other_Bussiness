<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('units_per_receiving_pack')->default(1)->after('receiving_packaging_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('item_packaging_id')
                ->nullable()
                ->after('item_id')
                ->constrained('item_packagings')
                ->nullOnDelete();
        });

        $items = DB::table('items')->select('id')->get();

        foreach ($items as $item) {
            $firstPackaging = DB::table('item_packagings')
                ->where('item_id', $item->id)
                ->orderBy('id')
                ->first();

            if (! $firstPackaging) {
                continue;
            }

            $packagingCount = DB::table('item_packagings')->where('item_id', $item->id)->count();
            $unitsPerReceiving = max(1, (int) $firstPackaging->quantity_per_unit);

            DB::table('items')
                ->where('id', $item->id)
                ->update(['units_per_receiving_pack' => $unitsPerReceiving]);

            if ($packagingCount === 1 && $unitsPerReceiving > 1) {
                DB::table('item_packagings')
                    ->where('id', $firstPackaging->id)
                    ->update(['quantity_per_unit' => 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_packaging_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('units_per_receiving_pack');
        });
    }
};
