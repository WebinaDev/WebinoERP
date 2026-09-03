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
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Provisions an isolated WebinoDashboard stack on the same VPS as WebinoERP (no SSH).
 *
 * Requires host Docker access (docker CLI + ability to reach /var/lib/webino and reload Caddy).
 * Images webino-backend:latest and webino-next:latest must exist (see scripts/build-webino-dashboard-images.sh).
 */
class LocalSameVpsProvisioner
{
    public function __construct(
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

        $this->ensureDockerNetwork();
        $this->ensureImages();

        $dir = '/var/lib/webino/sites/'.$provision->slug;
        $this->ensureDir($dir);

        $compose = $this->composeYaml($provision);
        $envFile = $this->envFile($provision, $siteType, $token);
        $this->writeFile($dir.'/docker-compose.yml', $compose);
        $this->writeFile($dir.'/.env', $envFile);

        $this->writeCaddySnippet($provision->domain, $provision->slug);
        $this->reloadCaddy();

        $up = $this->composeUp($provision->slug, $dir);
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
            'settings' => [
                'site_dir' => $dir,
                'provision_mode' => 'local_same_vps',
            ],
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

        try {
            if ($this->waitForHealthy($provision->slug)) {
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

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $result = $this->composeUp($provision->slug, $dir);
        $this->updateResourceStatus($provision, 'running');

        return $result;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $result = $this->run([
            'docker', 'compose',
            '-p', $provision->slug,
            '-f', $dir.'/docker-compose.yml',
            'stop',
        ], 300);
        $this->updateResourceStatus($provision, 'stopped');

        return $result;
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 200): string
    {
        $dir = $this->siteDir($provision);
        $result = $this->run([
            'docker', 'compose',
            '-p', $provision->slug,
            '-f', $dir.'/docker-compose.yml',
            'logs',
            '--tail', (string) $tail,
        ], 60);

        return trim($result['stdout']."\n".$result['stderr']);
    }

    protected function siteDir(WebinoSiteProvision $provision): string
    {
        return '/var/lib/webino/sites/'.$provision->slug;
    }

    protected function ensureDockerNetwork(): void
    {
        $this->run(['docker', 'network', 'create', 'webino_sites'], 30);
        // Ignore failure when network already exists.

        $web = $this->findErpWebContainer();
        if ($web !== null) {
            $this->run(['docker', 'network', 'connect', 'webino_sites', $web], 30);
        }
    }

    protected function ensureImages(): void
    {
        $backendOk = $this->run(['docker', 'image', 'inspect', 'webino-backend:latest'], 30)['exit_code'] === 0;
        $frontendOk = $this->run(['docker', 'image', 'inspect', 'webino-next:latest'], 30)['exit_code'] === 0;

        if ($backendOk && $frontendOk) {
            return;
        }

        $script = (string) (env('WEBINO_DASHBOARD_BUILD_SCRIPT')
            ?: ($this->erpRoot().'/scripts/build-webino-dashboard-images.sh'));
        if (is_file($script)) {
            $env = [];
            $dashboardPath = (string) env('WEBINO_DASHBOARD_PATH', '');
            if ($dashboardPath !== '' && is_dir($dashboardPath)) {
                $env['WEBINO_DASHBOARD_PATH'] = $dashboardPath;
            }
            $build = $this->runEnv(['bash', $script], 1800, $env);
            if ($build['exit_code'] !== 0) {
                throw new RuntimeException(
                    'platform.dashboard_images_missing: build failed. Run scripts/build-webino-dashboard-images.sh. '
                    .trim($build['stderr'] ?: $build['stdout'])
                );
            }
        }

        $backendOk = $this->run(['docker', 'image', 'inspect', 'webino-backend:latest'], 30)['exit_code'] === 0;
        $frontendOk = $this->run(['docker', 'image', 'inspect', 'webino-next:latest'], 30)['exit_code'] === 0;
        if (! $backendOk || ! $frontendOk) {
            throw new RuntimeException(
                'platform.dashboard_images_missing: need webino-backend:latest and webino-next:latest. '
                .'Build with WebinoERP/scripts/build-webino-dashboard-images.sh (set WEBINO_DASHBOARD_PATH if needed).'
            );
        }
    }

    protected function composeYaml(WebinoSiteProvision $provision): string
    {
        $slug = $provision->slug;

        return <<<YAML
# Published images required: webino-backend:latest, webino-next:latest
# Build via WebinoERP/scripts/build-webino-dashboard-images.sh
services:
  db:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: webino
      POSTGRES_USER: webino
      POSTGRES_PASSWORD: \${DB_PASSWORD}
    volumes:
      - {$slug}_db:/var/lib/postgresql/data
    networks: [webino_sites]
  redis:
    image: redis:7-alpine
    networks: [webino_sites]
  backend:
    image: webino-backend:latest
    env_file: .env
    depends_on: [db, redis]
    networks: [webino_sites]
  frontend:
    image: webino-next:latest
    environment:
      INTERNAL_API_URL: http://backend:8080
      API_PROXY_TARGET: http://backend:8080
    depends_on: [backend]
    networks: [webino_sites]
volumes:
  {$slug}_db:
networks:
  webino_sites:
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

    protected function writeCaddySnippet(string $domain, string $slug): void
    {
        $snippet = <<<CADDY
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

        $repoSites = (string) (env('WEBINO_SITES_CADDY_DIR')
            ?: ($this->erpRoot().'/docker/caddy/sites'));
        $this->ensureDir($repoSites);
        $this->writeFile(rtrim($repoSites, '/').'/'.$slug.'.caddy', $snippet);

        $hostSites = '/var/lib/webino/caddy.d';
        $this->ensureDir($hostSites);
        $this->writeFile($hostSites.'/'.$slug.'.caddy', $snippet);
    }

    protected function reloadCaddy(): void
    {
        $web = $this->findErpWebContainer();
        if ($web !== null) {
            $this->run(['docker', 'exec', $web, 'caddy', 'reload', '--config', '/etc/caddy/Caddyfile'], 60);

            return;
        }

        $this->run(['sh', '-c', 'systemctl reload caddy 2>/dev/null || caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || true'], 60);
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function composeUp(string $slug, string $dir): array
    {
        return $this->run([
            'docker', 'compose',
            '-p', $slug,
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d',
        ], 900);
    }

    protected function waitForHealthy(string $slug, int $attempts = 12): bool
    {
        $url = 'http://'.$slug.'-frontend-1:3000/up';
        for ($i = 0; $i < $attempts; $i++) {
            $viaCurl = $this->run([
                'docker', 'run', '--rm',
                '--network', 'webino_sites',
                'curlimages/curl:8.5.0',
                '-sf', $url,
            ], 30);
            if ($viaCurl['exit_code'] === 0) {
                return true;
            }

            $web = $this->findErpWebContainer();
            if ($web !== null) {
                $viaExec = $this->run([
                    'docker', 'exec', $web,
                    'wget', '-q', '-O', '-', $url,
                ], 30);
                if ($viaExec['exit_code'] === 0) {
                    return true;
                }
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

    protected function updateResourceStatus(WebinoSiteProvision $provision, string $status): void
    {
        PlatformResource::query()
            ->where('provision_id', $provision->id)
            ->update(['status' => $status]);
    }

    protected function findErpWebContainer(): ?string
    {
        $byImage = $this->run([
            'docker', 'ps',
            '--filter', 'ancestor=caddy:2-alpine',
            '--format', '{{.Names}}',
        ], 15);
        $names = preg_split('/\r?\n/', trim($byImage['stdout'])) ?: [];
        foreach ($names as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        $byName = $this->run([
            'docker', 'ps',
            '--format', '{{.Names}}',
        ], 15);
        foreach (preg_split('/\r?\n/', trim($byName['stdout'])) ?: [] as $name) {
            if ($name !== '' && (str_ends_with($name, '-web-1') || str_contains($name, '_web_') || $name === 'web')) {
                return $name;
            }
        }

        return null;
    }

    protected function erpRoot(): string
    {
        // Laravel base_path is WebinoERP/backend
        return dirname(base_path());
    }

    protected function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $r = $this->run(['mkdir', '-p', $dir], 30);
            if ($r['exit_code'] !== 0 || ! is_dir($dir)) {
                throw new RuntimeException('platform.site_dir_create_failed: '.$dir);
            }
        }
    }

    protected function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        $this->ensureDir($dir);
        if (@file_put_contents($path, $contents) === false) {
            $b64 = base64_encode($contents);
            $r = $this->run([
                'sh', '-c',
                'echo '.escapeshellarg($b64).' | base64 -d > '.escapeshellarg($path),
            ], 30);
            if ($r['exit_code'] !== 0) {
                throw new RuntimeException('platform.write_file_failed: '.$path);
            }
        }
    }

    /**
     * @param  list<string>  $command
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function run(array $command, int $timeout = 120): array
    {
        return $this->runEnv($command, $timeout, []);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $extraEnv
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function runEnv(array $command, int $timeout = 120, array $extraEnv = []): array
    {
        $env = null;
        if ($extraEnv !== []) {
            $inherited = getenv() ?: [];
            if (! is_array($inherited)) {
                $inherited = [];
            }
            $env = array_merge($inherited, $extraEnv);
        }

        $process = new Process($command, null, $env);
        $process->setTimeout($timeout);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
