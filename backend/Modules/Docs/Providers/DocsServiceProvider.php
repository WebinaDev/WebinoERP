<?php

namespace Modules\Docs\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DocsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Docs';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/docs')
            ->middleware(['api', 'auth:sanctum', 'module:docs', 'module.permission:docs'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
