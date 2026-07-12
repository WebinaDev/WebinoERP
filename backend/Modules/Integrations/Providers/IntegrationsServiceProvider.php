<?php

namespace Modules\Integrations\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Integrations\Http\Controllers\WebinocrmBaleRestController;
use Modules\Integrations\Http\Controllers\WebinocrmGitWebhookController;
use Modules\Integrations\Http\Controllers\WebinocrmWebinoServerWebhookController;
use Modules\Integrations\Http\Controllers\WoobaleCompatController;
use Modules\Integrations\Services\Bale\BaleAutomationEngine;
use Modules\Integrations\Services\Bale\BaleSettingsStore;
use Modules\Integrations\Services\Bale\WoobaleSettingsStore;
use Modules\Integrations\Services\BaleBusinessService;
use Modules\Integrations\Services\BaleWebhookHandler;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakManager;

class IntegrationsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Integrations';

    public function register(): void
    {
        $this->app->singleton(BaleSettingsStore::class);
        $this->app->singleton(BaleAutomationEngine::class);
        $this->app->singleton(WoobaleSettingsStore::class);
        $this->app->singleton(BaleBusinessService::class);
        $this->app->singleton(BaleWebhookHandler::class);
        $this->app->singleton(ModirPayamakEdgeClient::class);
        $this->app->singleton(ModirPayamakManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        app(ModirPayamakManager::class)->seedDefaultPackages();

        Route::prefix('api/v1/integrations')
            ->middleware('api')
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        Route::prefix('api/webinocrm/v1/bale')
            ->middleware(['api', 'auth:sanctum', 'role:system_manager'])
            ->group(module_path($this->moduleName, 'Routes/webinocrm-bale.php'));

        Route::prefix('api/webinocrm/v1')
            ->middleware(['api', 'auth:sanctum', 'role:system_manager'])
            ->group(module_path($this->moduleName, 'Routes/webinocrm-hosting.php'));

        Route::prefix('api/webinocrm/v1')
            ->middleware('api')
            ->post('/git/webhook', [WebinocrmGitWebhookController::class, 'handle']);

        Route::prefix('api/webinocrm/v1')
            ->middleware('api')
            ->post('/hosting/webinoserver/webhook', [WebinocrmWebinoServerWebhookController::class, 'handle']);

        Route::prefix('api/webinocrm/v1')
            ->middleware('api')
            ->post('/bale/webhook', [WebinocrmBaleRestController::class, 'webhook']);

        Route::prefix('api/woobale/v1')
            ->middleware('api')
            ->group(function () {
                Route::post('/webhook', [WoobaleCompatController::class, 'webhook']);
                Route::get('/health', [WoobaleCompatController::class, 'health']);
            });
    }
}
