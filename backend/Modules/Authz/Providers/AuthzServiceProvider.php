<?php

namespace Modules\Authz\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthzServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Authz';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/authz')
            ->middleware(['api', 'auth:sanctum'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Authz\Services\AuthorizationEngine::class);
    }
}
