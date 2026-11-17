<?php

namespace Modules\Bi\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BiServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Bi';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/bi')
            ->middleware(['api', 'auth:sanctum', 'module:bi', 'module.permission:bi'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Bi\Console\DeliverScheduledBiReports::class,
                \Modules\Bi\Console\CheckBiThresholdAlerts::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Bi\Services\BiDataMartService::class);
    }
}
