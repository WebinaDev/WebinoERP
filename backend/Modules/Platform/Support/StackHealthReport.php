<?php

namespace Modules\Platform\Support;

/**
 * Control-panel stack health: distinguish "not run", "skipped (budget)", and real failures.
 */
final class StackHealthReport
{
    /** @var list<string> */
    public const CHECKS = [
        'db_auth',
        'backend_self',
        'readiness',
        'redis',
        'caddy_to_backend',
        'frontend_to_backend',
        'on_webino_sites_backend',
        'on_webino_sites_frontend',
    ];

    /**
     * @return array<string, string>
     */
    public static function checks(string $state = 'not_run'): array
    {
        return array_fill_keys(self::CHECKS, $state);
    }

    /**
     * Diagnostics were not executed (light panel, or the call never started).
     *
     * Boolean probe flags stay null so clients do not treat them as failures.
     *
     * @return array<string, mixed>
     */
    public static function notRun(?string $log = null): array
    {
        return [
            'project' => null,
            'containers' => [],
            'on_webino_sites' => ['backend' => false, 'frontend' => false],
            'caddy_to_backend' => false,
            'frontend_to_backend' => false,
            'db_auth_ok' => null,
            'backend_self' => null,
            'readiness_ok' => null,
            'redis_ok' => null,
            'diagnostics_ran' => false,
            'checks' => self::checks('not_run'),
            'log' => $log,
        ];
    }

    public static function state(bool $attempted, bool $ok): string
    {
        if (! $attempted) {
            return 'skipped';
        }

        return $ok ? 'ok' : 'fail';
    }

    /**
     * Cold start (process still coming up, no auth error) is not a hard failure.
     * A crash-loop or a real DB password mismatch is.
     */
    public static function backendStartupFailed(
        string $status,
        int $exitCode,
        int $restartCount,
        bool $passwordMismatch,
        string $logs,
    ): bool {
        if ($passwordMismatch) {
            return true;
        }
        if (preg_match('/password authentication failed|SQLSTATE\[28P01\]|FATAL:\s+password/i', $logs) === 1) {
            return true;
        }

        $status = strtolower(trim($status));
        if (in_array($status, ['restarting', 'exited', 'dead', 'missing'], true)) {
            return true;
        }
        // Health never came up and the container has been restarting.
        if ($restartCount >= 3) {
            return true;
        }
        if ($exitCode > 0 && $status !== 'running' && $status !== 'created' && $status !== '') {
            return true;
        }

        return false;
    }
}
