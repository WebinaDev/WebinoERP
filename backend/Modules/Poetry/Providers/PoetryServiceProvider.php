<?php

namespace Modules\Poetry\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PoetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Poetry', 'Database/Migrations'));

        Route::prefix('api/v1/public/poetry')
            ->middleware(['api', 'throttle:120,1'])
            ->group(module_path('Poetry', 'Routes/public.php'));

        // Admin reuses the Marketing licence + blog permissions (poems are blog content).
        Route::prefix('api/v1/poetry')
            ->middleware(['api', 'auth:sanctum', 'module:marketing', 'module.permission:marketing,blog', 'throttle:60,1'])
            ->group(module_path('Poetry', 'Routes/api.php'));
    }
}
