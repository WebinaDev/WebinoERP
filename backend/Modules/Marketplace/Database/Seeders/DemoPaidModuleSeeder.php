<?php

namespace Modules\Marketplace\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceModuleRepo;

class DemoPaidModuleSeeder extends Seeder
{
    public function run(): void
    {
        $source = ModuleGitSource::query()->updateOrCreate(
            ['slug' => 'demo_paid'],
            [
                'clone_url' => 'https://github.com/WebinaDev/webino-module-demo-paid.git',
                'auth_type' => 'pat',
                'credential_ref' => 'org-git',
            ]
        );

        $module = MarketplaceModule::query()->updateOrCreate(
            ['slug' => 'demo_paid'],
            [
                'name' => 'Demo Paid Module',
                'distribution' => 'git',
                'description' => 'Sample paid module installed from organization git with license gating.',
                'status' => 'published',
                'price' => 990000,
                'requires_license' => true,
                'module_git_source_id' => $source->id,
            ]
        );

        MarketplaceModuleRepo::query()->updateOrCreate(
            ['module_id' => $module->id],
            [
                'repo_url' => $source->clone_url,
                'repo_branch' => 'main',
                'provider' => 'github',
                'gitea_repo' => 'WebinaDev/webino-module-demo-paid',
                'default_branch' => 'main',
            ]
        );
    }
}
