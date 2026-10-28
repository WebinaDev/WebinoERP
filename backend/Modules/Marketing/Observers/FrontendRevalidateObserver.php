<?php

namespace Modules\Marketing\Observers;

use Illuminate\Support\Facades\Http;

/**
 * After a builder page / global / template save or delete, purge the Next.js data cache via the
 * frontend's secret-protected /webino-revalidate route. On by default (URL defaults to the
 * in-network frontend container; secret optional). Opt out with WEBINO_PAGE_CACHE=off.
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
        if ($url === '' || strtolower((string) config('services.frontend_revalidate.page_cache', 'on')) === 'off'
            || app()->runningUnitTests()) {
            return false;
        }
        try {
            return Http::timeout(3)->withHeaders($secret !== '' ? ['x-revalidate-secret' => $secret] : [])
                ->post($url, ['tags' => $tags])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
