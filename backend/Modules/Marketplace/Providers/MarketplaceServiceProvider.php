<?php

namespace Modules\Marketplace\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplace\Http\Controllers\BasalamOAuthController;

class MarketplaceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketplace';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/marketplace')
            ->middleware(['api', 'auth:sanctum', 'module:marketplace', 'module.permission:marketplace'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        // Public OAuth callback — CRM redirect_uri parity (no auth).
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
