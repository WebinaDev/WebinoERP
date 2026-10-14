<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Database\Seeders\SystemModulesSeeder;
use Modules\Core\Database\Seeders\SystemSettingsSeeder;
use Modules\Crm\Database\Seeders\CrmLookupSeeder;
use Modules\Marketing\Database\Seeders\MarketingFormsSeeder;
use Modules\Marketplace\Database\Seeders\DemoPaidModuleSeeder;
use Modules\Marketplace\Database\Seeders\SiteThemeSeeder;
use Modules\Platform\Database\Seeders\PlatformServiceTemplatesSeeder;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemModulesSeeder::class,
            SystemSettingsSeeder::class,
            CrmLookupSeeder::class,
            SiteBuilderSeeder::class,
            PlatformServiceTemplatesSeeder::class,
            SiteThemeSeeder::class,
            DemoPaidModuleSeeder::class,
            MarketingFormsSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
