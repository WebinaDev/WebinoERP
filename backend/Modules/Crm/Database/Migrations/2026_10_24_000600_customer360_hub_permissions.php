<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Customer 360 phase 1 permissions. Additive only: creates the permissions and
 * grants them to system_manager without re-syncing (and wiping) other roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $perms = [];
        foreach (['crm.hub.profile.sensitive', 'crm.hub.merge'] as $name) {
            $perms[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        if ($role = Role::query()->where('name', 'system_manager')->where('guard_name', 'web')->first()) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are kept on rollback.
    }
};
