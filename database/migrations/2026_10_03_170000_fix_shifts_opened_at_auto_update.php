<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL gave the first TIMESTAMP column ON UPDATE CURRENT_TIMESTAMP, so every
     * shift update (totals refresh after a payment) moved opened_at to "now".
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE shifts MODIFY opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

        DB::statement('UPDATE shifts SET opened_at = created_at WHERE created_at IS NOT NULL AND opened_at <> created_at');
    }

    public function down(): void
    {
    }
};
