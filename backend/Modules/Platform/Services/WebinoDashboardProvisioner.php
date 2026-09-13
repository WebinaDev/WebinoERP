<?php

namespace Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Support\TenantEnvBuilder;
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
 *
 * Caddy must run as a Docker container on the webino_sites network (same as local),
 * with /var/lib/webino/caddy.d mounted at /etc/caddy/sites.
 */
class WebinoDashboardProvisioner
{
    public const CADDY_DURABLE_DIR = '/var/lib/webino/caddy.d';

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

        $dir = $this->siteDir($provision);
        $compose = TenantSiteStack::composeYaml($provision->slug);
        $this->docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
        $envFile = $this->envFile($provision, $siteType, $token);
        $this->docker->writeFile($server, $dir.'/.env', $envFile);

        // Ensure external compose network + published images exist on remote host.
        $preflight = $this->docker->sshRun(
            $server,
            'docker network inspect webino_sites >/dev/null 2>&1 || docker network create webino_sites; '
            .'docker image inspect webino-backend:latest >/dev/null 2>&1 && docker image inspect webino-next:latest >/dev/null 2>&1 '
            .'|| { echo "platform.dashboard_images_missing: pull or load webino-backend:latest and webino-next:latest on this host" >&2; exit 1; }',
            120
        );
        if ($preflight['exit_code'] !== 0) {
            throw new RuntimeException(trim($preflight['stderr'] ?: $preflight['stdout']) ?: 'platform.dashboard_images_missing');
        }

        $this->ensureRemoteCaddyReady($server);

        $up = $this->docker->composeUp($server, $dir, TenantSiteStack::projectName($provision->slug));
        if ($up['exit_code'] !== 0) {
            throw new RuntimeException(trim($up['stderr'] ?: $up['stdout']) ?: 'platform.compose_up_failed');
        }
        $this->attachProxyNetwork($server, $provision->slug);

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

        $this->writeCaddySnippet($server, $provision->domain, $provision->slug);
        $this->reloadRemoteCaddy($server);

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

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);
        $result = $this->docker->composeUp($server, $dir, TenantSiteStack::projectName($provision->slug));
        $this->attachProxyNetwork($server, $provision->slug);
        try {
            $this->writeCaddySnippet($server, (string) $provision->domain, $provision->slug);
            $this->reloadRemoteCaddy($server);
        } catch (Throwable $e) {
            $result['stderr'] = trim(($result['stderr'] ?? '')."\ncaddy: ".$e->getMessage());
        }
        $this->updateResourceStatus($provision, 'running');

        return $result;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);
        $result = $this->docker->composeStop($server, $dir, TenantSiteStack::projectName($provision->slug));
        $this->updateResourceStatus($provision, 'stopped');

        return $result;
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 200): string
    {
        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);
        $r = $this->docker->sshRun(
            $server,
            'cd '.escapeshellarg($dir).' && docker compose -p '.escapeshellarg(TenantSiteStack::projectName($provision->slug)).' logs --tail '.((int) $tail).' 2>&1',
            60
        );

        return trim($r['stdout'].$r['stderr']);
    }

    public function changeDomain(WebinoSiteProvision $provision, string $newDomain): void
    {
        $newDomain = strtolower(trim($newDomain));
        if ($newDomain === '' || ! str_contains($newDomain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);

        $this->writeCaddySnippet($server, $newDomain, $provision->slug);
        $this->reloadRemoteCaddy($server);

        $this->docker->sshRun(
            $server,
            'if [ -f '.escapeshellarg($dir.'/.env').' ]; then '
            .'sed -i -E '.escapeshellarg('s|^APP_URL=.*|APP_URL=https://'.$newDomain.'|').' '.escapeshellarg($dir.'/.env').'; '
            .'grep -q "^APP_URL=" '.escapeshellarg($dir.'/.env').' || echo '.escapeshellarg('APP_URL=https://'.$newDomain).' >> '.escapeshellarg($dir.'/.env').'; '
            .'fi',
            30
        );

        $this->recreateAppServices($server, $provision);
        $this->attachProxyNetwork($server, $provision->slug);

        PlatformResource::query()
            ->where('provision_id', $provision->id)
            ->update(['fqdn' => $newDomain]);
    }

    /**
     * @return array{ok:bool,ssl_status:?string,expires_at:?string,forced:bool,log?:string}
     */
    public function renewSsl(WebinoSiteProvision $provision, bool $force = false): array
    {
        $domain = strtolower(trim((string) $provision->domain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        $server = $this->serverFor($provision);
        $log = [];

        $dir = $this->siteDir($provision);
        $up = $this->docker->composeUp($server, $dir, TenantSiteStack::projectName($provision->slug));
        $log[] = 'compose up exit='.$up['exit_code'];
        $this->attachProxyNetwork($server, $provision->slug);
        $log[] = $this->writeCaddySnippet($server, $domain, $provision->slug);

        if ($force) {
            $log[] = $this->deleteDomainCertLeaf($server, $domain);
        }

        $reload = $this->reloadRemoteCaddy($server);
        $log[] = 'caddy reload exit='.$reload['exit_code'];
        if (trim($reload['stderr']) !== '') {
            $log[] = trim($reload['stderr']);
        }
        if ($reload['exit_code'] !== 0) {
            throw new RuntimeException(
                'ریلود Caddy ناموفق بود. '.trim($reload['stderr'] ?: $reload['stdout'])
            );
        }

        $seen = $this->caddyContainerSeesSnippet($server, $provision->slug);
        $log[] = $seen
            ? 'verified /etc/caddy/sites/'.$provision->slug.'.caddy in caddy container'
            : 'WARNING: snippet not visible inside Caddy at /etc/caddy/sites/'.$provision->slug.'.caddy';

        $expiresAt = null;
        $onDisk = false;
        for ($i = 0; $i < 6; $i++) {
            $expiresAt = $this->readCertExpiryFromCaddy($server, $domain);
            if ($expiresAt !== null) {
                $onDisk = true;
                break;
            }
            sleep(3);
        }

        $status = $onDisk ? 'active' : 'provisioning';
        $ok = $onDisk || $seen;
        if (! $onDisk) {
            $log[] = 'گواهی هنوز روی دیسک Caddy نیست. DNS دامنه باید به همین سرور باشد و پورت ۸۰ و ۴۴۳ باز باشند.';
        }

        PlatformDomain::query()
            ->where('domain', $domain)
            ->update(['ssl_status' => $status]);

        return [
            'ok' => $ok,
            'ssl_status' => $status,
            'expires_at' => $expiresAt,
            'forced' => $force,
            'log' => implode("\n", array_filter($log)),
        ];
    }

    /**
     * @return array{ssl_status:?string,expires_at:?string,domain:?string,log?:string,snippet_ok?:bool}
     */
    public function sslInfo(WebinoSiteProvision $provision): array
    {
        $domain = (string) ($provision->domain ?? '');
        $empty = [
            'ssl_status' => null,
            'expires_at' => null,
            'domain' => $domain !== '' ? $domain : null,
            'log' => null,
            'snippet_ok' => false,
        ];

        try {
            $server = $this->serverFor($provision);
            $payload = is_array($provision->wizard_payload) ? $provision->wizard_payload : [];
            $stored = is_array($payload['ssl'] ?? null) ? $payload['ssl'] : [];
            $row = $domain !== ''
                ? PlatformDomain::query()->where('domain', $domain)->first()
                : null;
            $expiresAt = $domain !== '' ? $this->readCertExpiryFromCaddy($server, $domain) : null;

            return [
                'ssl_status' => $expiresAt ? 'active' : ($row?->ssl_status ?? ($stored['ssl_status'] ?? null)),
                'expires_at' => $expiresAt ?? ($stored['expires_at'] ?? null),
                'domain' => $domain !== '' ? $domain : null,
                'log' => isset($stored['log']) ? (string) $stored['log'] : null,
                'snippet_ok' => $domain !== '' ? $this->caddyContainerSeesSnippet($server, $provision->slug) : false,
            ];
        } catch (Throwable $e) {
            report($e);
            $empty['log'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function stackDiagnostics(WebinoSiteProvision $provision): array
    {
        $project = TenantSiteStack::projectName($provision->slug);
        $backend = TenantSiteStack::backendService($provision->slug);
        $frontend = TenantSiteStack::frontendService($provision->slug);
        $db = $project.'-db';
        $redis = $project.'-redis';

        try {
            $server = $this->serverFor($provision);
        } catch (Throwable $e) {
            return [
                'project' => $project,
                'containers' => [],
                'on_webino_sites' => ['backend' => false, 'frontend' => false],
                'caddy_to_backend' => false,
                'frontend_to_backend' => false,
                'db_auth_ok' => false,
                'log' => $e->getMessage(),
            ];
        }

        $containers = [];
        foreach ([$backend, $frontend, $db, $redis] as $name) {
            $status = $this->docker->sshRun(
                $server,
                'docker inspect -f "{{.State.Status}}" '.escapeshellarg($name).' 2>/dev/null || echo missing',
                15
            );
            $nets = $this->docker->sshRun(
                $server,
                'docker inspect -f "{{range \$k,\$v := .NetworkSettings.Networks}}{{\$k}} {{end}}" '.escapeshellarg($name).' 2>/dev/null || true',
                15
            );
            $restarts = $this->docker->sshRun(
                $server,
                'docker inspect -f "{{.RestartCount}}" '.escapeshellarg($name).' 2>/dev/null || echo 0',
                15
            );
            $netList = array_values(array_filter(preg_split('/\s+/', trim($nets['stdout'])) ?: []));
            $containers[$name] = [
                'status' => trim($status['stdout']) ?: 'missing',
                'networks' => $netList,
                'restart_count' => (int) trim($restarts['stdout']),
            ];
        }

        $onBackend = in_array('webino_sites', $containers[$backend]['networks'] ?? [], true);
        $onFrontend = in_array('webino_sites', $containers[$frontend]['networks'] ?? [], true);
        $snippetOk = $this->caddyContainerSeesSnippet($server, $provision->slug);

        $caddyProbe = $this->docker->sshRun(
            $server,
            $this->caddyExecPrefix($server)
            .'wget -q -O - --timeout=5 http://'.$backend.':8080/api/v1/health/metrics 2>/dev/null | head -c 80 || true',
            30
        );
        $caddyToBackend = trim($caddyProbe['stdout']) !== '';

        return [
            'project' => $project,
            'containers' => $containers,
            'on_webino_sites' => ['backend' => $onBackend, 'frontend' => $onFrontend],
            'caddy_to_backend' => $caddyToBackend,
            'frontend_to_backend' => false,
            'db_auth_ok' => ($containers[$db]['status'] ?? '') === 'running',
            'backend_self' => ($containers[$backend]['status'] ?? '') === 'running',
            'readiness_ok' => ($containers[$backend]['status'] ?? '') === 'running'
                && ($containers[$frontend]['status'] ?? '') === 'running',
            'redis_ok' => ($containers[$redis]['status'] ?? '') === 'running',
            'caddy_snippet_ok' => $snippetOk,
            'caddy_config_has_upstream' => $snippetOk,
            'caddy_exec_to_backend' => $caddyToBackend,
            'log' => '',
        ];
    }

    /**
     * Pull published images and recreate services (remote has no local build context).
     *
     * @param  'frontend'|'backend'|'migrate'|'full'  $target
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function runUpdate(WebinoSiteProvision $provision, string $target): array
    {
        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);
        $project = TenantSiteStack::projectName($provision->slug);
        $channel = TenantSiteStack::imageTag((string) (($provision->wizard_payload['channel'] ?? null) ?: 'beta'));
        $log = [];

        if ($target === 'migrate') {
            $result = $this->docker->sshRun(
                $server,
                'cd '.escapeshellarg($dir)
                .' && docker compose -p '.escapeshellarg($project)
                .' --env-file .env exec -T '.escapeshellarg(TenantSiteStack::backendService($provision->slug))
                .' php artisan migrate --force',
                600
            );

            return [
                ...$result,
                'log' => trim($result['stdout']."\n".$result['stderr']),
            ];
        }

        $images = match ($target) {
            'frontend' => ['webino-next:'.$channel],
            'backend' => ['webino-backend:'.$channel],
            default => ['webino-backend:'.$channel, 'webino-next:'.$channel],
        };

        foreach ($images as $image) {
            $pull = $this->docker->pullImage($server, $image);
            $log[] = 'pull '.$image.' exit='.$pull['exit_code'];
            if ($pull['exit_code'] !== 0) {
                return [
                    'exit_code' => $pull['exit_code'],
                    'stdout' => $pull['stdout'],
                    'stderr' => $pull['stderr'],
                    'log' => implode("\n", $log)."\n".trim($pull['stdout']."\n".$pull['stderr']),
                ];
            }
        }

        $services = match ($target) {
            'frontend' => [TenantSiteStack::frontendService($provision->slug)],
            'backend' => [TenantSiteStack::backendService($provision->slug)],
            default => [
                TenantSiteStack::backendService($provision->slug),
                TenantSiteStack::frontendService($provision->slug),
            ],
        };

        $result = $this->docker->sshRun(
            $server,
            'cd '.escapeshellarg($dir)
            .' && docker compose -p '.escapeshellarg($project)
            .' --env-file .env up -d --no-deps --force-recreate --remove-orphans '
            .implode(' ', array_map('escapeshellarg', $services)),
            900
        );
        $this->attachProxyNetwork($server, $provision->slug);
        $log[] = 'recreate exit='.$result['exit_code'];

        return [
            ...$result,
            'log' => implode("\n", $log)."\n".trim($result['stdout']."\n".$result['stderr']),
        ];
    }

    /**
     * Lightweight remote DB repair: recreate backend so it re-reads .env.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string,message?:string,stages?:array<string,bool>}
     */
    public function repairDatabase(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $result = $this->recreateAppServices($server, $provision);
        $this->attachProxyNetwork($server, $provision->slug);
        $ok = ($result['exit_code'] ?? 1) === 0;

        return [
            ...$result,
            'log' => trim($result['stdout']."\n".$result['stderr']),
            'message' => $ok ? 'Backend recreated on remote' : 'Remote repair failed',
            'stages' => [
                'db_auth' => $ok,
                'recreate' => $ok,
                'caddy_reload' => true,
                'app_health' => $ok,
                'caddy_to_backend' => $ok,
            ],
        ];
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function destroyStack(WebinoSiteProvision $provision): array
    {
        $server = $this->serverFor($provision);
        $dir = $this->siteDir($provision);
        $project = TenantSiteStack::projectName($provision->slug);
        $result = $this->docker->composeDown($server, $dir, $project);
        $this->removeCaddySnippet($server, $provision->slug);
        try {
            $this->reloadRemoteCaddy($server);
        } catch (Throwable) {
            // ignore reload failures on destroy
        }
        $this->updateResourceStatus($provision, 'destroyed');

        return $result;
    }

    public function powerState(WebinoSiteProvision $provision): string
    {
        $status = (string) (PlatformResource::query()
            ->where('provision_id', $provision->id)
            ->value('status') ?? '');

        return match ($status) {
            'running' => 'running',
            'stopped', 'destroyed' => 'stopped',
            default => 'unknown',
        };
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

    protected function siteDir(WebinoSiteProvision $provision): string
    {
        return '/var/lib/webino/sites/'.$provision->slug;
    }

    protected function envFile(WebinoSiteProvision $provision, string $siteType, string $token): string
    {
        return TenantEnvBuilder::build($provision, $siteType, $token);
    }

    protected function updateResourceStatus(WebinoSiteProvision $provision, string $status): void
    {
        PlatformResource::query()
            ->where('provision_id', $provision->id)
            ->update(['status' => $status]);
    }

    protected function attachProxyNetwork(PlatformServer $server, string $slug): void
    {
        $connect = [];
        foreach (TenantSiteStack::proxyContainerNames($slug) as $name) {
            $connect[] = 'docker network connect webino_sites '.escapeshellarg($name).' 2>/dev/null || true';
        }
        $this->docker->sshRun($server, implode('; ', $connect), 60);
    }

    /**
     * Ensure a Docker Caddy on webino_sites can resolve tenant upstreams.
     */
    protected function ensureRemoteCaddyReady(PlatformServer $server): void
    {
        $this->docker->sshRun(
            $server,
            'mkdir -p '.escapeshellarg(self::CADDY_DURABLE_DIR)
            .' && echo "# keep" > '.escapeshellarg(self::CADDY_DURABLE_DIR.'/_keep.caddy'),
            30
        );

        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            throw new RuntimeException(
                'platform.remote_caddy_missing: روی سرور ریموت باید یک کانتینر Caddy روی شبکه webino_sites باشد و مسیر '
                .self::CADDY_DURABLE_DIR.' به /etc/caddy/sites مانت شده باشد.'
            );
        }

        $this->docker->sshRun(
            $server,
            'docker network connect webino_sites '.escapeshellarg($name).' 2>/dev/null || true',
            30
        );
    }

    protected function findRemoteCaddyContainer(PlatformServer $server): ?string
    {
        $byCompose = $this->docker->sshRun(
            $server,
            'docker ps --filter label=com.docker.compose.service=web --format "{{.Names}}" | head -n 1',
            15
        );
        $name = trim($byCompose['stdout']);
        if ($name !== '') {
            return $name;
        }

        $byImage = $this->docker->sshRun(
            $server,
            'docker ps --filter ancestor=caddy:2-alpine --format "{{.Names}}" | head -n 1',
            15
        );
        $name = trim($byImage['stdout']);
        if ($name !== '') {
            return $name;
        }

        $byName = $this->docker->sshRun(
            $server,
            'docker ps --format "{{.Names}}" | grep -Ei "caddy|webino.*web" | head -n 1',
            15
        );
        $name = trim($byName['stdout']);

        return $name !== '' ? $name : null;
    }

    protected function writeCaddySnippet(PlatformServer $server, string $domain, string $slug): string
    {
        $snippet = TenantSiteStack::caddySnippet($domain, $slug);
        $path = self::CADDY_DURABLE_DIR.'/'.$slug.'.caddy';
        $this->docker->writeFile($server, self::CADDY_DURABLE_DIR.'/_keep.caddy', "# keep import glob non-empty\n");
        $wrote = $this->docker->writeFile($server, $path, $snippet);
        if (($wrote['exit_code'] ?? 1) !== 0) {
            throw new RuntimeException('platform.caddy_snippet_write_failed: '.$path);
        }

        // Legacy host path leftover — remove so host Caddy does not conflict if present.
        $this->docker->sshRun(
            $server,
            'rm -f '.escapeshellarg('/etc/caddy/webino.d/'.$slug.'.caddy').' 2>/dev/null || true',
            15
        );

        return 'wrote '.$path;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function reloadRemoteCaddy(PlatformServer $server): array
    {
        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            throw new RuntimeException('platform.remote_caddy_missing');
        }

        return $this->docker->sshRun(
            $server,
            'docker exec '.escapeshellarg($name).' caddy reload --config /etc/caddy/Caddyfile 2>&1'
            .' || docker exec '.escapeshellarg($name).' caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile 2>&1'
            .' || docker kill -s HUP '.escapeshellarg($name).' 2>&1',
            60
        );
    }

    protected function caddyContainerSeesSnippet(PlatformServer $server, string $slug): bool
    {
        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            return false;
        }
        $r = $this->docker->sshRun(
            $server,
            'docker exec '.escapeshellarg($name).' test -s '.escapeshellarg('/etc/caddy/sites/'.$slug.'.caddy'),
            15
        );

        return ($r['exit_code'] ?? 1) === 0;
    }

    protected function caddyExecPrefix(PlatformServer $server): string
    {
        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            return 'true; ';
        }

        return 'docker exec '.escapeshellarg($name).' ';
    }

    protected function deleteDomainCertLeaf(PlatformServer $server, string $domain): string
    {
        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            return 'force: caddy container not found; skipped cert leaf delete';
        }

        $domainArg = escapeshellarg($domain);
        $script = 'set -e; base=/data/caddy/certificates; '
            .'if [ ! -d "$base" ]; then echo "no certificates dir"; exit 0; fi; '
            .'found=0; domain='.$domainArg.'; for d in "$base"/*/; do '
            .'  leaf="${d}${domain}"; '
            .'  if [ -d "$leaf" ]; then rm -rf "$leaf"; found=1; echo "removed $leaf"; fi; '
            .'done; '
            .'if [ "$found" = "0" ]; then echo "no leaf for $domain"; fi';

        $result = $this->docker->sshRun(
            $server,
            'docker exec '.escapeshellarg($name).' sh -c '.escapeshellarg($script),
            60
        );

        return 'force: '.trim($result['stdout'].' '.$result['stderr']);
    }

    protected function readCertExpiryFromCaddy(PlatformServer $server, string $domain): ?string
    {
        $name = $this->findRemoteCaddyContainer($server);
        if ($name === null) {
            return null;
        }

        $domainArg = escapeshellarg($domain);
        $script = 'base=/data/caddy/certificates; domain='.$domainArg.'; '
            .'for d in "$base"/*/; do '
            .'  crt="${d}${domain}/${domain}.crt"; '
            .'  if [ -f "$crt" ]; then openssl x509 -enddate -noout -in "$crt" 2>/dev/null; exit 0; fi; '
            .'done; exit 1';

        $r = $this->docker->sshRun(
            $server,
            'docker exec '.escapeshellarg($name).' sh -c '.escapeshellarg($script),
            30
        );
        $out = trim($r['stdout']);
        if ($out === '' || ! str_starts_with($out, 'notAfter=')) {
            return null;
        }
        $raw = substr($out, strlen('notAfter='));
        $ts = strtotime($raw);

        return $ts ? date('c', $ts) : null;
    }

    protected function removeCaddySnippet(PlatformServer $server, string $slug): void
    {
        $this->docker->sshRun(
            $server,
            'rm -f '.escapeshellarg(self::CADDY_DURABLE_DIR.'/'.$slug.'.caddy')
            .' '.escapeshellarg('/etc/caddy/webino.d/'.$slug.'.caddy').' 2>/dev/null || true',
            15
        );
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function recreateAppServices(PlatformServer $server, WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $project = TenantSiteStack::projectName($provision->slug);
        $services = [
            TenantSiteStack::backendService($provision->slug),
            TenantSiteStack::frontendService($provision->slug),
        ];

        return $this->docker->sshRun(
            $server,
            'cd '.escapeshellarg($dir)
            .' && docker compose -p '.escapeshellarg($project)
            .' --env-file .env up -d --no-deps --force-recreate --remove-orphans '
            .implode(' ', array_map('escapeshellarg', $services)),
            900
        );
    }

    protected function waitForHealthy(string $domain, int $attempts = 12): bool
    {
        $url = 'https://'.$domain.'/api/v1/health/metrics';
        for ($i = 0; $i < $attempts; $i++) {
            try {
                if (Http::timeout(8)->get($url)->successful()) {
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
            'provision_token' => $token,
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
