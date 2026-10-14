<?php

namespace Modules\Sales\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Sales\Http\Controllers\RahnPublicController;

class SalesServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Sales';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/sales')
            ->middleware(['api', 'auth:sanctum', 'module:sales', 'module.permission:sales'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        // Public رهن‌درصد share API — no auth (Marketplace Basalam OAuth callback pattern).
        // Guarantees unauthenticated access even if withoutMiddleware on the group file is ignored.
        Route::middleware(['api', 'throttle:60,1'])->prefix('api/v1/sales/rahn/public')->group(function () {
            Route::get('{token}', [RahnPublicController::class, 'show'])->where('token', '[a-zA-Z0-9]+');
            Route::post('{token}/calculate', [RahnPublicController::class, 'calculate'])->where('token', '[a-zA-Z0-9]+');
            Route::post('{token}/submit', [RahnPublicController::class, 'submit'])->where('token', '[a-zA-Z0-9]+');
        });

        // Token-free website calculator (/pricing) — same auth carve-out as the share links.
        Route::middleware(['api', 'throttle:60,1'])->prefix('api/v1/sales/rahn/site')->group(function () {
            Route::get('config', [RahnPublicController::class, 'siteConfig']);
            Route::post('calculate', [RahnPublicController::class, 'siteCalculate']);
            Route::post('submit', [RahnPublicController::class, 'siteSubmit'])->middleware('throttle:6,1');
        });
    }

    public function register(): void
    {
        //
    }
}
