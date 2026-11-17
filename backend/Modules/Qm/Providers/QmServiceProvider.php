<?php

namespace Modules\Qm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class QmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Qm';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/qm')
            ->middleware(['api', 'auth:sanctum', 'module:qm', 'module.permission:qm'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Qm\Services\QualityGateService::class);
    }
}
