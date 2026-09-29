<?php

use App\Services\PlatformSettingsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PlatformSettingsService::class)->update([
            'support_email' => 'emca@emca.tech',
            'support_phone' => '+255 749 719 998',
        ]);
    }

    public function down(): void
    {
    }
};
