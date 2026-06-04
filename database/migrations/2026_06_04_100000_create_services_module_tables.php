<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('businesses', 'service_business_types')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->json('service_business_types')->nullable()->after('category_business_types');
            });
        }

        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('source_service_type_key')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'branch_id', 'source_service_type_key'], 'svc_cat_biz_branch_type_idx');
            });
        }

        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 64)->nullable();
                $table->string('unit_label', 64)->default('per service');
                $table->decimal('price', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['business_id', 'branch_id', 'is_active'], 'svc_biz_branch_active_idx');
            });
        }

        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('item_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_items', 'line_description')) {
                $table->string('line_description')->nullable()->after('service_id');
            }
        });

        if (Schema::hasColumn('sale_items', 'item_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->foreignId('item_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'service_id')) {
                $table->dropForeign(['service_id']);
                $table->dropColumn(['service_id', 'line_description']);
            }
        });

        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');

        if (Schema::hasColumn('businesses', 'service_business_types')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('service_business_types');
            });
        }
    }
};
