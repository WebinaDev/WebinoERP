<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Integrations\Services\WebinoServerPanelClient;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionAuditLogger;
use Throwable;

class SiteProvisionOrchestrator
{
    public function __construct(
        private readonly WebinoServerPanelClient $panel,
        private readonly LicenseProvisionerService $licenses,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    public function launch(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        $provision->load(['package.businessType.category', 'package.features', 'crmAccount']);

        $settings = CoreHostingSetting::current();
        if (! $this->panel->isConfigured()) {
            return $this->fail($provision, 'WebinoServer panel is not configured.');
        }

        $payload = $provision->wizard_payload ?? [];
        $slug = $provision->slug;
        $domain = $provision->domain;

        try {
            if (! $provision->license_id) {
                $license = $this->licenses->createForProvision(
                    $domain,
                    $provision->package,
                    [
                        'selected_feature_slugs' => $payload['selected_feature_slugs'] ?? [],
                        'extra_module_slugs' => $payload['extra_module_slugs'] ?? ['dashboard', 'modules'],
                        'expires_at' => $payload['expires_at'] ?? null,
                        'max_users' => $payload['max_users'] ?? null,
                    ],
                    $provision->created_by,
                );
                $provision->license_id = $license->id;
                $provision->save();
            }

            $license = $provision->license;
            $token = $provision->provision_token ?: Str::random(48);
            $provision->provision_token = $token;
            $provision->status = WebinoSiteProvision::STATUS_PROVISIONING;
            $provision->launched_at = now();
            $provision->save();

            $crmUrl = $settings->public_crm_url ?: config('app.url');
            $seed = $this->buildSeedJson($provision, $payload);

            $this->panel->ensureProductInstalled('Webino', $settings->default_product_channel ?? 'LTS');

            if (! $provision->uses_custom_domain && $provision->subdomain && filled($settings->platform_base_domain)) {
                $this->tryCreateSubdomainDns($provision->subdomain.'.'.$settings->platform_base_domain);
            }

            $aliases = [];
            if (! $provision->uses_custom_domain && $provision->subdomain) {
                $aliases[] = 'www.'.$domain;
            }

            $this->panel->createSite([
                'slug' => $slug,
                'domain' => $domain,
                'product' => 'Webino',
                'channel' => $settings->default_product_channel ?? 'LTS',
                'aliases' => $aliases,
                'env' => [
                    'WEBINO_BASE_URL' => rtrim((string) $crmUrl, '/'),
                    'TENANT_LICENSE_KEY' => $license?->license_key,
                    'TENANT_PROVISION_TOKEN' => $token,
                    'TENANT_SEED_JSON' => json_encode($seed, JSON_UNESCAPED_UNICODE),
                ],
            ]);

            if ($this->waitForHealthy($domain)) {
                $this->bootstrapRemoteSite($domain, $token, $seed);
                $provision->status = WebinoSiteProvision::STATUS_READY;
                $provision->ready_at = now();
                $this->audit->log($provision->created_by, 'provision.ready', $provision);
            } else {
                $provision->status = WebinoSiteProvision::STATUS_SSL_PENDING;
                $this->audit->log($provision->created_by, 'provision.ssl_pending', $provision);
            }

            $provision->error_log = null;
            $provision->save();
        } catch (Throwable $e) {
            return $this->fail($provision, $e->getMessage());
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function poll(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if ($provision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            if ($this->waitForHealthy($provision->domain, attempts: 1)) {
                $payload = $provision->wizard_payload ?? [];
                $seed = $this->buildSeedJson($provision, $payload);
                try {
                    $this->bootstrapRemoteSite($provision->domain, (string) $provision->provision_token, $seed);
                    $provision->update([
                        'status' => WebinoSiteProvision::STATUS_READY,
                        'ready_at' => now(),
                    ]);
                } catch (Throwable $e) {
                    $provision->update(['error_log' => $e->getMessage()]);
                }
            }
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function rollback(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        try {
            if ($this->panel->isConfigured()) {
                $this->panel->deleteSite($provision->slug);
            }
        } catch (Throwable) {
            /* best effort */
        }

        if ($provision->license) {
            $this->licenses->revoke($provision->license);
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'error_log' => trim(($provision->error_log ?? '')."\nRolled back."),
        ]);

        return $provision->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildSeedJson(WebinoSiteProvision $provision, array $payload): array
    {
        $package = $provision->package;
        $type = $package?->businessType;
        $category = $type?->category;
        $isCorporate = $category?->slug === 'corporate';

        $seed = [
            'tenant_name' => $payload['site_name'] ?? $provision->slug,
            'store_display_name' => $payload['site_name'] ?? $provision->slug,
            'default_currency' => $payload['currency'] ?? 'IRR',
            'domain' => $provision->domain,
            'license_key' => $provision->license?->license_key,
            'business_category_slug' => $category?->slug,
            'business_type_slug' => $type?->slug,
            'vertical' => $type?->slug,
            'package_sku' => $package?->sku,
            'theme_preset' => $type?->theme_preset,
            'nav_preset' => $type?->nav_preset,
            'branding' => [
                'logo_url' => $payload['logo_url'] ?? null,
                'description' => $payload['description'] ?? null,
            ],
            'admin_email' => $payload['admin_email'] ?? null,
            'admin_name' => $payload['admin_name'] ?? 'Admin',
            'crm_account_id' => $provision->crm_account_id,
        ];

        if ($isCorporate) {
            $seed['active_theme_slug'] = 'corporate-demo-v1';
            $seed['default_pages'] = [
                ['slug' => 'about', 'title' => 'About us', 'body' => 'About our company.', 'published' => true],
                ['slug' => 'services', 'title' => 'Services', 'body' => 'Our services.', 'published' => true],
                ['slug' => 'privacy', 'title' => 'Privacy', 'body' => 'Privacy policy.', 'published' => true],
            ];
            $seed['sample_blog_post'] = [
                'slug' => 'welcome',
                'title' => 'Welcome',
                'excerpt' => 'First post on your new site.',
                'body' => 'Demo blog content provisioned from Site Builder.',
            ];
            $seed['sample_portfolio_item'] = [
                'slug' => 'sample-project',
                'title' => 'Sample project',
                'description' => 'A portfolio showcase item.',
                'client' => 'Demo Client',
            ];
            $seed['sample_team_member'] = [
                'name' => 'Team Lead',
                'role' => 'CEO',
                'bio' => 'Your team member bio.',
            ];
        }

        return $seed;
    }

    protected function waitForHealthy(string $domain, int $attempts = 12): bool
    {
        $url = 'https://'.$domain.'/up';
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $res = Http::timeout(8)->get($url);
                if ($res->successful()) {
                    return true;
                }
            } catch (Throwable) {
                /* retry */
            }
            sleep(5);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    protected function bootstrapRemoteSite(string $domain, string $token, array $seed): void
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($settings->provision_webhook_secret ?? '');
        if ($secret === '') {
            throw new \RuntimeException('Provision HMAC secret is not configured');
        }
        $body = json_encode(['seed' => $seed], JSON_UNESCAPED_UNICODE);
        $headers = [
            'X-Provision-Token' => $token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
        ];

        Http::withHeaders($headers)
            ->withBody($body, 'application/json')
            ->timeout(60)
            ->post('https://'.$domain.'/api/v1/provision/bootstrap')
            ->throw();
    }

    protected function fail(WebinoSiteProvision $provision, string $message): WebinoSiteProvision
    {
        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'error_log' => $message,
        ]);
        $this->audit->log($provision->created_by, 'provision.failed', $provision, ['error' => $message]);

        return $provision->fresh();
    }

    protected function tryCreateSubdomainDns(string $fqdn): void
    {
        try {
            $this->panel->createDnsRecord([
                'zone_id' => 1,
                'record' => [
                    'name' => $fqdn,
                    'type' => 'A',
                    'content' => env('WEBINOSERVER_SERVER_IP', '127.0.0.1'),
                    'ttl' => 300,
                ],
            ]);
        } catch (Throwable) {
            /* DNS module optional — manual DNS guide shown in wizard */
        }
    }
}
