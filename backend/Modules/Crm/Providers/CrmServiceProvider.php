<?php

namespace Modules\Crm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Crm\Console\DispatchRemindersCommand;
use Modules\Crm\Console\RecomputeLeadScoresCommand;
use Modules\Crm\Console\RunSequencesCommand;
use Modules\Crm\Console\DispatchContentRemindersCommand;
use Modules\Crm\Http\Controllers\ConsultationIngestController;
use Modules\Crm\Http\Controllers\ElementorLeadController;
use Modules\Crm\Http\Controllers\PublicLeadFormController;

class CrmServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Crm';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecomputeLeadScoresCommand::class,
                DispatchRemindersCommand::class,
                RunSequencesCommand::class,
                DispatchContentRemindersCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/crm')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/leads/elementor', [ElementorLeadController::class, 'store']);
                Route::post('/public/forms/{slug}', [PublicLeadFormController::class, 'store'])
                    ->where('slug', '[a-z0-9\-]+');
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
