<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Http\Controllers\DeployWebhookController;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\Platform\Services\SshExecutor;
use Modules\Platform\Services\WebinoDashboardProvisioner;

class PlatformServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Platform';

    public function register(): void
    {
        $this->app->singleton(SshExecutor::class);
        $this->app->singleton(DockerRemoteService::class);
        $this->app->singleton(WebinoDashboardProvisioner::class);
        $this->app->singleton(LocalSameVpsProvisioner::class);
        $this->app->singleton(\Modules\Platform\Services\PlatformAlertEvaluator::class);
        $this->app->singleton(\Modules\Platform\Services\PlatformServerMetricsCollector::class);
        $this->app->singleton(\Modules\Platform\Services\PlatformResourceUsageCollector::class);
        $this->app->singleton(\Modules\Platform\Services\PlatformServiceHealthCollector::class);
        $this->app->singleton(\Modules\Platform\Services\PlatformQuotaEnforcer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        Route::prefix('api/v1/platform')
            ->middleware(['api'])
            ->post('webhooks/deploy/{token}', [DeployWebhookController::class, 'handle']);

        Route::prefix('api/v1/platform')
            ->middleware(['api', 'auth:sanctum', 'module:platform', 'module.permission:platform'])
            ->group(module_path($this->moduleName, 'Routes/api.php'));

        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->job(new \Modules\Platform\Jobs\CollectServerMetricsJob())
                    ->everyMinute()
                    ->withoutOverlapping();
                $schedule->job(new \Modules\Platform\Jobs\CollectResourceUsageJob())
                    ->everyFiveMinutes()
                    ->withoutOverlapping();
                $schedule->job(new \Modules\Platform\Jobs\CollectServiceHealthJob())
                    ->everyMinute()
                    ->withoutOverlapping();
                $schedule->job(new \Modules\Platform\Jobs\RollupMetricsHourlyJob())
                    ->hourly()
                    ->withoutOverlapping();
                $schedule->job(new \Modules\Platform\Jobs\RollupMetricsDailyJob())
                    ->dailyAt('00:05')
                    ->withoutOverlapping();
                $schedule->job(new \Modules\Platform\Jobs\PurgeOldMetricsJob())
                    ->dailyAt('02:00')
                    ->withoutOverlapping();
            }
        });
    }
}
