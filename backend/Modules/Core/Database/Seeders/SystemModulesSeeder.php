<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Entities\SystemModule;

class SystemModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'is_active' => true],
            ['name' => 'HRM', 'slug' => 'hrm', 'is_active' => true],
            ['name' => 'Projects', 'slug' => 'projects', 'is_active' => true],
            ['name' => 'CRM', 'slug' => 'crm', 'is_active' => true],
            ['name' => 'Accounting', 'slug' => 'accounting', 'is_active' => true],
            ['name' => 'Sales', 'slug' => 'sales', 'is_active' => true],
            ['name' => 'SCM', 'slug' => 'scm', 'is_active' => true],
            ['name' => 'Docs', 'slug' => 'docs', 'is_active' => true],
            ['name' => 'Marketplace', 'slug' => 'marketplace', 'is_active' => true],
            ['name' => 'Integrations', 'slug' => 'integrations', 'is_active' => true],
            ['name' => 'Warehouse', 'slug' => 'warehouse', 'is_active' => true],
            ['name' => 'MFG', 'slug' => 'mfg', 'is_active' => true],
            ['name' => 'Site Builder', 'slug' => 'site_builder', 'is_active' => true],
            ['name' => 'Marketing', 'slug' => 'marketing', 'is_active' => true],
        ];

        foreach ($modules as $module) {
            SystemModule::firstOrCreate(['slug' => $module['slug']], $module);
        }
    }
}
