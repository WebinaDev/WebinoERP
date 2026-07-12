<?php

namespace Modules\Mfg\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MfgServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Mfg';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/mfg')
            ->middleware(['api', 'auth:sanctum', 'module:mfg', 'module.permission:mfg'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
