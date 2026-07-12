<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
        $admin->syncRoles([RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER]);

        $sales = User::firstOrCreate(
            ['email' => 'sales@webina.local'],
            [
                'name' => 'Sales Consultant',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $sales->syncRoles([RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT]);
    }
}
