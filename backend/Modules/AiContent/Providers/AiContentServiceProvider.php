<?php

namespace Modules\AiContent\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiContentServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'AiContent';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/ai-content')
            ->middleware(['api', 'auth:sanctum', 'module:ai_content', 'module.permission:ai_content,ai-content'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));
    }

    public function register(): void
    {
        //
    }
}
