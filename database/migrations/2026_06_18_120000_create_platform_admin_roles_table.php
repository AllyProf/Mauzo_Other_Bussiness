<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('platform_admin_role_id')
                ->nullable()
                ->after('platform_admin_role')
                ->constrained('platform_admin_roles')
                ->nullOnDelete();
        });

        $now = now();
        $legacyRoles = config('platform_admin.roles', []);

        foreach ($legacyRoles as $slug => $permissions) {
            DB::table('platform_admin_roles')->insert([
                'name' => match ($slug) {
                    'full' => 'Full Access',
                    'billing' => 'Billing',
                    'support' => 'Support',
                    'readonly' => 'Read Only',
                    default => ucfirst(str_replace('_', ' ', $slug)),
                },
                'slug' => $slug,
                'description' => null,
                'permissions' => json_encode($permissions),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIdsBySlug = DB::table('platform_admin_roles')->pluck('id', 'slug');

        $users = DB::table('users')
            ->where('role', 'platform_staff')
            ->whereNotNull('platform_admin_role')
            ->get(['id', 'platform_admin_role']);

        foreach ($users as $user) {
            $slug = $user->platform_admin_role ?: 'readonly';
            $roleId = $roleIdsBySlug[$slug] ?? $roleIdsBySlug['readonly'] ?? null;

            if ($roleId) {
                DB::table('users')->where('id', $user->id)->update([
                    'platform_admin_role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_admin_role_id');
        });

        Schema::dropIfExists('platform_admin_roles');
    }
};
