<?php

namespace Modules\SiteBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant backends call ERP with the same provision HMAC used by Site Control.
 * Body bytes are hashed as received. Token selects the site.
 */
class VerifySiteProvisionSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('X-Provision-Token', '');
        $signature = (string) $request->header('X-Provision-Signature', '');
        if ($token === '' || $signature === '') {
            return response()->json(['message' => 'توکن یا امضای پروویژن ارسال نشده است.'], 403);
        }

        $site = WebinoSiteProvision::query()->where('provision_token', $token)->first();
        if (! $site) {
            return response()->json(['message' => 'توکن پروویژن نامعتبر است.'], 403);
        }

        $secret = (string) (CoreHostingSetting::current()->provision_webhook_secret ?? '');
        if ($secret === '') {
            return response()->json(['message' => 'کلید امضای پروویژن در ERP تنظیم نشده است.'], 503);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'امضای درخواست نامعتبر است.'], 403);
        }

        $request->attributes->set('site_provision', $site);

        return $next($request);
    }
}
