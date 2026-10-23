<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Customer 360 phase 2/3: SMS log -> customer link, Basalam orders mirror, hub SMS/Basalam permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_messages') && ! Schema::hasColumn('crm_messages', 'crm_account_id')) {
            Schema::table('crm_messages', function (Blueprint $t) {
                $t->unsignedBigInteger('crm_account_id')->nullable()->index();
            });
        }

        if (! Schema::hasTable('crm_basalam_orders')) {
            Schema::create('crm_basalam_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('connection_id')->nullable()->index();
                $t->unsignedBigInteger('crm_account_id')->nullable()->index();
                $t->string('site_domain', 255)->nullable()->index();
                $t->string('external_id', 100);
                $t->string('status', 40)->nullable();
                $t->decimal('total', 18, 2)->default(0);
                $t->string('buyer_name', 191)->nullable();
                $t->string('buyer_mobile', 30)->nullable();
                $t->unsignedInteger('items_count')->default(0);
                $t->json('items')->nullable();
                $t->timestamp('ordered_at')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
                $t->unique(['site_domain', 'external_id']);
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $perms = [];
            foreach (['crm.hub.sms.send', 'crm.hub.automations.manage', 'crm.hub.import_export', 'crm.hub.bi.view'] as $name) {
                $perms[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
            if ($role = Role::query()->where('name', 'system_manager')->where('guard_name', 'web')->first()) {
                $role->givePermissionTo($perms);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_basalam_orders');
        if (Schema::hasTable('crm_messages') && Schema::hasColumn('crm_messages', 'crm_account_id')) {
            Schema::table('crm_messages', fn (Blueprint $t) => $t->dropColumn('crm_account_id'));
        }
    }
};
