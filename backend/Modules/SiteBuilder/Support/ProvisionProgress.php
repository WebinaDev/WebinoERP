<?php

namespace Modules\SiteBuilder\Support;

use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use RuntimeException;

/**
 * Tracks multi-phase site provision progress for the wizard launch panel.
 *
 * @phpstan-type ProgressShape array{
 *   phase: string,
 *   percent: int,
 *   label_fa: string,
 *   label_en: string,
 *   eta_seconds: int|null,
 *   images_cached: bool|null,
 *   updated_at: string
 * }
 */
final class ProvisionProgress
{
    public const PHASE_QUEUED = 'queued';

    public const PHASE_FETCH_SOURCE = 'fetch_source';

    public const PHASE_BUILD_IMAGES = 'build_images';

    public const PHASE_WRITE_STACK = 'write_stack';

    public const PHASE_COMPOSE_UP = 'compose_up';

    public const PHASE_HEALTH = 'health';

    public const PHASE_BOOTSTRAP = 'bootstrap';

    public const PHASE_SSL = 'ssl';

    public const PHASE_DONE = 'done';

    public const PHASE_FAILED = 'failed';

    public const PHASE_CANCELLED = 'cancelled';

    /** @var array<string, array{percent: int, label_fa: string, label_en: string, eta_build: int, eta_cached: int}> */
    public const PHASES = [
        self::PHASE_QUEUED => [
            'percent' => 5,
            'label_fa' => 'در صف اجرا',
            'label_en' => 'Queued',
            'eta_build' => 1200,
            'eta_cached' => 180,
        ],
        self::PHASE_FETCH_SOURCE => [
            'percent' => 15,
            'label_fa' => 'دریافت هسته از GitHub',
            'label_en' => 'Fetching source from GitHub',
            'eta_build' => 1100,
            'eta_cached' => 160,
        ],
        self::PHASE_BUILD_IMAGES => [
            'percent' => 35,
            'label_fa' => 'ساخت ایمیج‌های Docker',
            'label_en' => 'Building Docker images',
            'eta_build' => 900,
            'eta_cached' => 120,
        ],
        self::PHASE_WRITE_STACK => [
            'percent' => 50,
            'label_fa' => 'نوشتن استک سایت',
            'label_en' => 'Writing site stack',
            'eta_build' => 400,
            'eta_cached' => 90,
        ],
        self::PHASE_COMPOSE_UP => [
            'percent' => 65,
            'label_fa' => 'بالا آوردن کانتینرها',
            'label_en' => 'Starting containers',
            'eta_build' => 300,
            'eta_cached' => 70,
        ],
        self::PHASE_HEALTH => [
            'percent' => 80,
            'label_fa' => 'بررسی سلامت سرویس‌ها',
            'label_en' => 'Health check',
            'eta_build' => 180,
            'eta_cached' => 45,
        ],
        self::PHASE_BOOTSTRAP => [
            'percent' => 90,
            'label_fa' => 'راه‌اندازی اولیهٔ سایت',
            'label_en' => 'Bootstrapping tenant',
            'eta_build' => 90,
            'eta_cached' => 30,
        ],
        self::PHASE_SSL => [
            'percent' => 95,
            'label_fa' => 'فعال‌سازی SSL',
            'label_en' => 'SSL pending',
            'eta_build' => 60,
            'eta_cached' => 20,
        ],
        self::PHASE_DONE => [
            'percent' => 100,
            'label_fa' => 'سایت آماده است',
            'label_en' => 'Site ready',
            'eta_build' => 0,
            'eta_cached' => 0,
        ],
        self::PHASE_FAILED => [
            'percent' => 100,
            'label_fa' => 'ناموفق',
            'label_en' => 'Failed',
            'eta_build' => 0,
            'eta_cached' => 0,
        ],
        self::PHASE_CANCELLED => [
            'percent' => 100,
            'label_fa' => 'لغو شد',
            'label_en' => 'Cancelled',
            'eta_build' => 0,
            'eta_cached' => 0,
        ],
    ];

    /**
     * @return ProgressShape
     */
    public static function make(string $phase, ?bool $imagesCached = null, ?string $labelFa = null, ?string $labelEn = null): array
    {
        $meta = self::PHASES[$phase] ?? self::PHASES[self::PHASE_QUEUED];
        $cached = $imagesCached ?? false;

        return [
            'phase' => $phase,
            'percent' => $meta['percent'],
            'label_fa' => $labelFa ?? $meta['label_fa'],
            'label_en' => $labelEn ?? $meta['label_en'],
            'eta_seconds' => $cached ? $meta['eta_cached'] : $meta['eta_build'],
            'images_cached' => $imagesCached,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public static function report(WebinoSiteProvision $provision, string $phase, ?bool $imagesCached = null, ?string $labelFa = null, ?string $labelEn = null): void
    {
        $provision->refresh();
        if ($provision->status === WebinoSiteProvision::STATUS_CANCELLED) {
            throw new RuntimeException('platform.provision_cancelled');
        }

        $existing = is_array($provision->progress) ? $provision->progress : [];
        $cached = $imagesCached;
        if ($cached === null && array_key_exists('images_cached', $existing)) {
            $cached = $existing['images_cached'] === null ? null : (bool) $existing['images_cached'];
        }

        $provision->update([
            'progress' => self::make($phase, $cached, $labelFa, $labelEn),
        ]);
    }

    public static function assertNotCancelled(WebinoSiteProvision $provision): void
    {
        $provision->refresh();
        if ($provision->status === WebinoSiteProvision::STATUS_CANCELLED) {
            throw new RuntimeException('platform.provision_cancelled');
        }
    }
}
