<?php

namespace Modules\DeviceGateway\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeviceGatewayServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'DeviceGateway';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/device-gateway')
            ->middleware(['api', 'auth:sanctum', 'module:device_gateway', 'module.permission:device_gateway'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\DeviceGateway\Services\DeviceDispatcherService::class);
    }
}
