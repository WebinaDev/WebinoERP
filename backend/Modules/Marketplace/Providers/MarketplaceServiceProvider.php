<?php

namespace Modules\Marketplace\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplace\Http\Controllers\BasalamOAuthController;
use Modules\Marketplace\Http\Controllers\WebinocrmMarketplaceCompatController;

class MarketplaceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketplace';

    public function boot(): void
    {

        Route::prefix('api/webinocrm/v1/marketplace')
            ->middleware('api')
            ->group(function () {
                Route::match(['get', 'post'], '/catalog', [WebinocrmMarketplaceCompatController::class, 'catalog']);
                Route::post('/purchase', [WebinocrmMarketplaceCompatController::class, 'purchase']);
                Route::match(['get', 'post'], '/payment-callback', [WebinocrmMarketplaceCompatController::class, 'paymentCallback']);
                // Thin core-update probe for Dashboard (git/core product via include_core catalog).
                Route::match(['get', 'post'], '/core/check', [WebinocrmMarketplaceCompatController::class, 'coreCheck']);
            });

        // Dashboard Basalam OAuth Edge aliases (no Sanctum — tenant site traffic).
        Route::prefix('api/webinocrm/v1/basalam/oauth')
            ->middleware(['api', 'throttle:60,1'])
            ->group(function () {
                Route::post('/start', [BasalamOAuthController::class, 'start']);
                Route::post('/refresh', [BasalamOAuthController::class, 'refresh']);
                Route::post('/disconnect', [BasalamOAuthController::class, 'disconnect']);
            });

        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/marketplace')
            ->middleware(['api', 'auth:sanctum', 'module:marketplace', 'module.permission:marketplace'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        // Public OAuth callback — ERP redirect_uri (no auth).
        Route::middleware(['api', 'throttle:60,1'])
            ->get('/api/basalam/oauth/callback', [BasalamOAuthController::class, 'callback']);
        Route::middleware(['api', 'throttle:60,1'])
            ->get('/api/v1/marketplace/basalam/oauth/callback', [BasalamOAuthController::class, 'callback']);
    }

    public function register(): void
    {
        //
    }
}
