<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Database\Seeders\SystemModulesSeeder;
use Modules\Core\Database\Seeders\SystemSettingsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemModulesSeeder::class,
            SystemSettingsSeeder::class,
            \Modules\Crm\Database\Seeders\CrmLookupSeeder::class,
            \Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder::class,
            \Modules\Marketplace\Database\Seeders\SiteThemeSeeder::class,
            \Modules\Marketing\Database\Seeders\MarketingSiteSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
