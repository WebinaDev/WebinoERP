<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Modules\Authz\Entities\AuthzMembership;
use Modules\Authz\Entities\AuthzRole;
use Modules\Authz\Entities\AuthzUserRole;
use Modules\Crm\Entities\CrmCompany;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@webina.local'],
            [
                'name' => 'System Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        if ($admin->wasRecentlyCreated) {
            $admin->assignRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        }

        $sales = User::firstOrCreate(
            ['email' => 'sales@webina.local'],
            [
                'name' => 'Sales Consultant',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        if ($sales->wasRecentlyCreated) {
            $sales->assignRole(RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT);
        }

        $company = CrmCompany::query()->where('is_default', true)->first() ?? CrmCompany::query()->first();
        if ($company && DB::getSchemaBuilder()->hasTable('authz_memberships')) {
            foreach ([[$admin, 'super_admin'], [$sales, 'sales_agent']] as [$user, $roleCode]) {
                if (! $user->wasRecentlyCreated) continue;
                DB::table('crm_company_users')->insertOrIgnore([
                    'user_id' => $user->id, 'company_id' => $company->id, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                AuthzMembership::query()->firstOrCreate(
                    ['user_id' => $user->id, 'company_id' => $company->id, 'branch_id' => null],
                    ['status' => 'active', 'is_default_context' => true]
                );
                $role = AuthzRole::query()->where('code', $roleCode)->whereNull('company_id')->first();
                if ($role) AuthzUserRole::query()->firstOrCreate(
                    ['user_id' => $user->id, 'company_id' => $company->id, 'role_id' => $role->id],
                    ['scope_json' => null]
                );
            }
        }
    }
}
