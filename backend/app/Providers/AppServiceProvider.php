<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmLead;
use Modules\Core\Entities\CoreChatChannel;
use Modules\Core\Observers\ActivityObserver;
use Modules\Core\Policies\ChatChannelPolicy;
use Modules\Crm\Policies\CrmAccountPolicy;
use Modules\Crm\Policies\LeadPolicy;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Policies\HrmEmployeePolicy;
use Modules\Projects\Policies\ProjectPolicy;
use Modules\Projects\Policies\ProjectTaskPolicy;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/integrations.php'), 'integrations');
        $this->mergeConfigFrom(base_path('config/module_permissions.php'), 'module_permissions');

        Scramble::ignoreDefaultRoutes();

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }

        // Spatie backup vendor config references ZipArchive::CM_* at merge time.
        // Without ext-zip that fatals during bootstrap and every route (incl. license/check) 500s.
        // Package is in dont-discover; register only when the extension is present.
        if (class_exists(\ZipArchive::class) && class_exists(\Spatie\Backup\BackupServiceProvider::class)) {
            $this->app->register(\Spatie\Backup\BackupServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Site provision builds can run ~40 minutes. The framework default
        // redis retry_after (90s) releases the job while the first worker is
        // still cloning/building, so the site sits queued or starts twice.
        $retryAfter = (int) config('queue.connections.redis.retry_after', 90);
        if ($retryAfter < 2500) {
            config(['queue.connections.redis.retry_after' => 2500]);
        }

        RateLimiter::for('auth-public', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $mobile = (string) $request->input('mobile', $request->input('email', ''));

            return Limit::perMinute(3)->by('otp-send:'.($mobile !== '' ? $mobile : $request->ip()));
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $mobile = (string) $request->input('mobile', $request->input('email', ''));

            return Limit::perMinute(5)->by('otp-verify:'.($mobile !== '' ? $mobile : '').'|'.$request->ip());
        });

        User::observe(UserObserver::class);

        if (env('SENTRY_LARAVEL_DSN') && class_exists(\Sentry\SentrySdk::class)) {
            \Sentry\init(['dsn' => env('SENTRY_LARAVEL_DSN'), 'environment' => config('app.env')]);
        }

        Gate::policy(CrmLead::class, LeadPolicy::class);
        Gate::policy(CrmAccount::class, CrmAccountPolicy::class);
        Gate::policy(HrmEmployee::class, HrmEmployeePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectTask::class, ProjectTaskPolicy::class);
        Gate::policy(CoreChatChannel::class, ChatChannelPolicy::class);

        Gate::define('accounting.view', fn ($user) => $user->can('accounting.view'));
        Gate::define('accounting.manage', fn ($user) => $user->can('accounting.manage'));
        Gate::define('modirpayamak.view', fn ($user) => $user->can('integrations.modirpayamak.view'));
        Gate::define('modirpayamak.manage', fn ($user) => $user->can('integrations.modirpayamak.manage'));
        Gate::define('modirpayamak.admin', fn ($user) => $user->hasRole('system_manager') || $user->can('integrations.modirpayamak.manage'));

        $observer = app(ActivityObserver::class);
        CrmLead::observe($observer);
        CrmAccount::observe($observer);
        Project::observe($observer);
        ProjectTask::observe($observer);
        Contract::observe($observer);

        Scramble::configure()
            ->routes(fn (Route $route) => Str::startsWith($route->uri(), 'api/v1/'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                );
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                if (preg_match('#api/v1/([^/]+)#', $routeInfo->route->uri(), $matches)) {
                    $operation->setTags([strtoupper($matches[1])]);
                }
            });
    }
}
