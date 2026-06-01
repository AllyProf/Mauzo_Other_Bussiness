<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_branches')->default(1)->after('max_business_types');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
        });

        Business::query()->each(function (Business $business) {
            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'address' => $business->address,
                'phone' => $business->phone,
                'is_active' => true,
                'is_default' => true,
            ]);

            User::where('business_id', $business->id)
                ->where('role', 'staff')
                ->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);
        });

        if (Schema::hasTable('plans')) {
            DB::table('plans')->where('name', 'Basic')->update(['max_branches' => 1]);
            DB::table('plans')->where('name', 'Professional')->update(['max_branches' => 3]);
            DB::table('plans')->where('name', 'Enterprise')->update(['max_branches' => 0]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_branches');
        });
    }
};
