<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Services\SiteAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-site HMAC auth for WP-facing Edge routes. The claimed domain comes from the `domain`
 * input, or the host of `site_url` (Basalam).
 */
class VerifyWebinoSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $claimed = (string) ($request->input('domain') ?? $request->query('domain', ''));
        if ($claimed === '' && is_string($request->input('site_url'))) {
            $claimed = (string) (parse_url((string) $request->input('site_url'), PHP_URL_HOST) ?: '');
        }

        $result = SiteAuth::authorize($request, $claimed);
        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'unavailable' => true,
                'code' => $result['reason'] ?? 'unauthorized',
                'message' => __('messages.site_auth_failed'),
            ], 401);
        }

        return $next($request);
    }
}
