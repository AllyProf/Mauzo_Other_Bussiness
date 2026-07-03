<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_materials')) {
            Schema::create('service_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('unit_label', 64)->default('piece');
                $table->decimal('current_stock', 15, 4)->default(0);
                $table->decimal('last_cost_per_unit', 15, 4)->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'branch_id', 'name'], 'svc_mat_biz_branch_name_uq');
            });
        }

        if (! Schema::hasTable('service_material_receipts')) {
            Schema::create('service_material_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_material_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('quantity', 15, 4);
                $table->decimal('total_cost', 15, 2)->default(0);
                $table->date('received_date');
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'service_material_id')) {
                $table->foreignId('service_material_id')->nullable()->after('consumable_item_id')->constrained('service_materials')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'service_material_id')) {
                $table->dropForeign(['service_material_id']);
                $table->dropColumn('service_material_id');
            }
        });

        Schema::dropIfExists('service_material_receipts');
        Schema::dropIfExists('service_materials');
    }
};
