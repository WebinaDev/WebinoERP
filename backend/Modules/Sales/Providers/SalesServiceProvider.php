<?php

namespace Modules\Sales\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Sales';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/sales')
            ->middleware(['api', 'auth:sanctum', 'module:sales', 'module.permission:sales'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
