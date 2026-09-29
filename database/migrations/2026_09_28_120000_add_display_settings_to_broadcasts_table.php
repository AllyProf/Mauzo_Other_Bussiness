<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('scroll_speed', 20)->default('slow')->after('is_active');
            $table->string('text_color', 7)->default('#ffffff')->after('scroll_speed');
            $table->string('font_family', 40)->default('century_gothic')->after('text_color');
            $table->unsignedTinyInteger('font_size')->default(14)->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['scroll_speed', 'text_color', 'font_family', 'font_size']);
        });
    }
};
