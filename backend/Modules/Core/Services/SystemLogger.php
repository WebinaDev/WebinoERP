<?php

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parity with webinocrm class-logger.php + class-log-database.php.
 *
 * Writes to the `core_system_logs` table with richer fields than the previous port:
 *   - severity (1=emergency .. 7=debug) — numeric for easy range filters
 *   - error_code (matches ErrorCodes::* constants)
 *   - ip / user_agent captured from the current request (if any)
 *   - context JSON preserved
 *
 * Falls back to the Laravel `Log` facade if the DB insert fails (e.g. before migrations have run).
 */
class SystemLogger
{
    public const SEV_EMERGENCY = 1;

    public const SEV_ALERT = 2;

    public const SEV_CRITICAL = 3;

    public const SEV_ERROR = 4;

    public const SEV_WARNING = 5;

    public const SEV_NOTICE = 6;

    public const SEV_INFO = 7;

    public const SEV_DEBUG = 8;

    private const LEVEL_TO_SEVERITY = [
        'emergency' => self::SEV_EMERGENCY,
        'alert' => self::SEV_ALERT,
        'critical' => self::SEV_CRITICAL,
        'error' => self::SEV_ERROR,
        'warning' => self::SEV_WARNING,
        'notice' => self::SEV_NOTICE,
        'info' => self::SEV_INFO,
        'debug' => self::SEV_DEBUG,
    ];

    public function __construct(private ?Request $request = null) {}

    /**
     * @param  array<string,mixed>  $context
     */
    public function log(string $level, string $message, array $context = [], ?string $channel = null, ?string $errorCode = null): void
    {
        $level = strtolower($level);
        $severity = self::LEVEL_TO_SEVERITY[$level] ?? self::SEV_INFO;
        $req = $this->request ?? request();
        $user = $req?->user();

        $row = [
            'level' => $level,
            'severity' => $severity,
            'error_code' => $errorCode,
            'channel' => $channel,
            'ip' => $req?->ip(),
            'user_agent' => $req?->userAgent() ? mb_substr($req->userAgent(), 0, 500) : null,
            'message' => mb_substr($message, 0, 10000),
            'context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
            'user_id' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            DB::table('core_system_logs')->insert($row);
        } catch (\Throwable $e) {
            Log::channel('single')->log($level === 'error' ? 'error' : 'info', '[SystemLogger fallback] '.$message, array_merge($context, [
                'error_code' => $errorCode,
                'channel' => $channel,
                'db_error' => $e->getMessage(),
            ]));
        }
    }

    public function info(string $message, array $context = [], ?string $channel = null): void
    {
        $this->log('info', $message, $context, $channel);
    }

    public function warn(string $message, array $context = [], ?string $channel = null, ?string $errorCode = null): void
    {
        $this->log('warning', $message, $context, $channel, $errorCode);
    }

    public function error(string $message, array $context = [], ?string $channel = null, ?string $errorCode = null): void
    {
        $this->log('error', $message, $context, $channel, $errorCode);
    }
}
