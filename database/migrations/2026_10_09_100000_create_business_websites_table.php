<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->boolean('is_published')->default(false);
            $table->string('tagline', 255)->nullable();
            $table->string('primary_color', 20)->default('#940000');
            $table->json('social_links')->nullable();
            $table->json('pages')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_websites');
    }
};
