<?php

namespace App\Providers;

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
    }

    public function boot(): void
    {
        RateLimiter::for('auth-public', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

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
