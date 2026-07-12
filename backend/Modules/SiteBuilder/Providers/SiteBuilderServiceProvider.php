<?php

namespace Modules\SiteBuilder\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SiteBuilderServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'SiteBuilder';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        \Illuminate\Support\Facades\Route::bind('siteProvision', function (string $value) {
            return \Modules\SiteBuilder\Entities\WebinoSiteProvision::query()->findOrFail($value);
        });

        \Illuminate\Support\Facades\Route::bind('category', function (string $value) {
            return \Modules\SiteBuilder\Entities\WebinoBusinessCategory::query()->findOrFail($value);
        });

        \Illuminate\Support\Facades\Route::bind('type', function (string $value) {
            return \Modules\SiteBuilder\Entities\WebinoBusinessType::query()->findOrFail($value);
        });

        \Illuminate\Support\Facades\Route::bind('feature', function (string $value) {
            return \Modules\SiteBuilder\Entities\WebinoDashboardFeature::query()->findOrFail($value);
        });

        \Illuminate\Support\Facades\Route::bind('package', function (string $value) {
            return \Modules\SiteBuilder\Entities\WebinoPackage::query()->findOrFail($value);
        });

        Route::prefix('api/v1/site-builder')
            ->middleware(['api', 'auth:sanctum', 'module:site_builder', 'module.permission:site_builder', 'throttle:60,1'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
