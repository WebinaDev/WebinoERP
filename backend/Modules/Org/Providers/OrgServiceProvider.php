<?php

namespace Modules\Org\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrgServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Org';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/org')
            ->middleware(['api', 'auth:sanctum', 'module:org', 'module.permission:org'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Org\Services\OrgContextService::class);
        $this->app->singleton(\Modules\Org\Services\DocumentNumberingService::class);
    }
}
