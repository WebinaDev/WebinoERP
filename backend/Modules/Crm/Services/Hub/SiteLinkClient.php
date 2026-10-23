<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Crm\Entities\CrmAccount;
use RuntimeException;

/**
 * ERP → tenant site panel (WebinoDashboard /api/v1/erp-link/*).
 * Auth = the site's provision_token + HMAC(body, provision_webhook_secret); body carries `ts`
 * so the site can reject replays. Short timeouts: the hub must never hang on a dead site.
 */
class SiteLinkClient
{
    public const LIVE = ['ready', 'active', 'live'];

    /** Live sites (provisions) owned by the account that can be called. */
    public function sites(CrmAccount $a): Collection
    {
        if (! Schema::hasTable('webino_site_provisions')) {
            return collect();
        }

        return DB::table('webino_site_provisions')->where('crm_account_id', $a->id)
            ->whereIn('status', self::LIVE)->whereNotNull('domain')->whereNotNull('provision_token')
            ->orderBy('id')->get(['id', 'domain', 'status', 'provision_token']);
    }

    /** @return array<string,mixed> */
    public function call(object $site, string $path, array $payload = [], int $timeout = 8): array
    {
        return $this->callRaw($site, 'erp-link/'.$path, $payload, $timeout);
    }

    /** Signed POST to https://{domain}/api/v1/{path}. */
    public function callRaw(object $site, string $path, array $payload = [], int $timeout = 8): array
    {
        $secret = (string) (CoreHostingSetting::current()->provision_webhook_secret ?? '');
        if ($secret === '') {
            throw new RuntimeException('provision_webhook_secret is not configured');
        }
        $body = json_encode($payload + ['ts' => time()], JSON_UNESCAPED_UNICODE);
        $res = Http::withHeaders([
            'X-Provision-Token' => (string) $site->provision_token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
            'X-Tenant-Domain' => (string) $site->domain,
            'Accept' => 'application/json',
        ])->withBody($body, 'application/json')->connectTimeout(3)->timeout($timeout)
            ->post('https://'.$site->domain.'/api/v1/'.$path);
        if (! $res->successful()) {
            throw new RuntimeException('HTTP '.$res->status().': '.mb_substr((string) (data_get($res->json(), 'message') ?? $res->body()), 0, 200));
        }

        return (array) ($res->json('data') ?? []);
    }

    /** Orders / tickets / KYC of this customer on each of their sites (cached 60s per account). */
    public function customerPanels(CrmAccount $a, bool $fresh = false): array
    {
        $key = 'crm_hub:site_panel:'.$a->id;
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 60, function () use ($a) {
            $contacts = Schema::hasTable('crm_contacts')
                ? DB::table('crm_contacts')->where('account_id', $a->id)->whereNull('deleted_at')->orderByDesc('is_primary')->limit(3)->get(['email', 'mobile', 'phone'])
                : collect();
            $email = $contacts->pluck('email')->filter()->first();
            $phone = $contacts->map(fn ($c) => $c->mobile ?: $c->phone)->filter()->first();
            $out = [];
            foreach ($this->sites($a) as $site) {
                try {
                    $data = $this->call($site, 'customer', array_filter(['erp_customer_id' => $a->id, 'email' => $email, 'phone' => $phone]));
                    $out[] = ['provision_id' => $site->id, 'domain' => $site->domain, 'ok' => true] + $data;
                } catch (\Throwable $e) {
                    $out[] = ['provision_id' => $site->id, 'domain' => $site->domain, 'ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300),
                        'panel_url' => 'https://'.$site->domain.'/dashboard'];
                }
            }

            return ['sites' => $out, 'fetched_at' => now()->toIso8601String(), 'lookup' => ['email' => $email, 'phone' => $phone]];
        });
    }
}
