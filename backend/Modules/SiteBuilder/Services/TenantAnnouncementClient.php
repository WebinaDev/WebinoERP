<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Support\SiteAnnouncementPayload;
use RuntimeException;
use Throwable;

/**
 * HMAC POST into a tenant dashboard. Header and body rules match
 * LocalSameVpsProvisioner::callTenantApi so Site Control and this sender
 * verify the same way on WebinoDashboard.
 */
class TenantAnnouncementClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function push(WebinoSiteProvision $provision, array $payload): array
    {
        $secret = (string) (CoreHostingSetting::current()->provision_webhook_secret ?? '');
        $token = (string) ($provision->provision_token ?? '');
        if ($secret === '') {
            throw new RuntimeException('کلید امضای پروویژن در تنظیمات هاستینگ ERP خالی است.');
        }
        if ($token === '') {
            throw new RuntimeException('توکن پروویژن این سایت خالی است.');
        }

        $body = json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('بدنه اطلاعیه قابل رمزگذاری نیست.');
        }

        $domain = strtolower(trim((string) $provision->domain));
        try {
            $response = Http::withHeaders([
                'X-Provision-Token' => $token,
                'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')
                ->timeout(20)
                ->post('https://'.$domain.'/api/v1/'.SiteAnnouncementPayload::PATH);
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
