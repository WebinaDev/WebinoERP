<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\CheckModuleLicense;

return Application::configure(basePath: dirname(__DIR__))
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth:sanctum']],
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('webino:reminders')->dailyAt('09:00');
        $schedule->command('webino:reminders:check')->everyMinute();
        $schedule->command('webino:visitor:aggregate --days=2')->dailyAt('00:20');
        $schedule->command('webino:sessions:cleanup --days=30')->dailyAt('02:20');
        $schedule->command('webino:tasks:recurring')->everyFifteenMinutes();
        $schedule->command('webino:db-optimize')->weeklyOn(0, '03:15');
        $schedule->command('crm:recompute-lead-scores')->dailyAt('04:00');
        if (class_exists(\Spatie\Backup\Commands\BackupCommand::class)) {
            $schedule->command('backup:run --only-db')->dailyAt('02:40');
        }
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'module' => CheckModuleLicense::class,
            'module.permission' => \Modules\Core\Http\Middleware\EnforceModulePermission::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'fieldsec' => \Modules\Core\Http\Middleware\ApplyFieldPermissions::class,
            'require.2fa' => \App\Http\Middleware\RequireTwoFactor::class,
            'user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        // Caddy (and any reverse proxy) sits in front — trust X-Forwarded-* so
        // $request->ip() / isSecure() / URL generation are correct.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );
        
        $middleware->encryptCookies(except: [
            env('AUTH_COOKIE_NAME', 'webino_auth_token'),
        ]);

        // Cookie+Bearer SPA — not Sanctum session auth. CSRF on /api/* blocks login
        // when APP_URL host is treated as a stateful domain.
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'sanctum/csrf-cookie',
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\ApiResponseFormatter::class,
            \App\Http\Middleware\AuthenticateFromCookie::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\UpdateTokenLastActivity::class,
            \App\Http\Middleware\RequireAjaxHeader::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\RequireTwoFactor::class,
            \App\Http\Middleware\ThrottleApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
