<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_communication_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('customer_ids');
            $table->json('channels');
            $table->string('purpose', 50)->default('general');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->json('result_summary')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communication_campaigns');
    }
};
