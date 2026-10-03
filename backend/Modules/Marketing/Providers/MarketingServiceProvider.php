<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketing\Console\ImportWordPressCommand;
use Modules\Marketing\Http\Controllers\Builder\BuilderController;
use Modules\Marketing\Http\Controllers\Builder\BuilderGlobalsController;
use Modules\Marketing\Http\Controllers\Builder\ThemeBuilderController;

class MarketingServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketing';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportWordPressCommand::class]);
        }

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/public')
            ->middleware(['api', 'throttle:120,1'])
            ->group(module_path($this->moduleName, 'Routes/public.php'));

        Route::prefix('api/v1/marketing')
            ->middleware(['api', 'auth:sanctum', 'module:marketing', 'module.permission:marketing', 'throttle:60,1'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        // Same paths and JSON shapes as WebinoDashboard (`/api/v1/builder`, `/api/v1/theme-builder`).
        Route::prefix('api/v1')
            ->middleware(['api', 'auth:sanctum', 'module:marketing', 'module.permission:marketing', 'throttle:60,1'])
            ->group(function () {
                Route::get('/builder', [BuilderController::class, 'index']);
                Route::post('/builder/pages', [BuilderController::class, 'store']);
                Route::get('/builder/pages/{page}', [BuilderController::class, 'show'])->whereNumber('page');
                Route::patch('/builder/pages/{page}', [BuilderController::class, 'update'])->whereNumber('page');
                Route::post('/builder/pages/{page}/publish', [BuilderController::class, 'publish'])->whereNumber('page');
                Route::delete('/builder/pages/{page}', [BuilderController::class, 'destroy'])->whereNumber('page');
                Route::get('/builder/templates/{kind}', [BuilderController::class, 'showTemplate']);
                Route::put('/builder/templates/{kind}', [BuilderController::class, 'saveTemplate']);
                Route::post('/builder/templates/{kind}/publish', [BuilderController::class, 'publishTemplate']);
                Route::get('/builder/globals', [BuilderGlobalsController::class, 'show']);
                Route::put('/builder/globals', [BuilderGlobalsController::class, 'update']);
                Route::post('/builder/globals/publish', [BuilderGlobalsController::class, 'publish']);
                Route::get('/theme-builder', [ThemeBuilderController::class, 'index']);
                Route::get('/theme-builder/library', [ThemeBuilderController::class, 'library']);
                Route::post('/theme-builder/library/apply', [ThemeBuilderController::class, 'apply']);
                Route::post('/theme-builder/templates', [ThemeBuilderController::class, 'store']);
                Route::get('/theme-builder/templates/{template}', [ThemeBuilderController::class, 'show'])->whereNumber('template');
                Route::patch('/theme-builder/templates/{template}', [ThemeBuilderController::class, 'update'])->whereNumber('template');
                Route::post('/theme-builder/templates/{template}/publish', [ThemeBuilderController::class, 'publish'])->whereNumber('template');
                Route::post('/theme-builder/templates/{template}/default', [ThemeBuilderController::class, 'makeDefault'])->whereNumber('template');
                Route::delete('/theme-builder/templates/{template}', [ThemeBuilderController::class, 'destroy'])->whereNumber('template');
            });
    }

    public function register(): void
    {
        //
    }
}
