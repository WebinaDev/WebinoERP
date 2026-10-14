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

        \Modules\Marketing\Services\Builder\BuilderRevisions::register();
        $this->registerBuilderConsole();

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
                Route::get('/builder/products/{product}', [BuilderController::class, 'showProduct'])->whereNumber('product');
                Route::patch('/builder/products/{product}', [BuilderController::class, 'updateProduct'])->whereNumber('product');
                Route::post('/builder/products/{product}/publish', [BuilderController::class, 'publishProduct'])->whereNumber('product');
                Route::get('/builder/posts/{post}', [BuilderController::class, 'showPost'])->whereNumber('post');
                Route::patch('/builder/posts/{post}', [BuilderController::class, 'updatePost'])->whereNumber('post');
                Route::post('/builder/posts/{post}/publish', [BuilderController::class, 'publishPost'])->whereNumber('post');
                Route::get('/builder/articles/{article}', [BuilderController::class, 'showArticle'])->whereNumber('article');
                Route::patch('/builder/articles/{article}', [BuilderController::class, 'updateArticle'])->whereNumber('article');
                Route::post('/builder/articles/{article}/publish', [BuilderController::class, 'publishArticle'])->whereNumber('article');
                Route::get('/builder/templates/{kind}', [BuilderController::class, 'showTemplate']);
                Route::put('/builder/templates/{kind}', [BuilderController::class, 'saveTemplate']);
                Route::post('/builder/templates/{kind}/publish', [BuilderController::class, 'publishTemplate']);
                Route::get('/builder/revisions', [\Modules\Marketing\Http\Controllers\Builder\BuilderRevisionController::class, 'index']);
                Route::get('/builder/revisions/{revision}', [\Modules\Marketing\Http\Controllers\Builder\BuilderRevisionController::class, 'show'])->whereNumber('revision');
                Route::post('/builder/revisions/{revision}/restore', [\Modules\Marketing\Http\Controllers\Builder\BuilderRevisionController::class, 'restore'])->whereNumber('revision');
                Route::get('/builder/forms/{key}', [\Modules\Marketing\Http\Controllers\Builder\BuilderFormSettingsController::class, 'show'])->where('key', '[\w.\-]{1,100}');
                Route::put('/builder/forms/{key}', [\Modules\Marketing\Http\Controllers\Builder\BuilderFormSettingsController::class, 'update'])->where('key', '[\w.\-]{1,100}');
                Route::get('/builder/library', [\Modules\Marketing\Http\Controllers\Builder\BuilderLibraryController::class, 'index']);
                Route::post('/builder/library', [\Modules\Marketing\Http\Controllers\Builder\BuilderLibraryController::class, 'store']);
                Route::get('/builder/library/{libraryItem}', [\Modules\Marketing\Http\Controllers\Builder\BuilderLibraryController::class, 'show'])->whereNumber('libraryItem');
                Route::delete('/builder/library/{libraryItem}', [\Modules\Marketing\Http\Controllers\Builder\BuilderLibraryController::class, 'destroy'])->whereNumber('libraryItem');
                Route::get('/builder/pages/{page}/settings', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'show'])->whereNumber('page');
                Route::put('/builder/pages/{page}/settings', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'update'])->whereNumber('page');
                Route::put('/builder/pages/{page}/schedule', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'schedule'])->whereNumber('page');
                Route::post('/builder/pages/{page}/unpublish', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'unpublish'])->whereNumber('page');
                Route::post('/builder/pages/{page}/discard', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'discard'])->whereNumber('page');
                Route::post('/builder/pages/{page}/preview-token', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'previewToken'])->whereNumber('page')->middleware('throttle:20,1');
                Route::post('/builder/pages/{page}/lock', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'lock'])->whereNumber('page');
                Route::delete('/builder/pages/{page}/lock', [\Modules\Marketing\Http\Controllers\Builder\BuilderPageSettingsController::class, 'unlock'])->whereNumber('page');
                Route::get('/builder/pages/{page}/ab', [\Modules\Marketing\Http\Controllers\Builder\BuilderAbController::class, 'show'])->whereNumber('page');
                Route::put('/builder/pages/{page}/ab', [\Modules\Marketing\Http\Controllers\Builder\BuilderAbController::class, 'update'])->whereNumber('page');
                Route::delete('/builder/pages/{page}/ab', [\Modules\Marketing\Http\Controllers\Builder\BuilderAbController::class, 'destroy'])->whereNumber('page');
                Route::get('/builder/forms/{key}/submissions', [\Modules\Marketing\Http\Controllers\Builder\BuilderFormFilesController::class, 'submissions'])->where('key', '[\w.\-]{1,100}');
                Route::get('/builder/forms/files/{file}', [\Modules\Marketing\Http\Controllers\Builder\BuilderFormFilesController::class, 'download'])->whereNumber('file');
                Route::get('/builder/collections', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'index']);
                Route::post('/builder/collections', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'store']);
                Route::put('/builder/collections/{collection}', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'update'])->whereNumber('collection');
                Route::delete('/builder/collections/{collection}', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'destroy'])->whereNumber('collection');
                Route::get('/builder/collections/{collection}/items', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'items'])->whereNumber('collection');
                Route::post('/builder/collections/{collection}/items', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'storeItem'])->whereNumber('collection');
                Route::put('/builder/collections/{collection}/items/{collectionItem}', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'updateItem'])->whereNumber('collection')->whereNumber('collectionItem');
                Route::delete('/builder/collections/{collection}/items/{collectionItem}', [\Modules\Marketing\Http\Controllers\Builder\BuilderCollectionsController::class, 'destroyItem'])->whereNumber('collection')->whereNumber('collectionItem');
                Route::get('/builder/kit', [\Modules\Marketing\Http\Controllers\Builder\BuilderKitController::class, 'export']);
                Route::post('/builder/kit', [\Modules\Marketing\Http\Controllers\Builder\BuilderKitController::class, 'import'])->middleware('throttle:10,1');
                Route::get('/builder/roles', [\Modules\Marketing\Http\Controllers\Builder\BuilderRolesController::class, 'show']);
                Route::put('/builder/roles', [\Modules\Marketing\Http\Controllers\Builder\BuilderRolesController::class, 'update']);
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
            });
    }

    public function register(): void
    {
        //
    }

    private function registerBuilderConsole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
        \Illuminate\Support\Facades\Artisan::command('builder:scheduled', function () {
            [$published, $unpublished] = app(\Modules\Marketing\Services\Builder\BuilderPageLifecycle::class)->runDue();
            $this->info("Builder pages published: {$published}, unpublished: {$unpublished}");
        })->purpose('Publish/unpublish builder pages whose schedule is due');
        \Illuminate\Support\Facades\Artisan::command('builder:purge-form-uploads {--hours=24}', function () {
            $removed = \Modules\Marketing\Http\Controllers\Builder\BuilderFormFilesController::purgeOrphans(max(1, (int) $this->option('hours')));
            $this->info("Removed {$removed} unattached form uploads");
        })->purpose('Delete builder form uploads that were never attached to a submission');
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $schedule->command('builder:scheduled')->everyMinute()->withoutOverlapping();
            $schedule->command('builder:purge-form-uploads')->hourly()->withoutOverlapping();
        });
    }
}
