<?php

namespace Modules\Projects\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Projects\Http\Controllers\FormController;

class ProjectsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Projects';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/forms')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('{slug}/submit', [FormController::class, 'submit'])
                    ->where('slug', '[a-z0-9\-]+');
            });

        Route::prefix('api/v1/projects')
            ->middleware(['api', 'auth:sanctum', 'module:projects', 'module.permission:projects'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }
}
