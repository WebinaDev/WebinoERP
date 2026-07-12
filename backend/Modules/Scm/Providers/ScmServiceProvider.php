<?php

namespace Modules\Scm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ScmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Scm';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/scm')
            ->middleware(['api', 'auth:sanctum', 'module:scm', 'module.permission:scm'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
