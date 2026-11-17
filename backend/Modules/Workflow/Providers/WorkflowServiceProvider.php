<?php

namespace Modules\Workflow\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Workflow';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/workflow')
            ->middleware(['api', 'auth:sanctum', 'module:workflow', 'module.permission:workflow'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Workflow\Console\CheckWorkflowSlaReminders::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(\Modules\Workflow\Services\CentralApprovalEngine::class);
    }
}
