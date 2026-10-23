<?php

namespace Modules\Marketing\Observers;

use Illuminate\Support\Facades\Http;

/**
 * After a builder page / global / template save or delete, purge the Next.js data cache via the
 * frontend's secret-protected /webino-revalidate route. No-op unless FRONTEND_REVALIDATE_URL and
 * REVALIDATE_SECRET are configured.
 */
class FrontendRevalidateObserver
{
    public function saved(object $model): void
    {
        self::revalidate();
    }

    public function deleted(object $model): void
    {
        self::revalidate();
    }

    /** @param  list<string>  $tags */
    public static function revalidate(array $tags = ['builder', 'poetry', 'catalog']): bool
    {
        $url = (string) config('services.frontend_revalidate.url', '');
        $secret = (string) config('services.frontend_revalidate.secret', '');
        if ($url === '' || $secret === '') {
            return false;
        }
        try {
            return Http::timeout(3)->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url, ['tags' => $tags])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
