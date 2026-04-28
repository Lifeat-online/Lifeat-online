<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Content Manager', 'slug' => 'content_manager', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sales Staff', 'slug' => 'sales_staff', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Support', 'slug' => 'support', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Writer', 'slug' => 'writer', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Business Owner', 'slug' => 'business_owner', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Registered User', 'slug' => 'registered_user', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('roles')->insert($roles);

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $legacyRoleMap = [
            'admin' => 'super_admin',
            'editor' => 'content_manager',
            'staff' => 'sales_staff',
            'support' => 'support',
            'writer' => 'writer',
            'business_owner' => 'business_owner',
            'member' => 'registered_user',
        ];

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            $mappedSlug = $legacyRoleMap[$user->role] ?? 'registered_user';
            $roleId = $roleIds[$mappedSlug] ?? null;

            if ($roleId) {
                DB::table('role_user')->insert([
                    'role_id' => $roleId,
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
