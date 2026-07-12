<?php

namespace Modules\Crm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Crm\Console\RecomputeLeadScoresCommand;
use Modules\Crm\Http\Controllers\ConsultationIngestController;
use Modules\Crm\Http\Controllers\ElementorLeadController;

class CrmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Crm';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RecomputeLeadScoresCommand::class]);
        }

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/crm')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/leads/elementor', [ElementorLeadController::class, 'store']);
            });

        Route::prefix('api/webinocrm/v1')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/consultations/ingest', [ConsultationIngestController::class, 'store']);
            });

        Route::prefix('api/v1/crm')
            ->middleware(['api', 'auth:sanctum', 'module:crm', 'module.permission:crm'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
