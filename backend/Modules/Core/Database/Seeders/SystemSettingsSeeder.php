<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Entities\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Branding settings
            ['key' => 'brand_name', 'value' => config('app.name'), 'group' => 'branding'],
            ['key' => 'brand_logo_url', 'value' => null, 'group' => 'branding'],
            ['key' => 'primary_color', 'value' => '#0f172a', 'group' => 'branding'],
            ['key' => 'border_radius', 'value' => '0.5rem', 'group' => 'branding'],
            
            // System settings
            ['key' => 'setup_completed', 'value' => 'false', 'group' => 'system'],
            ['key' => 'module_dashboard_enabled', 'value' => '1', 'group' => 'modules'],
            ['key' => 'module_projects_enabled', 'value' => '1', 'group' => 'modules'],
            ['key' => 'module_crm_enabled', 'value' => '1', 'group' => 'modules'],
            ['key' => 'module_accounting_enabled', 'value' => '1', 'group' => 'modules'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}

