<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Http\Controllers\WebinocrmLicenseCompatController;
use Modules\Accounting\Http\Controllers\WebinocrmModuleCloneUrlController;

class AccountingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Accounting';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/accounting')
            ->middleware(['api', 'auth:sanctum', 'module:accounting', 'module.permission:accounting'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        Route::prefix('api/webinocrm/v1')
            ->middleware('api')
            ->group(function () {
                Route::post('/license/check', [WebinocrmLicenseCompatController::class, 'check']);
                Route::post('/license/activate', [WebinocrmLicenseCompatController::class, 'activate']);
                Route::post('/license/module-clone-url', [WebinocrmModuleCloneUrlController::class, 'handle']);
            });

        Route::prefix('api/webinocrm/v1')
            ->middleware(['api', 'auth:sanctum'])
            ->group(module_path($this->moduleName, 'Routes/webinocrm-v1.php'));
    }
}
