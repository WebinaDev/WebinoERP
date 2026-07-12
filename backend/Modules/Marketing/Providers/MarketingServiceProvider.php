<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketing\Console\ImportWordPressCommand;

class MarketingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketing';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportWordPressCommand::class]);
        }

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/public')
            ->middleware(['api', 'throttle:120,1'])
            ->group(module_path($this->moduleName, 'Routes/public.php'));

        Route::prefix('api/v1/marketing')
            ->middleware(['api', 'auth:sanctum', 'module:marketing', 'module.permission:marketing', 'throttle:60,1'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
