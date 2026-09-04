<?php

namespace Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Support\TenantSiteStack;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use RuntimeException;
use Throwable;

/**
 * Deploys an isolated WebinoDashboard stack on a remote Platform server via SSH.
 *
 * Compose uses published images only (no build context):
 *   - webino-backend:latest
 *   - webino-next:latest
 * Those images must already exist on the target host (build/push/pull before provision).
 * Same-VPS installs use LocalSameVpsProvisioner instead.
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
                    'expires_at' => $payload['expires_at'] ?? null,
                    'max_users' => $payload['max_users'] ?? null,
                    'site_type' => $siteType,
                    'site_name' => $payload['site_name'] ?? $provision->slug,
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
        $compose = TenantSiteStack::composeYaml($provision->slug);
        $this->docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
        $envFile = $this->envFile($provision, $siteType, $token);
        $this->docker->writeFile($server, $dir.'/.env', $envFile);

        // Ensure external compose network + published images exist on remote host.
        $preflight = $this->docker->sshRun(
            $server,
            'docker network inspect webino >/dev/null 2>&1 || docker network create webino; '
            .'docker image inspect webino-backend:latest >/dev/null 2>&1 && docker image inspect webino-next:latest >/dev/null 2>&1 '
            .'|| { echo "platform.dashboard_images_missing: pull or load webino-backend:latest and webino-next:latest on this host" >&2; exit 1; }',
            120
        );
        if ($preflight['exit_code'] !== 0) {
            throw new RuntimeException(trim($preflight['stderr'] ?: $preflight['stdout']) ?: 'platform.dashboard_images_missing');
        }

        $up = $this->docker->composeUp($server, $dir, TenantSiteStack::projectName($provision->slug));
        if ($up['exit_code'] !== 0) {
            throw new RuntimeException(trim($up['stderr'] ?: $up['stdout']) ?: 'platform.compose_up_failed');
        }
        $connect = [];
        foreach (TenantSiteStack::proxyContainerNames($provision->slug) as $name) {
            $connect[] = 'docker network connect webino '.escapeshellarg($name).' 2>/dev/null || true';
        }
        $this->docker->sshRun($server, implode('; ', $connect), 60);

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

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $dir = '/var/lib/webino/sites/'.$provision->slug;
        $result = $this->docker->composeUp($server, $dir, TenantSiteStack::projectName($provision->slug));
        $connect = [];
        foreach (TenantSiteStack::proxyContainerNames($provision->slug) as $name) {
            $connect[] = 'docker network connect webino '.escapeshellarg($name).' 2>/dev/null || true';
        }
        $this->docker->sshRun($server, implode('; ', $connect), 60);
        PlatformResource::query()->where('provision_id', $provision->id)->update(['status' => 'running']);

        return $result;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $dir = '/var/lib/webino/sites/'.$provision->slug;
        $result = $this->docker->composeStop($server, $dir, TenantSiteStack::projectName($provision->slug));
        PlatformResource::query()->where('provision_id', $provision->id)->update(['status' => 'stopped']);

        return $result;
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 200): string
    {
        $server = $this->serverFor($provision);
        $dir = '/var/lib/webino/sites/'.$provision->slug;
        $r = $this->docker->sshRun(
            $server,
            'cd '.escapeshellarg($dir).' && docker compose -p '.escapeshellarg(TenantSiteStack::projectName($provision->slug)).' logs --tail '.((int) $tail).' 2>&1',
            60
        );

        return trim($r['stdout'].$r['stderr']);
    }

    protected function serverFor(WebinoSiteProvision $provision): PlatformServer
    {
        $resource = PlatformResource::query()->where('provision_id', $provision->id)->first();
        $serverId = $resource?->server_id ?: (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
        $server = $serverId ? PlatformServer::query()->find($serverId) : null;
        if (! $server) {
            throw new RuntimeException('platform.no_ready_server');
        }

        return $server;
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return implode("\n", [
            $this->envLine('APP_ENV', 'production'),
            $this->envLine('APP_KEY', 'base64:'.base64_encode(random_bytes(32))),
            $this->envLine('DB_CONNECTION', 'pgsql'),
            $this->envLine('DB_HOST', 'db'),
            $this->envLine('DB_DATABASE', 'webino'),
            $this->envLine('DB_USERNAME', 'webino'),
            $this->envLine('DB_PASSWORD', Str::random(24)),
            $this->envLine('REDIS_HOST', 'redis'),
            $this->envLine('RUN_MIGRATIONS', '1'),
            $this->envLine('WEBINO_BASE_URL', $crm),
            $this->envLine('TENANT_LICENSE_KEY', (string) ($provision->license?->license_key ?? '')),
            $this->envLine('TENANT_PROVISION_TOKEN', $token),
            $this->envLine('TENANT_SEED_JSON', (string) $seed),
            $this->envLine('WEBINO_PROVISION_HMAC_SECRET', (string) ($settings->provision_webhook_secret ?? '')),
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

    protected function envLine(string $key, string $value): string
    {
        $escaped = str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);

        return $key.'="'.$escaped.'"';
    }
}
