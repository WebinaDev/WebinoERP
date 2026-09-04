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
use Modules\SiteBuilder\Support\ProvisionProgress;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Provisions an isolated WebinoDashboard stack on the same VPS as WebinoERP (no SSH).
 *
 * Source: git clone https://github.com/Webinadev/WebinoDashboard into /var/lib/webino/src
 * (path shared with the host Docker daemon). Each site is a separate compose project.
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

        ProvisionProgress::assertNotCancelled($provision);

        $token = $provision->provision_token ?: Str::random(48);
        $provision->provision_token = $token;
        $provision->status = WebinoSiteProvision::STATUS_PROVISIONING;
        $provision->launched_at = now();
        $provision->save();

        $imagesCached = $this->imagesPresent();
        ProvisionProgress::report($provision, ProvisionProgress::PHASE_QUEUED, $imagesCached);

        $this->ensureDockerNetwork();
        ProvisionProgress::assertNotCancelled($provision);

        if (! $imagesCached) {
            ProvisionProgress::report($provision, ProvisionProgress::PHASE_FETCH_SOURCE, false);
        }
        ProvisionProgress::report(
            $provision,
            $imagesCached ? ProvisionProgress::PHASE_WRITE_STACK : ProvisionProgress::PHASE_BUILD_IMAGES,
            $imagesCached,
        );
        $this->ensureImages();
        $imagesCached = true;
        ProvisionProgress::assertNotCancelled($provision);

        ProvisionProgress::report($provision, ProvisionProgress::PHASE_WRITE_STACK, $imagesCached);
        $dir = $this->siteDir($provision);
        $this->ensureDir($dir);

        $channel = (string) (($payload['channel'] ?? null) ?: 'latest');
        $compose = TenantSiteStack::composeYaml($provision->slug, $channel);
        $envFile = $this->envFile($provision, $siteType, $token);
        $this->writeFile($dir.'/docker-compose.yml', $compose);
        $this->writeFile($dir.'/.env', $envFile);

        $this->writeCaddySnippet($provision->domain, $provision->slug);
        $this->reloadCaddy();
        ProvisionProgress::assertNotCancelled($provision);

        ProvisionProgress::report($provision, ProvisionProgress::PHASE_COMPOSE_UP, $imagesCached);
        $up = $this->composeUp($provision->slug, $dir);
        if ($up['exit_code'] !== 0) {
            throw new RuntimeException(trim($up['stderr'] ?: $up['stdout']) ?: 'platform.compose_up_failed');
        }
        // Existing volumes keep the first-init password; align role with .env then recreate backend.
        $dbLog = $this->syncDatabasePassword($provision);
        $recreate = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d', '--no-deps', '--force-recreate', '--remove-orphans',
            TenantSiteStack::backendService($provision->slug),
            TenantSiteStack::frontendService($provision->slug),
        ], 900);
        if ($recreate['exit_code'] !== 0) {
            throw new RuntimeException(
                trim($recreate['stderr'] ?: $recreate['stdout']) ?: 'platform.compose_recreate_failed'
            );
        }
        $up['stdout'] .= "\n".$dbLog."\n".$recreate['stdout'];
        $up['stderr'] .= "\n".$recreate['stderr'];
        $this->attachToProxyNetwork($provision->slug);
        ProvisionProgress::assertNotCancelled($provision);

        $resource = PlatformResource::query()->updateOrCreate(
            ['provision_id' => $provision->id],
            [
                'environment_id' => $this->ensureDefaultEnvironment($provision)->id,
                'server_id' => $server->id,
                'type' => 'webino_dashboard',
                'name' => $provision->slug,
                'status' => 'running',
                'fqdn' => $provision->domain,
                'build_pack' => 'compose',
                'git_repository' => (string) env('WEBINO_DASHBOARD_GIT_URL', 'https://github.com/Webinadev/WebinoDashboard.git'),
                'git_branch' => (string) env('WEBINO_DASHBOARD_GIT_REF', 'main'),
                'site_type_slug' => $siteType,
                'license_id' => $provision->license_id,
                'crm_account_id' => $provision->crm_account_id,
                'docker_compose_raw' => $compose,
                'settings' => [
                    'site_dir' => $dir,
                    'compose_project' => TenantSiteStack::projectName($provision->slug),
                    'provision_mode' => 'local_same_vps',
                ],
            ],
        );

        PlatformDomain::query()->firstOrCreate(
            ['resource_id' => $resource->id, 'domain' => $provision->domain],
            ['ssl_status' => 'pending'],
        );

        PlatformDeployment::query()->create([
            'resource_id' => $resource->id,
            'status' => 'success',
            'logs' => $up['stdout']."\n".$up['stderr'],
            'triggered_by' => $provision->created_by,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        try {
            ProvisionProgress::report($provision, ProvisionProgress::PHASE_HEALTH, $imagesCached);
            if ($this->waitForHealthy($provision->slug)) {
                ProvisionProgress::assertNotCancelled($provision);
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_BOOTSTRAP, $imagesCached);
                $this->bootstrapRemote($provision, $siteType, $token);
                $provision->status = WebinoSiteProvision::STATUS_READY;
                $provision->ready_at = now();
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_DONE, $imagesCached);
            } else {
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_SSL, $imagesCached);
                $provision->status = WebinoSiteProvision::STATUS_SSL_PENDING;
            }
            $provision->error_log = null;
            $provision->save();
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'platform.provision_cancelled')) {
                throw $e;
            }
            ProvisionProgress::report($provision, ProvisionProgress::PHASE_SSL, $imagesCached);
            $provision->status = WebinoSiteProvision::STATUS_SSL_PENDING;
            $provision->error_log = $e->getMessage();
            $provision->save();
        }

        return $resource;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string,attach_log?:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        $this->ensureDockerNetwork();
        $this->rewriteComposeImages($provision, $this->effectiveTag($provision));
        $this->rewriteEnvFile($provision);
        $legacyLog = $this->removeLegacyServiceContainers($provision->slug);
        $dir = $this->siteDir($provision);
        $result = $this->composeUp($provision->slug, $dir);
        // Align Postgres role password with .env before recreate so migrate can succeed.
        $dbLog = $this->syncDatabasePassword($provision);
        // Ensure backend/frontend pick up new .env mount + network after rewrite.
        $recreate = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d', '--no-deps', '--force-recreate', '--remove-orphans',
            TenantSiteStack::backendService($provision->slug),
            TenantSiteStack::frontendService($provision->slug),
        ], 900);
        if ($recreate['exit_code'] !== 0 && $result['exit_code'] === 0) {
            $result = $recreate;
        } else {
            $result['stdout'] .= "\n".$recreate['stdout'];
            $result['stderr'] .= "\n".$recreate['stderr'];
        }
        $attachLog = $this->attachToProxyNetwork($provision->slug);
        $caddyLog = '';
        try {
            $caddyLog = $this->ensureCaddySnippet($provision);
            $this->reloadCaddy();
            $caddyLog .= "\ncaddy reload requested";
        } catch (Throwable $e) {
            $caddyLog = 'caddy: '.$e->getMessage();
        }
        $this->updateResourceStatus($provision, 'running');

        return [
            ...$result,
            'attach_log' => trim(implode("\n", array_filter([$legacyLog, $dbLog, $attachLog, $caddyLog]))),
        ];
    }

    /**
     * Rewrite compose (unique service names on webino_sites), bring stack up,
     * and refresh Caddy snippet (/api → backend, pages → frontend).
     * Used by ops resync after ERP deploy so existing sites pick up network fixes.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,attach_log:string,log:string}
     */
    public function resyncStack(WebinoSiteProvision $provision): array
    {
        $this->ensureDockerNetwork();
        $this->rewriteComposeImages($provision, $this->effectiveTag($provision));
        $this->rewriteEnvFile($provision);
        $legacyLog = $this->removeLegacyServiceContainers($provision->slug);
        $dir = $this->siteDir($provision);
        if (! is_file($dir.'/docker-compose.yml')) {
            throw new RuntimeException('platform.site_dir_missing: '.$dir);
        }
        $result = $this->composeUp($provision->slug, $dir);
        $dbLog = $this->syncDatabasePassword($provision);
        $recreate = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d', '--no-deps', '--force-recreate', '--remove-orphans',
            TenantSiteStack::backendService($provision->slug),
            TenantSiteStack::frontendService($provision->slug),
        ], 900);
        if ($recreate['exit_code'] !== 0 && $result['exit_code'] === 0) {
            $result = $recreate;
        } else {
            $result['stdout'] .= "\n".$recreate['stdout'];
            $result['stderr'] .= "\n".$recreate['stderr'];
        }
        $attachLog = $this->attachToProxyNetwork($provision->slug);
        $caddyLog = '';
        try {
            $caddyLog = $this->ensureCaddySnippet($provision);
            $this->reloadCaddy();
            $caddyLog .= "\ncaddy reload requested";
        } catch (Throwable $e) {
            $caddyLog = 'caddy: '.$e->getMessage();
        }
        if ($result['exit_code'] === 0) {
            $this->updateResourceStatus($provision, 'running');
        }

        $combined = trim(implode("\n", array_filter([$legacyLog, $dbLog, $attachLog, $caddyLog])));

        return [
            ...$result,
            'attach_log' => $combined,
            'log' => trim($result['stdout']."\n".$result['stderr']."\n".$combined),
        ];
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $result = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            'stop',
        ], 300);
        $this->updateResourceStatus($provision, 'stopped');

        return $result;
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 80): string
    {
        $dir = $this->siteDir($provision);
        $result = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            'logs',
            '--tail', (string) $tail,
        ], 60);

        return $this->compressRepeatedLogLines(trim($result['stdout']."\n".$result['stderr']));
    }

    /**
     * Collapse consecutive identical log lines: "msg" then "msg (×N)".
     */
    protected function compressRepeatedLogLines(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\r?\n/', $text) ?: [];
        $out = [];
        $prev = null;
        $count = 0;
        $flush = function () use (&$out, &$prev, &$count): void {
            if ($prev === null) {
                return;
            }
            $out[] = $count > 1 ? $prev.' (×'.$count.')' : $prev;
        };

        foreach ($lines as $line) {
            if ($line === $prev) {
                $count++;
                continue;
            }
            $flush();
            $prev = $line;
            $count = 1;
        }
        $flush();

        return implode("\n", $out);
    }

    /**
     * Rebuild frontend image from git and recreate only the frontend service.
     * Never rewrites Caddy / Let's Encrypt.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function updateFrontend(WebinoSiteProvision $provision): array
    {
        $channel = $this->channelOf($provision);
        $this->forceBuildImages($channel, only: 'frontend');
        $this->rewriteComposeImages($provision, $channel);

        return $this->recreateServices($provision, ['frontend']);
    }

    /**
     * Rebuild backend image from git and recreate backend (+ worker/scheduler if present).
     * Never rewrites Caddy / Let's Encrypt. Does not run migrations.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function updateBackend(WebinoSiteProvision $provision): array
    {
        $channel = $this->channelOf($provision);
        $this->forceBuildImages($channel, only: 'backend');
        $this->rewriteComposeImages($provision, $channel);

        return $this->recreateServices($provision, ['backend']);
    }

    /**
     * Run artisan migrate inside the tenant backend container. No image rebuild, no Caddy.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function migrate(WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $result = $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'exec', '-T', TenantSiteStack::backendService($provision->slug),
            'php', 'artisan', 'migrate', '--force',
        ], 600);

        return [
            ...$result,
            'log' => trim($result['stdout']."\n".$result['stderr']),
        ];
    }

    /**
     * Full app update: rebuild frontend+backend and recreate both. Skips Caddy and migrate.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function updateApp(WebinoSiteProvision $provision): array
    {
        $channel = $this->channelOf($provision);
        $this->forceBuildImages($channel, only: 'all');
        $this->rewriteComposeImages($provision, $channel);

        return $this->recreateServices($provision, ['backend', 'frontend']);
    }

    /**
     * Change public domain — the ONLY path that rewrites the Caddy snippet.
     * Does not delete caddy_data (Let's Encrypt storage stays intact).
     */
    public function changeDomain(WebinoSiteProvision $provision, string $newDomain): void
    {
        $newDomain = strtolower(trim($newDomain));
        if ($newDomain === '' || ! str_contains($newDomain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        $this->writeCaddySnippet($newDomain, $provision->slug);
        $this->reloadCaddy();

        $dir = $this->siteDir($provision);
        $envPath = $dir.'/.env';
        if (is_file($envPath)) {
            $map = $this->readEnvMap($envPath);
            $map['APP_URL'] = 'https://'.$newDomain;
            $lines = [];
            foreach ($map as $k => $v) {
                $lines[] = $this->envLine($k, $v);
            }
            $this->writeFile($envPath, implode("\n", $lines)."\n");
        }

        PlatformResource::query()
            ->where('provision_id', $provision->id)
            ->update(['fqdn' => $newDomain]);
    }

    /**
     * Reload Caddy for this site's domain. Optionally delete only that domain's
     * cert leaf under /data/caddy/certificates (never the whole caddy_data volume).
     *
     * Probe via the Caddy container cert store — not public HTTPS from PHP
     * (hairpin NAT makes get_headers fail even when Let's Encrypt succeeded).
     *
     * @return array{ok:bool,ssl_status:?string,expires_at:?string,forced:bool,log?:string}
     */
    public function renewSsl(WebinoSiteProvision $provision, bool $force = false): array
    {
        $domain = strtolower(trim((string) $provision->domain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        $log = [];

        // Bring stack up first — without containers, ACME may succeed but the site stays down.
        try {
            $dir = $this->siteDir($provision);
            if (is_dir($dir) && is_file($dir.'/docker-compose.yml')) {
                $up = $this->composeUp($provision->slug, $dir);
                $log[] = 'compose up exit='.$up['exit_code'];
                $attachLog = $this->attachToProxyNetwork($provision->slug);
                $log[] = $attachLog !== '' ? $attachLog : 'proxy network attach ok';
            } else {
                $log[] = 'site dir missing: '.$dir;
            }
        } catch (Throwable $e) {
            $log[] = 'compose/attach: '.$e->getMessage();
        }

        $write = $this->ensureCaddySnippet($provision);
        $log[] = $write;

        if ($force) {
            $log[] = $this->deleteDomainCertLeaf($domain);
        }

        $reload = $this->reloadCaddyResult();
        $log[] = 'caddy reload exit='.$reload['exit_code'];
        if (trim($reload['stderr']) !== '') {
            $log[] = trim($reload['stderr']);
        }
        if ($reload['exit_code'] !== 0) {
            throw new RuntimeException(
                'ریلود Caddy ناموفق بود. '.trim($reload['stderr'] ?: $reload['stdout'])
            );
        }

        $seen = $this->caddyContainerSeesSnippet($provision->slug);
        $log[] = $seen
            ? 'verified /etc/caddy/sites/'.$provision->slug.'.caddy in web container'
            : 'WARNING: snippet not visible inside Caddy container at /etc/caddy/sites/'.$provision->slug.'.caddy';

        $expiresAt = null;
        $onDisk = false;
        for ($i = 0; $i < 8; $i++) {
            $expiresAt = $this->readCertExpiryFromCaddy($domain);
            if ($expiresAt !== null) {
                $onDisk = true;
                break;
            }
            sleep(3);
        }

        $acmeLog = $this->caddyAcmeLogSnippet($domain);
        if ($acmeLog !== '') {
            $log[] = $acmeLog;
        }

        if ($onDisk) {
            $status = 'active';
            $ok = true;
        } else {
            $status = 'provisioning';
            // Still OK at API level if snippet is loaded — ACME can take longer / needs public 80/443.
            $ok = $seen;
            $log[] = 'گواهی هنوز روی دیسک Caddy نیست. DNS دامنه باید به همین سرور باشد و پورت ۸۰ و ۴۴۳ باز باشند (Lets Encrypt).';
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
     * @return array{ssl_status:?string,expires_at:?string,domain:?string,log?:string}
     */
    public function sslInfo(WebinoSiteProvision $provision): array
    {
        $empty = [
            'ssl_status' => null,
            'expires_at' => null,
            'domain' => $provision->domain,
            'log' => null,
        ];

        try {
            $payload = is_array($provision->wizard_payload) ? $provision->wizard_payload : [];
            $stored = is_array($payload['ssl'] ?? null) ? $payload['ssl'] : [];
            $domain = (string) ($provision->domain ?? '');

            $row = null;
            if ($domain !== '') {
                try {
                    $row = PlatformDomain::query()->where('domain', $domain)->first();
                } catch (Throwable) {
                    $row = null;
                }
            }

            $expiresAt = null;
            if ($domain !== '') {
                try {
                    $expiresAt = $this->readCertExpiryFromCaddy($domain);
                } catch (Throwable) {
                    $expiresAt = null;
                }
            }

            return [
                'ssl_status' => $expiresAt ? 'active' : ($row?->ssl_status ?? ($stored['ssl_status'] ?? null)),
                'expires_at' => $expiresAt ?? ($stored['expires_at'] ?? null),
                'domain' => $domain !== '' ? $domain : null,
                'log' => isset($stored['log']) ? (string) $stored['log'] : null,
                'snippet_ok' => $domain !== '' ? $this->caddyContainerSeesSnippet($provision->slug) : false,
            ];
        } catch (Throwable $e) {
            report($e);
            $empty['log'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * Diagnostics for control panel: container state, webino_sites attach, API reachability.
     *
     * @return array{
     *   project:string,
     *   containers:array<string, array{status:string,networks:list<string>,restart_count:int}>,
     *   on_webino_sites:array{backend:bool,frontend:bool},
     *   caddy_to_backend:bool,
     *   frontend_to_backend:bool,
     *   db_auth_ok:bool,
     *   backend_self:bool,
     *   caddy_snippet_ok:bool,
     *   caddy_config_has_upstream:bool,
     *   caddy_exec_to_backend:bool,
     *   env_pw_fp:string,
     *   backend_pw_fp:string,
     *   log:string
     * }
     */
    public function stackDiagnostics(WebinoSiteProvision $provision): array
    {
        $project = TenantSiteStack::projectName($provision->slug);
        $backend = TenantSiteStack::backendService($provision->slug);
        $frontend = TenantSiteStack::frontendService($provision->slug);
        $db = $project.'-db';

        $containers = [];
        $lines = [];
        foreach ([$backend, $frontend, $db] as $name) {
            $status = $this->run([
                'docker', 'inspect', '-f', '{{.State.Status}}', $name,
            ], 15);
            $nets = $this->run([
                'docker', 'inspect', '-f',
                '{{range $k, $v := .NetworkSettings.Networks}}{{$k}} {{end}}',
                $name,
            ], 15);
            $restarts = $this->run([
                'docker', 'inspect', '-f', '{{.RestartCount}}', $name,
            ], 15);
            $statusStr = $status['exit_code'] === 0 ? trim($status['stdout']) : 'missing';
            $netList = preg_split('/\s+/', trim($nets['stdout'] ?? '')) ?: [];
            $netList = array_values(array_filter($netList, fn ($n) => $n !== ''));
            $restartCount = ($restarts['exit_code'] === 0 && is_numeric(trim($restarts['stdout'])))
                ? (int) trim($restarts['stdout'])
                : -1;
            $containers[$name] = [
                'status' => $statusStr,
                'networks' => $netList,
                'restart_count' => $restartCount,
            ];
            $lines[] = $name.': '.$statusStr
                .' restarts='.($restartCount >= 0 ? (string) $restartCount : '?')
                .' nets=['.implode(',', $netList).']';
        }

        $onSites = [
            'backend' => in_array('webino_sites', $containers[$backend]['networks'] ?? [], true),
            'frontend' => in_array('webino_sites', $containers[$frontend]['networks'] ?? [], true),
        ];
        $lines[] = 'on_webino_sites backend='.($onSites['backend'] ? 'yes' : 'no')
            .' frontend='.($onSites['frontend'] ? 'yes' : 'no');

        $envPassword = $this->readEnvMap($this->siteDir($provision).'/.env')['DB_PASSWORD'] ?? '';
        $envPwFp = $this->passwordFingerprint($envPassword);
        $backendPw = $this->run(['docker', 'exec', $backend, 'printenv', 'DB_PASSWORD'], 15);
        $backendPwFp = 'unavailable';
        if ($backendPw['exit_code'] === 0) {
            $backendPwFp = $this->passwordFingerprint(rtrim($backendPw['stdout'], "\r\n"));
        }
        $fpMatch = ($backendPwFp !== 'unavailable' && $envPwFp === $backendPwFp) ? 'yes' : 'no';
        $lines[] = 'db password fp env='.$envPwFp.' backend='.$backendPwFp.' match='.$fpMatch;

        $dbAuthOk = $this->probeDatabaseAuth($provision);
        $lines[] = 'db auth with .env password: '.($dbAuthOk ? 'ok' : 'FAIL');

        $backendSelf = $this->probeBackendSelf($backend);
        $lines[] = 'backend_self (127.0.0.1:8080/api/v1/health/metrics): '.($backendSelf ? 'ok' : 'FAIL');

        $caddySnippetOk = $this->caddyContainerSeesSnippet($provision->slug);
        $lines[] = 'caddy_snippet_ok (/etc/caddy/sites/'.$provision->slug.'.caddy): '
            .($caddySnippetOk ? 'yes' : 'no');

        $upstream = $backend.':8080';
        $caddyConfigHasUpstream = $this->caddyConfigHasUpstream($upstream);
        $lines[] = 'caddy_config_has_upstream ('.$upstream.'): '
            .($caddyConfigHasUpstream ? 'yes' : 'no');

        $healthUrl = 'http://'.$backend.':8080/api/v1/health/metrics';
        $caddyExecToBackend = $this->probeFromCaddyContainer($healthUrl);
        $lines[] = 'caddy_exec_to_backend: '.($caddyExecToBackend ? 'ok' : 'FAIL');

        $caddyToBackend = $this->probeOnProxyNetwork($healthUrl);
        $lines[] = 'caddy→'.$backend.'/api/v1/health/metrics: '.($caddyToBackend ? 'ok' : 'FAIL');

        $feToBe = $this->run([
            'docker', 'exec', $frontend,
            'wget', '-q', '-O', '-', 'http://backend:8080/api/v1/health/metrics',
        ], 30);
        if ($feToBe['exit_code'] !== 0) {
            $feToBe = $this->run([
                'docker', 'exec', $frontend,
                'node', '-e',
                "fetch('http://backend:8080/api/v1/health/metrics').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))",
            ], 30);
        }
        $frontendToBackend = $feToBe['exit_code'] === 0;
        $lines[] = 'frontend→http://backend:8080 (internal alias): '.($frontendToBackend ? 'ok' : 'FAIL');

        return [
            'project' => $project,
            'containers' => $containers,
            'on_webino_sites' => $onSites,
            'db_auth_ok' => $dbAuthOk,
            'backend_self' => $backendSelf,
            'caddy_snippet_ok' => $caddySnippetOk,
            'caddy_config_has_upstream' => $caddyConfigHasUpstream,
            'caddy_exec_to_backend' => $caddyExecToBackend,
            'env_pw_fp' => $envPwFp,
            'backend_pw_fp' => $backendPwFp,
            'caddy_to_backend' => $caddyToBackend,
            'frontend_to_backend' => $frontendToBackend,
            'log' => implode("\n", $lines),
        ];
    }

    /**
     * HMAC call into the live tenant dashboard.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTenantApi(WebinoSiteProvision $provision, string $path, array $payload = []): array
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($settings->provision_webhook_secret ?? '');
        $token = (string) ($provision->provision_token ?? '');
        if ($secret === '') {
            throw new RuntimeException(
                'سکرت HMAC پروویژن در تنظیمات هاستینگ ERP خالی است (provision_webhook_secret).'
            );
        }
        if ($token === '') {
            throw new RuntimeException('توکن پروویژن این سایت خالی است؛ سایت را دوباره provision کنید.');
        }
        // Always JSON object so HMAC body matches Laravel request content on the tenant.
        $body = json_encode($payload === [] ? new \stdClass : $payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('Failed to encode tenant API payload.');
        }

        try {
            $response = Http::withHeaders([
                'X-Provision-Token' => $token,
                'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->withBody($body, 'application/json')
                ->timeout(90)
                ->post('https://'.$provision->domain.'/api/v1/'.$path);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'ارتباط با سایت tenant برقرار نشد (https://'.$provision->domain.'): '.$e->getMessage(),
                0,
                $e
            );
        }

        if (! $response->successful()) {
            $tenantMessage = data_get($response->json(), 'message');
            if (is_string($tenantMessage) && $tenantMessage !== '') {
                throw new RuntimeException($tenantMessage);
            }

            throw new RuntimeException(
                'درخواست به سایت tenant ناموفق بود (HTTP '.$response->status().').'
            );
        }

        return $response->json() ?? [];
    }

    protected function channelOf(WebinoSiteProvision $provision): string
    {
        $channel = (string) (($provision->wizard_payload['channel'] ?? null) ?: 'beta');

        return TenantSiteStack::imageTag($channel);
    }

    /**
     * Resolve compose image tag without inventing a missing "beta" tag.
     * Prefer an explicit wizard channel; otherwise keep the tag already in
     * docker-compose.yml when that image exists; finally fall back to latest.
     */
    protected function effectiveTag(WebinoSiteProvision $provision): string
    {
        $explicit = $provision->wizard_payload['channel'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return TenantSiteStack::imageTag($explicit);
        }

        $composePath = $this->siteDir($provision).'/docker-compose.yml';
        if (is_file($composePath)) {
            $content = (string) file_get_contents($composePath);
            if (preg_match('/webino-backend:([^\s"\']+)/', $content, $m)) {
                $tag = trim($m[1]);
                if ($tag !== '' && $this->run(
                    ['docker', 'image', 'inspect', 'webino-backend:'.$tag],
                    15,
                )['exit_code'] === 0) {
                    return $tag;
                }
            }
        }

        return 'latest';
    }

    /**
     * Align the Postgres role password with DB_PASSWORD from the site .env.
     * Official postgres image trusts local unix-socket connections, so ALTER USER
     * works without knowing the previous password. Never logs the password.
     */
    protected function syncDatabasePassword(WebinoSiteProvision $provision): string
    {
        $dbContainer = TenantSiteStack::projectName($provision->slug).'-db';
        $password = $this->readEnvMap($this->siteDir($provision).'/.env')['DB_PASSWORD'] ?? '';
        if ($password === '') {
            return 'db password sync skipped: DB_PASSWORD empty';
        }

        $healthy = false;
        for ($i = 0; $i < 24; $i++) {
            $inspect = $this->run([
                'docker', 'inspect', '-f', '{{.State.Health.Status}}', $dbContainer,
            ], 15);
            $status = trim($inspect['stdout'] ?? '');
            if ($inspect['exit_code'] === 0 && $status === 'healthy') {
                $healthy = true;
                break;
            }
            // No healthcheck configured — fall back to running state.
            if ($inspect['exit_code'] === 0 && ($status === '' || $status === '<no value>')) {
                $running = $this->run([
                    'docker', 'inspect', '-f', '{{.State.Running}}', $dbContainer,
                ], 15);
                if (trim($running['stdout'] ?? '') === 'true') {
                    $healthy = true;
                    break;
                }
            }
            sleep(2);
        }
        if (! $healthy) {
            return 'db password sync failed: '.$dbContainer.' not healthy';
        }

        $escaped = str_replace("'", "''", $password);
        $sql = "ALTER USER webino WITH PASSWORD '{$escaped}'";
        $result = $this->run([
            'docker', 'exec', $dbContainer,
            'psql', '-v', 'ON_ERROR_STOP=1', '-U', 'webino', '-d', 'webino', '-c', $sql,
        ], 30);

        if ($result['exit_code'] !== 0) {
            $err = trim($result['stderr'] ?: $result['stdout']);
            // Strip any accidental password echo from psql output.
            $err = str_replace($password, '***', $err);

            return 'db password sync failed: '.$err;
        }

        // Verify via the same path the backend uses (TCP to host "db" on tenant net).
        // Loopback probes are worthless — pg_hba trusts 127.0.0.1.
        if (! $this->probeDatabaseAuth($provision)) {
            return "db password synced: ok\ndb password sync verified: FAIL";
        }

        return "db password synced: ok\ndb password sync verified: ok";
    }

    /**
     * Probe TCP auth to Postgres the same way the backend does: connect to host
     * "db" on the tenant private network (scram-sha-256), not loopback trust.
     */
    protected function probeDatabaseAuth(WebinoSiteProvision $provision): bool
    {
        $password = $this->readEnvMap($this->siteDir($provision).'/.env')['DB_PASSWORD'] ?? '';
        if ($password === '') {
            return false;
        }

        $network = TenantSiteStack::projectName($provision->slug).'_net';
        $result = $this->run([
            'docker', 'run', '--rm',
            '--network', $network,
            '-e', 'PGPASSWORD='.$password,
            'postgres:15-alpine',
            'psql', '-h', 'db', '-U', 'webino', '-d', 'webino', '-tAc', 'select 1',
        ], 45);

        return $result['exit_code'] === 0 && str_contains(trim($result['stdout']), '1');
    }

    /**
     * Fingerprint a password without revealing it: first 8 hex of sha256 + length.
     */
    protected function passwordFingerprint(string $password): string
    {
        if ($password === '') {
            return 'empty/0';
        }

        return substr(hash('sha256', $password), 0, 8).'/'.strlen($password);
    }

    /**
     * Repair DB auth mismatch: rewrite .env, ALTER USER to match, recreate backend,
     * wait until the app serves health, then refresh Caddy snippet + reload.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function repairDatabase(WebinoSiteProvision $provision): array
    {
        $this->rewriteEnvFile($provision);
        $dbLog = $this->syncDatabasePassword($provision);
        $recreate = $this->recreateServices($provision, ['backend']);

        $backend = TenantSiteStack::backendService($provision->slug);
        $waitLine = $this->waitUntilBackendReady($backend, 30);

        $domain = strtolower(trim((string) $provision->domain));
        $caddyWrite = '';
        $caddyReload = ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
        if ($domain !== '' && str_contains($domain, '.')) {
            $caddyWrite = $this->writeCaddySnippet($domain, $provision->slug);
            $caddyReload = $this->reloadCaddyResult();
        }

        $verified = $this->probeDatabaseAuth($provision);
        $caddyToBackend = $this->probeOnProxyNetwork(
            'http://'.$backend.':8080/api/v1/health/metrics'
        );

        $reloadDetail = trim(($caddyReload['stderr'] ?? '')."\n".($caddyReload['stdout'] ?? ''));
        $log = trim(implode("\n", array_filter([
            $dbLog,
            $recreate['log'] ?? '',
            $waitLine,
            $caddyWrite !== '' ? $caddyWrite : null,
            $caddyWrite !== ''
                ? 'caddy reload exit='.$caddyReload['exit_code']
                    .($reloadDetail !== '' ? ' '.$reloadDetail : '')
                : null,
            'db auth after repair: '.($verified ? 'ok' : 'FAIL'),
            'caddy→backend after repair: '.($caddyToBackend ? 'ok' : 'FAIL'),
        ])));

        $exit = (
            ($recreate['exit_code'] ?? 1) === 0
            && $verified
            && $caddyToBackend
            && ($caddyWrite === '' || ($caddyReload['exit_code'] ?? 1) === 0)
        ) ? 0 : 1;

        return [
            'exit_code' => $exit,
            'stdout' => $recreate['stdout'] ?? '',
            'stderr' => $recreate['stderr'] ?? '',
            'log' => $log,
        ];
    }

    /**
     * Poll until Octane serves /api/v1/health/metrics inside the backend container.
     * Migrations on first boot can take ~60–90s.
     */
    protected function waitUntilBackendReady(string $backendContainer, int $attempts = 30): string
    {
        for ($i = 1; $i <= $attempts; $i++) {
            if ($this->probeBackendSelf($backendContainer)) {
                return 'backend ready after attempt '.$i.'/'.$attempts;
            }
            sleep(3);
        }

        return 'backend NOT ready after '.$attempts.' attempts (~'.($attempts * 3).'s)';
    }

    /**
     * Probe health from inside the backend container (independent of Docker DNS / Caddy).
     */
    protected function probeBackendSelf(string $backendContainer): bool
    {
        $url = 'http://127.0.0.1:8080/api/v1/health/metrics';

        $wget = $this->run([
            'docker', 'exec', $backendContainer,
            'wget', '-q', '-O', '-', $url,
        ], 15);
        if ($wget['exit_code'] === 0 && str_contains($wget['stdout'], 'data')) {
            return true;
        }

        $curl = $this->run([
            'docker', 'exec', $backendContainer,
            'curl', '-sf', $url,
        ], 15);
        if ($curl['exit_code'] === 0 && str_contains($curl['stdout'], 'data')) {
            return true;
        }

        $php = $this->run([
            'docker', 'exec', $backendContainer,
            'php', '-r',
            'echo @file_get_contents("http://127.0.0.1:8080/api/v1/health/metrics") ?: "";',
        ], 15);

        return $php['exit_code'] === 0 && str_contains($php['stdout'], 'data');
    }

    /**
     * Check whether the live Caddy config JSON mentions the tenant backend upstream.
     */
    protected function caddyConfigHasUpstream(string $upstream): bool
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return false;
        }

        $result = $this->run([
            'docker', 'exec', $web,
            'wget', '-q', '-O', '-', 'http://127.0.0.1:2019/config/',
        ], 20);
        if ($result['exit_code'] !== 0 || trim($result['stdout']) === '') {
            $result = $this->run([
                'docker', 'exec', $web,
                'wget', '-q', '-O', '-', 'http://localhost:2019/config/',
            ], 20);
        }
        if ($result['exit_code'] !== 0) {
            return false;
        }

        return str_contains($result['stdout'], $upstream);
    }

    /**
     * Probe a URL from inside the ERP Caddy container (same network path as reverse_proxy).
     */
    protected function probeFromCaddyContainer(string $url): bool
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return false;
        }

        $viaExec = $this->run([
            'docker', 'exec', $web,
            'wget', '-q', '-O', '-', $url,
        ], 30);

        return $viaExec['exit_code'] === 0;
    }

    /**
     * Always git-fetch and rebuild (unlike ensureImages which skips when present).
     *
     * @param  'frontend'|'backend'|'all'  $only
     */
    protected function forceBuildImages(string $tag, string $only = 'all'): void
    {
        $script = (string) (env('WEBINO_DASHBOARD_BUILD_SCRIPT')
            ?: ($this->erpRoot().'/scripts/build-webino-dashboard-images.sh'));
        if (! is_file($script)) {
            throw new RuntimeException('platform.build_script_missing');
        }

        $env = [
            'WEBINO_DASHBOARD_GIT_URL' => (string) env(
                'WEBINO_DASHBOARD_GIT_URL',
                'https://github.com/Webinadev/WebinoDashboard.git',
            ),
            'WEBINO_DASHBOARD_GIT_REF' => (string) env('WEBINO_DASHBOARD_GIT_REF', 'main'),
            'WEBINO_DASHBOARD_SRC' => (string) env('WEBINO_DASHBOARD_SRC', '/var/lib/webino/src/WebinoDashboard'),
            'WEBINO_IMAGE_TAG' => $tag === 'latest' ? '' : $tag,
        ];
        $dashboardPath = (string) env('WEBINO_DASHBOARD_PATH', '');
        if ($dashboardPath !== '' && is_file($dashboardPath.'/docker/php/Dockerfile.platform')) {
            $env['WEBINO_DASHBOARD_PATH'] = $dashboardPath;
        }
        $token = (string) env('WEBINO_DASHBOARD_GIT_TOKEN', '');
        if ($token !== '') {
            $env['WEBINO_DASHBOARD_GIT_TOKEN'] = $token;
        }

        // Build script always builds both; selective recreate happens afterwards.
        unset($only);
        $build = $this->runEnv(['bash', $script], 2400, $env);
        if ($build['exit_code'] !== 0) {
            throw new RuntimeException(
                'platform.dashboard_rebuild_failed: '.trim($build['stderr'] ?: $build['stdout'])
            );
        }
    }

    protected function rewriteComposeImages(WebinoSiteProvision $provision, string $channel): void
    {
        $dir = $this->siteDir($provision);
        $compose = TenantSiteStack::composeYaml($provision->slug, $channel);
        $this->writeFile($dir.'/docker-compose.yml', $compose);
    }

    /**
     * @param  list<string>  $services  Short names backend|frontend or full ws-*-*
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    protected function recreateServices(WebinoSiteProvision $provision, array $services): array
    {
        $dir = $this->siteDir($provision);
        $mapped = array_map(
            fn (string $s) => match ($s) {
                'backend' => TenantSiteStack::backendService($provision->slug),
                'frontend' => TenantSiteStack::frontendService($provision->slug),
                default => $s,
            },
            $services,
        );
        $cmd = [
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($provision->slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d', '--no-deps', '--force-recreate', '--remove-orphans',
            ...$mapped,
        ];
        $result = $this->run($cmd, 900);
        $attachLog = $this->attachToProxyNetwork($provision->slug);
        $this->updateResourceStatus($provision, 'running');

        return [
            ...$result,
            'log' => trim($result['stdout']."\n".$result['stderr']."\n".$attachLog),
        ];
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

    protected function imagesPresent(): bool
    {
        $backendOk = $this->run(['docker', 'image', 'inspect', 'webino-backend:latest'], 30)['exit_code'] === 0;
        $frontendOk = $this->run(['docker', 'image', 'inspect', 'webino-next:latest'], 30)['exit_code'] === 0;

        return $backendOk && $frontendOk;
    }

    protected function ensureImages(): void
    {
        if ($this->imagesPresent()) {
            return;
        }

        $script = (string) (env('WEBINO_DASHBOARD_BUILD_SCRIPT')
            ?: ($this->erpRoot().'/scripts/build-webino-dashboard-images.sh'));
        if (is_file($script)) {
            $env = [
                'WEBINO_DASHBOARD_GIT_URL' => (string) env(
                    'WEBINO_DASHBOARD_GIT_URL',
                    'https://github.com/Webinadev/WebinoDashboard.git',
                ),
                'WEBINO_DASHBOARD_GIT_REF' => (string) env('WEBINO_DASHBOARD_GIT_REF', 'main'),
                'WEBINO_DASHBOARD_SRC' => (string) env('WEBINO_DASHBOARD_SRC', '/var/lib/webino/src/WebinoDashboard'),
            ];
            $dashboardPath = (string) env('WEBINO_DASHBOARD_PATH', '');
            if ($dashboardPath !== '' && is_file($dashboardPath.'/docker/php/Dockerfile.platform')) {
                $env['WEBINO_DASHBOARD_PATH'] = $dashboardPath;
            }
            $token = (string) env('WEBINO_DASHBOARD_GIT_TOKEN', '');
            if ($token !== '') {
                $env['WEBINO_DASHBOARD_GIT_TOKEN'] = $token;
            }
            $build = $this->runEnv(['bash', $script], 2400, $env);
            if ($build['exit_code'] !== 0) {
                throw new RuntimeException(
                    'platform.dashboard_images_missing: git clone/build failed from '
                    .$env['WEBINO_DASHBOARD_GIT_URL'].'. '
                    .trim($build['stderr'] ?: $build['stdout'])
                );
            }
        }

        if (! $this->imagesPresent()) {
            throw new RuntimeException(
                'platform.dashboard_images_missing: need webino-backend:latest and webino-next:latest. '
                .'ERP clones https://github.com/Webinadev/WebinoDashboard into /var/lib/webino/src and builds images.'
            );
        }
    }

    /**
     * Stop and remove this site's containers and volumes only. Shared images stay.
     *
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function destroyStack(WebinoSiteProvision $provision): array
    {
        $dir = $this->siteDir($provision);
        $project = TenantSiteStack::projectName($provision->slug);
        $result = ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
        if (is_file($dir.'/docker-compose.yml')) {
            $result = $this->run([
                'docker', 'compose',
                '-p', $project,
                '-f', $dir.'/docker-compose.yml',
                'down', '-v', '--remove-orphans',
            ], 300);
        }
        $this->removeCaddySnippet($provision->slug);
        $this->reloadCaddy();
        $this->updateResourceStatus($provision, 'destroyed');

        return $result;
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

        $previous = $this->readEnvMap($this->siteDir($provision).'/.env');
        $appKey = $previous['APP_KEY'] ?? ('base64:'.base64_encode(random_bytes(32)));
        $dbPassword = $previous['DB_PASSWORD'] ?? Str::random(24);

        return implode("\n", [
            $this->envLine('APP_ENV', 'production'),
            $this->envLine('APP_DEBUG', 'false'),
            $this->envLine('APP_URL', 'https://'.$provision->domain),
            $this->envLine('APP_KEY', $appKey),
            $this->envLine('DB_CONNECTION', 'pgsql'),
            $this->envLine('DB_HOST', 'db'),
            $this->envLine('DB_PORT', '5432'),
            $this->envLine('DB_DATABASE', 'webino'),
            $this->envLine('DB_USERNAME', 'webino'),
            $this->envLine('DB_PASSWORD', $dbPassword),
            $this->envLine('REDIS_HOST', 'redis'),
            $this->envLine('REDIS_PORT', '6379'),
            $this->envLine('CACHE_STORE', 'redis'),
            $this->envLine('SESSION_DRIVER', 'file'),
            $this->envLine('QUEUE_CONNECTION', 'redis'),
            $this->envLine('SESSION_SECURE_COOKIE', 'true'),
            $this->envLine('TRUSTED_PROXIES', '*'),
            $this->envLine('SANCTUM_STATEFUL_DOMAINS', $provision->domain),
            $this->envLine('RUN_MIGRATIONS', '1'),
            $this->envLine('WEBINO_BASE_URL', $crm),
            $this->envLine('TENANT_LICENSE_KEY', (string) ($provision->license?->license_key ?? '')),
            $this->envLine('TENANT_PROVISION_TOKEN', $token),
            $this->envLine('TENANT_SEED_JSON', (string) $seed),
            $this->envLine('WEBINO_PROVISION_HMAC_SECRET', (string) ($settings->provision_webhook_secret ?? '')),
        ])."\n";
    }

    /**
     * Refresh tenant .env (preserves APP_KEY / DB_PASSWORD) for Sanctum / proxy headers.
     */
    protected function rewriteEnvFile(WebinoSiteProvision $provision): void
    {
        $provision->loadMissing(['license', 'package.businessType']);
        $siteType = (string) (
            ($provision->wizard_payload['site_type_slug'] ?? null)
            ?: ($provision->package?->businessType?->slug)
            ?: 'corporate'
        );
        $token = (string) ($provision->provision_token ?: '');
        $this->writeFile($this->siteDir($provision).'/.env', $this->envFile($provision, $siteType, $token));
    }

    /**
     * Public entry for artisan resync / control panel.
     */
    public function ensureCaddySnippet(WebinoSiteProvision $provision): string
    {
        $domain = strtolower(trim((string) $provision->domain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new RuntimeException('platform.invalid_domain');
        }

        return $this->writeCaddySnippet($domain, $provision->slug);
    }

    public function reloadCaddyPublic(): void
    {
        $this->reloadCaddy();
    }

    /**
     * Write tenant site block to durable host path (survives update.sh git clean)
     * and optionally mirror into the repo bind path.
     */
    protected function writeCaddySnippet(string $domain, string $slug): string
    {
        $snippet = TenantSiteStack::caddySnippet($domain, $slug);
        $paths = [];

        // Primary: durable host dir mounted into Caddy as /etc/caddy/sites
        $durable = '/var/lib/webino/caddy.d';
        $this->ensureDir($durable);
        $this->writeFile($durable.'/_keep.caddy', "# keep import glob non-empty\n");
        $durableFile = $durable.'/'.$slug.'.caddy';
        $this->writeFile($durableFile, $snippet);
        $paths[] = $durableFile;

        // Mirror: compose WEBINO_SITES_CADDY_DIR / repo docker/caddy/sites (may be wiped by git clean)
        $repoSites = (string) (env('WEBINO_SITES_CADDY_DIR')
            ?: ($this->erpRoot().'/docker/caddy/sites'));
        try {
            $this->ensureDir($repoSites);
            $this->writeFile(rtrim($repoSites, '/').'/_keep.caddy', "# keep import glob non-empty\n");
            $repoFile = rtrim($repoSites, '/').'/'.$slug.'.caddy';
            $this->writeFile($repoFile, $snippet);
            $paths[] = $repoFile;
        } catch (Throwable $e) {
            // Durable path is enough for Caddy when mounted correctly.
            $paths[] = 'repo-mirror-skipped: '.$e->getMessage();
        }

        if (! is_file($durableFile) || filesize($durableFile) < 10) {
            throw new RuntimeException('platform.caddy_snippet_write_failed: '.$durableFile);
        }

        return 'wrote '.implode(', ', $paths);
    }

    protected function caddySnippetPath(string $slug): string
    {
        return '/var/lib/webino/caddy.d/'.$slug.'.caddy';
    }

    protected function caddyContainerSeesSnippet(string $slug): bool
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return is_file($this->caddySnippetPath($slug));
        }
        $path = '/etc/caddy/sites/'.$slug.'.caddy';
        $result = $this->run(['docker', 'exec', $web, 'test', '-s', $path], 10);

        return $result['exit_code'] === 0;
    }

    /**
     * Delete only the leaf cert directory for one hostname inside the Caddy container.
     * Never removes the caddy_data Docker volume.
     */
    protected function deleteDomainCertLeaf(string $domain): string
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return 'force: caddy container not found; skipped cert leaf delete';
        }

        // Let's Encrypt storage: /data/caddy/certificates/acme-v02.api.letsencrypt.org-directory/{domain}/
        $domainArg = escapeshellarg($domain);
        $script = 'set -e; base=/data/caddy/certificates; '
            .'if [ ! -d "$base" ]; then echo "no certificates dir"; exit 0; fi; '
            .'found=0; domain='.$domainArg.'; for d in "$base"/*/; do '
            .'  leaf="${d}${domain}"; '
            .'  if [ -d "$leaf" ]; then rm -rf "$leaf"; found=1; echo "removed $leaf"; fi; '
            .'done; '
            .'if [ "$found" = "0" ]; then echo "no leaf for $domain"; fi';

        $result = $this->run(['docker', 'exec', $web, 'sh', '-c', $script], 60);

        return 'force: '.trim($result['stdout'].' '.$result['stderr']);
    }

    protected function probeHttps(string $domain): bool
    {
        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 8],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $headers = @get_headers('https://'.$domain.'/', true, $ctx);
            if (! is_array($headers) || ! isset($headers[0])) {
                return false;
            }

            return (bool) preg_match('/\s(2\d\d|3\d\d)\s/', (string) $headers[0]);
        } catch (Throwable) {
            return false;
        }
    }

    protected function readCertExpiry(string $domain): ?string
    {
        try {
            $ctx = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $client = @stream_socket_client(
                'ssl://'.$domain.':443',
                $errno,
                $errstr,
                8,
                STREAM_CLIENT_CONNECT,
                $ctx
            );
            if (! is_resource($client)) {
                return null;
            }
            $params = stream_context_get_params($client);
            fclose($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            if (! $cert) {
                return null;
            }
            $parsed = openssl_x509_parse($cert);
            $ts = $parsed['validTo_time_t'] ?? null;
            if (! is_int($ts) && ! is_float($ts)) {
                return null;
            }

            return gmdate('c', (int) $ts);
        } catch (Throwable) {
            return null;
        }
    }

    protected function removeCaddySnippet(string $slug): void
    {
        $repoSites = (string) (env('WEBINO_SITES_CADDY_DIR')
            ?: ($this->erpRoot().'/docker/caddy/sites'));
        foreach ([
            rtrim($repoSites, '/').'/'.$slug.'.caddy',
            '/var/lib/webino/caddy.d/'.$slug.'.caddy',
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    protected function reloadCaddy(): void
    {
        $this->reloadCaddyResult();
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function reloadCaddyResult(): array
    {
        $web = $this->findErpWebContainer();
        if ($web !== null) {
            return $this->run(['docker', 'exec', $web, 'caddy', 'reload', '--config', '/etc/caddy/Caddyfile'], 60);
        }

        return $this->run(['sh', '-c', 'systemctl reload caddy 2>/dev/null || caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || true'], 60);
    }

    protected function readCertExpiryFromCaddy(string $domain): ?string
    {
        $pem = $this->certPemFromCaddy($domain);
        if ($pem === null || $pem === '') {
            return null;
        }
        $parsed = @openssl_x509_parse($pem);
        if (! is_array($parsed)) {
            return null;
        }
        $ts = $parsed['validTo_time_t'] ?? null;
        if (! is_int($ts) && ! is_float($ts)) {
            return null;
        }

        return gmdate('c', (int) $ts);
    }

    protected function certPemFromCaddy(string $domain): ?string
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return null;
        }

        $domainArg = escapeshellarg($domain);
        $script = 'domain='.$domainArg.'; '
            .'f=$(find /data/caddy/certificates -type f -name "${domain}.crt" 2>/dev/null | head -1); '
            .'if [ -n "$f" ]; then cat "$f"; fi';
        $result = $this->run(['docker', 'exec', $web, 'sh', '-c', $script], 8);
        $pem = trim($result['stdout']);

        return str_contains($pem, 'BEGIN CERTIFICATE') ? $pem : null;
    }

    protected function caddyAcmeLogSnippet(string $domain): string
    {
        $web = $this->findErpWebContainer();
        if ($web === null) {
            return '';
        }
        $logs = $this->run(['docker', 'logs', '--tail', '80', $web], 20);
        $blob = $logs['stdout']."\n".$logs['stderr'];
        $lines = preg_split('/\r?\n/', $blob) ?: [];
        $hit = [];
        foreach ($lines as $line) {
            if ($line !== '' && (str_contains($line, $domain) || preg_match('/acme|certificate|tls/i', $line))) {
                $hit[] = $line;
            }
        }
        if ($hit === []) {
            return '';
        }

        return implode("\n", array_slice($hit, -12));
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function composeUp(string $slug, string $dir): array
    {
        return $this->run([
            'docker', 'compose',
            '-p', TenantSiteStack::projectName($slug),
            '-f', $dir.'/docker-compose.yml',
            '--env-file', $dir.'/.env',
            'up', '-d', '--remove-orphans',
        ], 900);
    }

    /**
     * Remove tenant proxy containers still labeled with the old compose service
     * names "backend"/"frontend". Those names collide with the new unique service
     * names (same container_name) and steal Docker DNS on webino_sites from ERP Caddy.
     */
    protected function removeLegacyServiceContainers(string $slug): string
    {
        $lines = [];
        foreach (TenantSiteStack::proxyContainerNames($slug) as $name) {
            $inspect = $this->run([
                'docker', 'inspect',
                '-f', '{{index .Config.Labels "com.docker.compose.service"}}',
                $name,
            ], 15);
            if ($inspect['exit_code'] !== 0) {
                continue;
            }
            $service = trim($inspect['stdout']);
            if ($service !== 'backend' && $service !== 'frontend') {
                continue;
            }
            $rm = $this->run(['docker', 'rm', '-f', $name], 60);
            if ($rm['exit_code'] === 0) {
                $lines[] = "removed legacy {$name} (compose service={$service})";
            } else {
                $lines[] = "failed to remove legacy {$name}: ".trim($rm['stderr'] ?: $rm['stdout']);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Connect tenant proxy containers to webino_sites (idempotent if already attached).
     *
     * @return string Human-readable attach log (empty when all already connected / ok)
     */
    protected function attachToProxyNetwork(string $slug, string $network = 'webino_sites'): string
    {
        $lines = [];
        foreach (TenantSiteStack::proxyContainerNames($slug) as $name) {
            $result = $this->run(['docker', 'network', 'connect', $network, $name], 30);
            if ($result['exit_code'] === 0) {
                $lines[] = "connected {$name} → {$network}";

                continue;
            }
            $err = strtolower(trim($result['stderr'].' '.$result['stdout']));
            if (
                str_contains($err, 'already exists')
                || str_contains($err, 'already connected')
                || str_contains($err, 'endpoint with name')
            ) {
                $lines[] = "{$name} already on {$network}";

                continue;
            }
            $lines[] = "connect {$name} failed: ".trim($result['stderr'] ?: $result['stdout']);
        }

        return implode("\n", $lines);
    }

    protected function waitForHealthy(string $slug, int $attempts = 12): bool
    {
        $project = TenantSiteStack::projectName($slug);
        $frontendUrl = 'http://'.$project.'-frontend:3000/up';
        $backendUrl = 'http://'.$project.'-backend:8080/api/v1/health/metrics';

        for ($i = 0; $i < $attempts; $i++) {
            $feOk = $this->probeOnProxyNetwork($frontendUrl);
            $beOk = $this->probeOnProxyNetwork($backendUrl);
            if ($feOk && $beOk) {
                return true;
            }

            sleep(5);
        }

        return false;
    }

    protected function probeOnProxyNetwork(string $url): bool
    {
        // Prefer probing from the real Caddy container — same path as reverse_proxy.
        // A disposable curl image may be missing on the host and fails silently.
        if ($this->probeFromCaddyContainer($url)) {
            return true;
        }

        $viaCurl = $this->run([
            'docker', 'run', '--rm',
            '--network', 'webino_sites',
            'curlimages/curl:8.5.0',
            '-sf', $url,
        ], 30);

        return $viaCurl['exit_code'] === 0;
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
        $byCompose = $this->run([
            'docker', 'ps',
            '--filter', 'label=com.docker.compose.service=web',
            '--format', '{{.Names}}',
        ], 15);
        $names = preg_split('/\r?\n/', trim($byCompose['stdout'])) ?: [];
        foreach ($names as $name) {
            if ($name !== '') {
                return $name;
            }
        }

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
        try {
            $process->run();
        } catch (Throwable $e) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => $e->getMessage(),
            ];
        }

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function readEnvMap(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $out[trim($key)] = trim($value, " \t\"'");
        }

        return $out;
    }

    protected function envLine(string $key, string $value): string
    {
        $escaped = str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);

        return $key.'="'.$escaped.'"';
    }
}
