<?php

namespace Modules\Platform\Support;

use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/**
 * Per-site upload + Docker resource limits for tenant stacks.
 *
 * Defaults match Dashboard image PHP/nginx (64M upload) and a modest
 * container envelope (512M / 1 CPU per app service). Compose rewrites
 * always emit these defaults when runtime_limits is null.
 */
final class TenantSiteLimits
{
    public const DEFAULT_MAX_UPLOAD_MB = 64;

    public const DEFAULT_BACKEND_MEMORY_MB = 512;

    public const DEFAULT_FRONTEND_MEMORY_MB = 512;

    public const DEFAULT_BACKEND_CPUS = 1.0;

    public const DEFAULT_FRONTEND_CPUS = 1.0;

    public const DEFAULT_DB_MEMORY_MB = 256;

    public const DEFAULT_REDIS_MEMORY_MB = 128;

    public const MIN_UPLOAD_MB = 8;

    public const MAX_UPLOAD_MB = 512;

    public const MIN_MEMORY_MB = 128;

    public const MAX_MEMORY_MB = 8192;

    public const MIN_CPUS = 0.25;

    public const MAX_CPUS = 8.0;

    /**
     * @return array{
     *   max_upload_mb:int,
     *   backend_memory_mb:int,
     *   frontend_memory_mb:int,
     *   backend_cpus:float,
     *   frontend_cpus:float,
     *   db_memory_mb:int,
     *   redis_memory_mb:int
     * }
     */
    public static function defaults(): array
    {
        return [
            'max_upload_mb' => self::DEFAULT_MAX_UPLOAD_MB,
            'backend_memory_mb' => self::DEFAULT_BACKEND_MEMORY_MB,
            'frontend_memory_mb' => self::DEFAULT_FRONTEND_MEMORY_MB,
            'backend_cpus' => self::DEFAULT_BACKEND_CPUS,
            'frontend_cpus' => self::DEFAULT_FRONTEND_CPUS,
            'db_memory_mb' => self::DEFAULT_DB_MEMORY_MB,
            'redis_memory_mb' => self::DEFAULT_REDIS_MEMORY_MB,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{
     *   max_upload_mb:int,
     *   backend_memory_mb:int,
     *   frontend_memory_mb:int,
     *   backend_cpus:float,
     *   frontend_cpus:float,
     *   db_memory_mb:int,
     *   redis_memory_mb:int
     * }
     */
    public static function normalize(?array $stored): array
    {
        $base = self::defaults();
        if (! is_array($stored)) {
            return $base;
        }

        return [
            'max_upload_mb' => self::clampInt($stored['max_upload_mb'] ?? $base['max_upload_mb'], self::MIN_UPLOAD_MB, self::MAX_UPLOAD_MB),
            'backend_memory_mb' => self::clampInt($stored['backend_memory_mb'] ?? $base['backend_memory_mb'], self::MIN_MEMORY_MB, self::MAX_MEMORY_MB),
            'frontend_memory_mb' => self::clampInt($stored['frontend_memory_mb'] ?? $base['frontend_memory_mb'], self::MIN_MEMORY_MB, self::MAX_MEMORY_MB),
            'backend_cpus' => self::clampFloat($stored['backend_cpus'] ?? $base['backend_cpus'], self::MIN_CPUS, self::MAX_CPUS),
            'frontend_cpus' => self::clampFloat($stored['frontend_cpus'] ?? $base['frontend_cpus'], self::MIN_CPUS, self::MAX_CPUS),
            'db_memory_mb' => self::clampInt($stored['db_memory_mb'] ?? $base['db_memory_mb'], self::MIN_MEMORY_MB, self::MAX_MEMORY_MB),
            'redis_memory_mb' => self::clampInt($stored['redis_memory_mb'] ?? $base['redis_memory_mb'], self::MIN_MEMORY_MB, self::MAX_MEMORY_MB),
        ];
    }

    /**
     * @return array{
     *   max_upload_mb:int,
     *   backend_memory_mb:int,
     *   frontend_memory_mb:int,
     *   backend_cpus:float,
     *   frontend_cpus:float,
     *   db_memory_mb:int,
     *   redis_memory_mb:int
     * }
     */
    public static function forProvision(WebinoSiteProvision $provision): array
    {
        $stored = is_array($provision->runtime_limits) ? $provision->runtime_limits : null;

        return self::normalize($stored);
    }

    /** MEDIA_MAX_UPLOAD_KB for tenant .env (Dashboard media library). */
    public static function mediaMaxUploadKb(array $limits): int
    {
        return max(1, (int) round(($limits['max_upload_mb'] ?? self::DEFAULT_MAX_UPLOAD_MB) * 1024));
    }

    /** Docker Compose mem_limit value, e.g. "512m". */
    public static function memLimit(int $mb): string
    {
        return max(self::MIN_MEMORY_MB, $mb).'m';
    }

    /** Docker Compose cpus value as a string suitable for YAML. */
    public static function cpusYaml(float $cpus): string
    {
        $v = round($cpus, 2);
        if (abs($v - (int) $v) < 0.001) {
            return (string) (int) $v;
        }

        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    protected static function clampInt(mixed $value, int $min, int $max): int
    {
        $n = (int) round((float) $value);

        return max($min, min($max, $n));
    }

    protected static function clampFloat(mixed $value, float $min, float $max): float
    {
        $n = (float) $value;

        return max($min, min($max, $n));
    }
}
