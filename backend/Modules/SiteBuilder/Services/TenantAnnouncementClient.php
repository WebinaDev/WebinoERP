<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Facades\Http;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Support\ErpApiToken;
use Modules\SiteBuilder\Support\SiteAnnouncementPayload;
use RuntimeException;
use Throwable;

/**
 * Pushes one notice to a tenant dashboard.
 * Authorization: Bearer WEBINO_ERP_API_TOKEN
 * POST /api/v1/integrations/erp/announcements
 */
class TenantAnnouncementClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function push(WebinoSiteProvision $provision, array $payload): array
    {
        $token = ErpApiToken::current();
        if ($token === '') {
            throw new RuntimeException('توکن WEBINO_ERP_API_TOKEN تنظیم نشده است.');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('بدنه اطلاعیه قابل رمزگذاری نیست.');
        }

        $domain = strtolower(trim((string) $provision->domain));
        $url = 'https://'.$domain.'/api/v1/'.SiteAnnouncementPayload::PATH;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')
                ->timeout(20)
                ->post($url);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'ارتباط با سایت برقرار نشد (https://'.$domain.'): '.$e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            $tenantMessage = data_get($response->json(), 'message');
            if (is_string($tenantMessage) && $tenantMessage !== '') {
                throw new RuntimeException($tenantMessage);
            }

            throw new RuntimeException('درخواست به سایت ناموفق بود (HTTP '.$response->status().').');
        }

        return $response->json() ?? [];
    }
}
