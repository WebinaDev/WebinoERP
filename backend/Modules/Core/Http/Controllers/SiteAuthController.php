<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Core\Services\SiteAuth;

/**
 * POST /api/webinocrm/v1/site-auth/enroll
 *
 * Proof of domain control: the ERP generates the site secret and delivers it only to
 * `receive_url` on the claimed domain (HTTPS), echoing the site's one-time `pending` token.
 * A caller that does not control the domain never sees the secret. Re-enrolling an already
 * enrolled domain requires a valid signature with the current secret (or an admin reset).
 */
class SiteAuthController extends Controller
{
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'receive_url' => 'required|url|max:500',
            'pending' => 'required|string|min:32|max:128',
        ]);
        $domain = CoreLicenseResolver::normalizeDomain($data['domain']);
        $receive = (string) $data['receive_url'];
        $host = CoreLicenseResolver::normalizeDomain((string) (parse_url($receive, PHP_URL_HOST) ?: ''));
        $scheme = strtolower((string) parse_url($receive, PHP_URL_SCHEME));

        if ($domain === '' || $host !== $domain) {
            return response()->json(['ok' => false, 'code' => 'host_mismatch', 'message' => __('messages.site_auth_failed')], 422);
        }
        if ($scheme !== 'https' && ! (bool) config('integrations.site_auth.allow_http', false)) {
            return response()->json(['ok' => false, 'code' => 'https_required', 'message' => __('messages.site_auth_failed')], 422);
        }
        if (SiteAuth::isEnrolled($domain) && SiteAuth::verifiedDomain($request) !== $domain) {
            return response()->json(['ok' => false, 'code' => 'already_enrolled', 'message' => __('messages.site_auth_already_enrolled')], 409);
        }

        $secret = Str::random(64);
        try {
            $res = Http::timeout(15)->connectTimeout(8)->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->post($receive, ['pending' => $data['pending'], 'secret' => $secret, 'domain' => $domain]);
        } catch (\Throwable $e) {
            Log::warning('site_auth.enroll_callback_failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'code' => 'callback_failed', 'message' => __('messages.site_auth_failed')], 502);
        }
        if (! $res->successful() || $res->json('ok') !== true) {
            return response()->json(['ok' => false, 'code' => 'callback_rejected', 'message' => __('messages.site_auth_failed')], 502);
        }

        SiteAuth::storeSecret($domain, $secret);
        Log::info('site_auth.enrolled', ['domain' => $domain]);

        return response()->json(['ok' => true, 'domain' => $domain, 'mode' => SiteAuth::mode()]);
    }

    /** Signed probe so a site can confirm its secret works (and see the current mode). */
    public function status(Request $request): JsonResponse
    {
        $domain = SiteAuth::verifiedDomain($request);

        return response()->json([
            'ok' => $domain !== null,
            'domain' => $domain,
            'mode' => SiteAuth::mode(),
        ], $domain !== null ? 200 : 401);
    }
}
