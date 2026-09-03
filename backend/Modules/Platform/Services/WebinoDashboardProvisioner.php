<?php

namespace Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use RuntimeException;
use Throwable;

/**
 * Deploys an isolated WebinoDashboard stack on a Platform server (Coolify-style).
 */
class WebinoDashboardProvisioner
{
    public function __construct(
        private readonly DockerRemoteService $docker,
        private readonly SshExecutor $ssh,
        private readonly LicenseProvisionerService $licenses,
    ) {}

    public function provisionFromSiteBuilder(WebinoSiteProvision $provision, PlatformServer $server, ?string $siteTypeSlug = null): PlatformResource
    {
        $provision->load(['package.businessType.category', 'package.features', 'crmAccount', 'license']);
        $payload = $provision->wizard_payload ?? [];
        $siteType = $siteTypeSlug
            ?: ($payload['site_type_slug'] ?? null)
            ?: ($provision->package?->businessType?->slug)
            ?: 'corporate';

        if (! $provision->license_id) {
            $license = $this->licenses->createForProvision(
                $provision->domain,
                $provision->package,
                [
                    'selected_feature_slugs' => $payload['selected_feature_slugs'] ?? [],
                    'extra_module_slugs' => $payload['extra_module_slugs'] ?? ['dashboard', 'modules'],
                    'expires_at' => $payload['expires_at'] ?? null,
                    'max_users' => $payload['max_users'] ?? null,
                    'site_type' => $siteType,
                ],
                $provision->created_by,
            );
            $provision->license_id = $license->id;
            $provision->save();
            $provision->load('license');
        }

        $token = $provision->provision_token ?: Str::random(48);
        $provision->provision_token = $token;
        $provision->status = WebinoSiteProvision::STATUS_PROVISIONING;
        $provision->launched_at = now();
        $provision->save();

        $dir = '/var/lib/webino/sites/'.$provision->slug;
        $compose = $this->composeYaml($provision, $siteType, $token);
        $this->docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
        $envFile = $this->envFile($provision, $siteType, $token);
        $this->docker->writeFile($server, $dir.'/.env', $envFile);

        $up = $this->docker->composeUp($server, $dir);
        if ($up['exit_code'] !== 0) {
            throw new RuntimeException(trim($up['stderr'] ?: $up['stdout']) ?: 'platform.compose_up_failed');
        }

        $resource = PlatformResource::query()->create([
            'environment_id' => $this->ensureDefaultEnvironment($provision)->id,
            'server_id' => $server->id,
            'type' => 'webino_dashboard',
            'name' => $provision->slug,
            'status' => 'running',
            'fqdn' => $provision->domain,
            'build_pack' => 'compose',
            'site_type_slug' => $siteType,
            'license_id' => $provision->license_id,
            'crm_account_id' => $provision->crm_account_id,
            'provision_id' => $provision->id,
            'docker_compose_raw' => $compose,
            'settings' => ['site_dir' => $dir],
        ]);

        PlatformDomain::query()->create([
            'resource_id' => $resource->id,
            'domain' => $provision->domain,
            'ssl_status' => 'pending',
        ]);

        PlatformDeployment::query()->create([
            'resource_id' => $resource->id,
            'status' => 'success',
            'logs' => $up['stdout']."\n".$up['stderr'],
            'triggered_by' => $provision->created_by,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $caddy = $this->caddySnippet($provision->domain, $provision->slug);
        $this->docker->writeFile($server, '/etc/caddy/webino.d/'.$provision->slug.'.caddy', $caddy);
        $this->ssh->run($server, 'systemctl reload caddy 2>/dev/null || caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || true');

        try {
            if ($this->waitForHealthy($provision->domain)) {
                $this->bootstrapRemote($provision, $siteType, $token);
                $provision->status = WebinoSiteProvision::STATUS_READY;
                $provision->ready_at = now();
            } else {
                $provision->status = WebinoSiteProvision::STATUS_SSL_PENDING;
            }
            $provision->error_log = null;
            $provision->save();
        } catch (Throwable $e) {
            $provision->status = WebinoSiteProvision::STATUS_SSL_PENDING;
            $provision->error_log = $e->getMessage();
            $provision->save();
        }

        return $resource;
    }

    protected function ensureDefaultEnvironment(WebinoSiteProvision $provision): \Modules\Platform\Entities\PlatformEnvironment
    {
        $accountId = $provision->crm_account_id;
        $name = $provision->crmAccount?->name ?? $provision->slug;
        if ($accountId) {
            $project = \Modules\Platform\Entities\PlatformProject::query()->firstOrCreate(
                ['crm_account_id' => $accountId],
                ['name' => $name, 'description' => 'Auto-created from Site Builder']
            );
            if ($project->name !== $name && filled($name)) {
                $project->update(['name' => $name]);
            }
        } else {
            $project = \Modules\Platform\Entities\PlatformProject::query()->firstOrCreate(
                ['name' => $name, 'crm_account_id' => null],
                ['description' => 'Auto-created from Site Builder']
            );
        }

        return \Modules\Platform\Entities\PlatformEnvironment::query()->firstOrCreate(
            ['project_id' => $project->id, 'name' => 'production']
        );
    }

    protected function composeYaml(WebinoSiteProvision $provision, string $siteType, string $token): string
    {
        $slug = $provision->slug;
        return <<<YAML
services:
  db:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: webino
      POSTGRES_USER: webino
      POSTGRES_PASSWORD: \${DB_PASSWORD}
    volumes:
      - {$slug}_db:/var/lib/postgresql/data
    networks: [webino]
  redis:
    image: redis:7-alpine
    networks: [webino]
  backend:
    image: webino-backend:latest
    env_file: .env
    depends_on: [db, redis]
    networks: [webino]
  frontend:
    image: webino-next:latest
    environment:
      INTERNAL_API_URL: http://backend:8080
      API_PROXY_TARGET: http://backend:8080
    depends_on: [backend]
    networks: [webino]
volumes:
  {$slug}_db:
networks:
  webino:
    external: true
YAML;
    }

    protected function envFile(WebinoSiteProvision $provision, string $siteType, string $token): string
    {
        $settings = CoreHostingSetting::current();
        $crm = rtrim((string) ($settings->public_crm_url ?: config('app.url')), '/');
        $seed = json_encode([
            'tenant_name' => $provision->wizard_payload['site_name'] ?? $provision->slug,
            'domain' => $provision->domain,
            'license_key' => $provision->license?->license_key,
            'site_type_slug' => $siteType,
            'business_type_slug' => $siteType,
            'crm_account_id' => $provision->crm_account_id,
            'admin_email' => $provision->wizard_payload['admin_email'] ?? null,
            'admin_name' => $provision->wizard_payload['admin_name'] ?? 'Admin',
        ], JSON_UNESCAPED_UNICODE);

        return implode("\n", [
            'APP_ENV=production',
            'APP_KEY=base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION=pgsql',
            'DB_HOST=db',
            'DB_DATABASE=webino',
            'DB_USERNAME=webino',
            'DB_PASSWORD='.Str::random(24),
            'REDIS_HOST=redis',
            'RUN_MIGRATIONS=1',
            'WEBINO_BASE_URL='.$crm,
            'TENANT_LICENSE_KEY='.($provision->license?->license_key ?? ''),
            'TENANT_PROVISION_TOKEN='.$token,
            'TENANT_SEED_JSON='.$seed,
            'WEBINO_PROVISION_HMAC_SECRET='.($settings->provision_webhook_secret ?? ''),
        ])."\n";
    }

    protected function caddySnippet(string $domain, string $slug): string
    {
        return <<<CADDY
{$domain} {
  encode gzip
  handle /api/* {
    reverse_proxy {$slug}-backend-1:8080
  }
  handle {
    reverse_proxy {$slug}-frontend-1:3000
  }
}
CADDY;
    }

    protected function waitForHealthy(string $domain, int $attempts = 12): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                if (Http::timeout(8)->get('https://'.$domain.'/up')->successful()) {
                    return true;
                }
            } catch (Throwable) {
            }
            sleep(5);
        }
        return false;
    }

    protected function bootstrapRemote(WebinoSiteProvision $provision, string $siteType, string $token): void
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($settings->provision_webhook_secret ?? '');
        if ($secret === '') {
            throw new RuntimeException('platform.provision_hmac_missing');
        }
        $seed = [
            'tenant_name' => $provision->wizard_payload['site_name'] ?? $provision->slug,
            'domain' => $provision->domain,
            'license_key' => $provision->license?->license_key,
            'site_type_slug' => $siteType,
            'business_type_slug' => $siteType,
            'crm_account_id' => $provision->crm_account_id,
            'admin_email' => $provision->wizard_payload['admin_email'] ?? null,
            'admin_name' => $provision->wizard_payload['admin_name'] ?? 'Admin',
        ];
        $body = json_encode(['seed' => $seed], JSON_UNESCAPED_UNICODE);
        Http::withHeaders([
            'X-Provision-Token' => $token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
        ])->withBody($body, 'application/json')
            ->timeout(60)
            ->post('https://'.$provision->domain.'/api/v1/provision/bootstrap')
            ->throw();
    }
}
