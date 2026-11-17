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
            ['name' => 'Marketing', 'slug' => 'marketing', 'is_active' => true],
            ['name' => 'Platform', 'slug' => 'platform', 'is_active' => true],
            ['name' => 'AI Content', 'slug' => 'ai_content', 'is_active' => true],
            ['name' => 'Org', 'slug' => 'org', 'is_active' => true],
            ['name' => 'Workflow', 'slug' => 'workflow', 'is_active' => true],
            ['name' => 'BI', 'slug' => 'bi', 'is_active' => true],
            ['name' => 'Field Service', 'slug' => 'field_service', 'is_active' => true],
            ['name' => 'QM', 'slug' => 'qm', 'is_active' => true],
            ['name' => 'Assets', 'slug' => 'assets', 'is_active' => true],
            ['name' => 'Authz', 'slug' => 'authz', 'is_active' => true],
            ['name' => 'Procurement', 'slug' => 'procurement', 'is_active' => true],
            ['name' => 'Device Gateway', 'slug' => 'device_gateway', 'is_active' => true],
        ];

        foreach ($modules as $module) {
            SystemModule::firstOrCreate(['slug' => $module['slug']], $module);
        }
    }
}
