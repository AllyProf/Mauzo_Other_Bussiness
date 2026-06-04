<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->string('recipient_name')->nullable();
            $table->text('message');
            $table->string('channel', 20)->default('sms');
            $table->string('purpose', 50)->default('general');
            $table->string('status', 20)->default('pending');
            $table->text('provider_response')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sms_logs');
    }
};
