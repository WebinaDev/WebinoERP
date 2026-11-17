<?php

namespace Modules\Assets\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AssetsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Assets';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/assets')
            ->middleware(['api', 'auth:sanctum', 'module:assets', 'module.permission:assets'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Assets\Console\CheckPreventiveMaintenanceDue::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Assets\Services\AssetDepreciationService::class);
    }
}
