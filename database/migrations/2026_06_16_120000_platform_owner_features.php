<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('login_identifier', 255);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at');
            $table->index(['attempted_at', 'login_identifier']);
        });

        Schema::create('platform_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('company')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 50)->default('landing');
            $table->string('status', 30)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('registration_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->nullable();
            $table->string('event', 80);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event', 'created_at']);
        });

        Schema::create('business_onboarding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->json('completed_steps')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique('business_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_admin_role', 30)->nullable()->after('role');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('admin_read_at')->nullable()->after('status');
        });

        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->timestamp('expiry_reminder_sent_at')->nullable()->after('emailed_at');
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('expiry_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['expiry_reminder_sent_at', 'payment_reminder_sent_at']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('admin_read_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_admin_role');
        });

        Schema::dropIfExists('business_onboarding');
        Schema::dropIfExists('registration_funnel_events');
        Schema::dropIfExists('platform_leads');
        Schema::dropIfExists('failed_login_attempts');
    }
};
