<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Http\Controllers\DeployWebhookController;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\SshExecutor;
use Modules\Platform\Services\WebinoDashboardProvisioner;

class PlatformServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Platform';

    public function register(): void
    {
        $this->app->singleton(SshExecutor::class);
        $this->app->singleton(DockerRemoteService::class);
        $this->app->singleton(WebinoDashboardProvisioner::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/platform')
            ->middleware(['api'])
            ->post('webhooks/deploy/{token}', [DeployWebhookController::class, 'handle']);

        Route::prefix('api/v1/platform')
            ->middleware(['api', 'auth:sanctum', 'module:platform', 'module.permission:platform'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }
}
