<?php

namespace Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Support\TenantAliasDomains;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Throwable;

/**
 * Tenant Dashboard calls this (provision HMAC) when the LinkHub subdomain label changes.
 */
class SiteAliasSyncController extends Controller
{
    public function sync(Request $request, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        $site = $request->attributes->get('site_provision');
        if (! $site instanceof WebinoSiteProvision) {
            return response()->json(['message' => 'سایت یافت نشد.'], 403);
        }

        $data = $request->validate([
            'alias_subdomain' => 'required|string|max:63',
        ]);

        try {
            $orchestrator->syncAliasDomains($site, $data['alias_subdomain']);
            $fresh = $site->fresh();
            $host = TenantAliasDomains::hostname(
                (string) $fresh->domain,
                (string) $fresh->alias_subdomain,
            );

            return response()->json([
                'data' => [
                    'alias_subdomain' => $fresh->alias_subdomain,
                    'alias_hostname' => $host,
                    'domain_aliases' => $host ? [$host] : [],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'همگام‌سازی ساب‌دامین ناموفق بود.'], 422);
        }
    }
}
