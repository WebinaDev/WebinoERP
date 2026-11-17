<?php

namespace Modules\Procurement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProcurementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Procurement';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/procurement')
            ->middleware(['api', 'auth:sanctum', 'module:procurement', 'module.permission:procurement'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Procurement\Services\ProcurementMatchingService::class);
    }
}
