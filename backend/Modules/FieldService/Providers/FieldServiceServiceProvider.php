<?php

namespace Modules\FieldService\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FieldServiceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'FieldService';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/field-service')
            ->middleware(['api', 'auth:sanctum', 'module:field_service', 'module.permission:field_service'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\FieldService\Console\CheckFieldServiceSlaBreaches::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\FieldService\Services\FieldServiceCostingService::class);
    }
}
