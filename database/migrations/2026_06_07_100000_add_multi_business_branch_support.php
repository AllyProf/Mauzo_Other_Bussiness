<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::create('branch_business', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'business_id']);
        });

        $owners = DB::table('users')
            ->where('role', 'owner')
            ->whereNotNull('business_id')
            ->get(['id', 'business_id']);

        foreach ($owners as $owner) {
            DB::table('businesses')
                ->where('id', $owner->business_id)
                ->whereNull('owner_user_id')
                ->update(['owner_user_id' => $owner->id]);
        }

        $branches = DB::table('branches')->get(['id', 'business_id']);

        foreach ($branches as $branch) {
            $ownerUserId = DB::table('businesses')->where('id', $branch->business_id)->value('owner_user_id');

            if ($ownerUserId) {
                DB::table('branches')
                    ->where('id', $branch->id)
                    ->whereNull('owner_user_id')
                    ->update(['owner_user_id' => $ownerUserId]);
            }

            $exists = DB::table('branch_business')
                ->where('branch_id', $branch->id)
                ->where('business_id', $branch->business_id)
                ->exists();

            if (! $exists) {
                DB::table('branch_business')->insert([
                    'branch_id' => $branch->id,
                    'business_id' => $branch->business_id,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_business');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
