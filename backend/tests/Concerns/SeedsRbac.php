<?php

namespace Tests\Concerns;

use App\Models\User;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;

trait SeedsRbac
{
    protected function seedRbac(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function seedLicensedModules(): void
    {
        foreach (['crm', 'hrm', 'accounting', 'projects', 'scm', 'sales', 'docs', 'marketplace', 'integrations', 'mfg'] as $slug) {
            SystemModule::firstOrCreate(
                ['slug' => $slug],
                ['name' => strtoupper($slug), 'is_active' => true]
            );
        }
    }

    protected function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
