<?php

namespace Modules\SiteBuilder\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoDashboardFeature;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class SiteBuilderServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'SiteBuilder';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // Scoped names only — do not bind generic {category}/{type}/{package} globally.
        Route::bind('siteProvision', fn (string $value) => WebinoSiteProvision::query()->findOrFail($value));
        Route::bind('siteCategory', fn (string $value) => WebinoBusinessCategory::query()->findOrFail($value));
        Route::bind('siteType', fn (string $value) => WebinoBusinessType::query()->findOrFail($value));
        Route::bind('siteFeature', fn (string $value) => WebinoDashboardFeature::query()->findOrFail($value));
        Route::bind('sitePackage', fn (string $value) => WebinoPackage::query()->findOrFail($value));

        Route::prefix('api/v1/site-builder')
            ->middleware(['api', 'auth:sanctum', 'module:platform', 'module.permission:platform,site-builder', 'throttle:60,1'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->commands([
            \Modules\SiteBuilder\Console\EnsureHostingDefaultsCommand::class,
            \Modules\SiteBuilder\Console\ResyncCaddySnippetsCommand::class,
            \Modules\SiteBuilder\Console\ResyncTenantStacksCommand::class,
        ]);
    }
}
