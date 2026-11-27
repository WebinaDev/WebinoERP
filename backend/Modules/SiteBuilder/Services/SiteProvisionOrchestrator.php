<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\Platform\Services\WebinoDashboardProvisioner;
use Modules\Platform\Support\StackHealthReport;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

class SiteProvisionOrchestrator
{
    public function __construct(
        private readonly WebinoDashboardProvisioner $remote,
        private readonly LocalSameVpsProvisioner $local,
        private readonly LicenseProvisionerService $licenses,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    public function launch(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        try {
            $provision->load(['package.businessType.category', 'package.features', 'crmAccount']);

            if ($provision->status === WebinoSiteProvision::STATUS_CANCELLED) {
                return $provision;
            }

            $serverId = (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
            $server = $serverId ? PlatformServer::query()->find($serverId) : null;

            $useRemote = $server && ! $this->isLocalhostServer($server);
            if (! $useRemote) {
                $server = $this->ensureLocalhostServer();
            }

            if (! $server) {
                return $this->fail($provision, 'platform.no_ready_server');
            }

            $siteType = $provision->wizard_payload['site_type_slug']
                ?? $provision->package?->businessType?->slug
                ?? 'corporate';

            ProvisionProgress::assertNotCancelled($provision);
            if ($useRemote) {
                $this->remote->provisionFromSiteBuilder($provision, $server, $siteType);
            } else {
                $this->local->provisionFromSiteBuilder($provision, $server, $siteType);
            }
            $this->audit->log($provision->created_by, 'provision.launched', $provision->fresh());
            if ($provision->fresh()?->status === WebinoSiteProvision::STATUS_READY) {
                $this->markComingSoon($provision);
            }
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'platform.provision_cancelled')) {
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_CANCELLED);

                return $provision->fresh(['license', 'package', 'crmAccount']);
            }

            return $this->fail($provision, $e->getMessage());
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function start(WebinoSiteProvision $provision): array
    {
        $result = $this->shouldUseLocal($provision)
            ? $this->local->start($provision)
            : $this->remote->start($provision);
        $this->audit->log($provision->created_by, 'provision.start', $provision);

        return $result;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function stop(WebinoSiteProvision $provision): array
    {
        $result = $this->shouldUseLocal($provision)
            ? $this->local->stop($provision)
            : $this->remote->stop($provision);
        $this->audit->log($provision->created_by, 'provision.stop', $provision);

        return $result;
    }

    /**
     * Runtime power state separate from lifecycle status (ready/ssl_pending/…).
     *
     * @return 'running'|'stopped'|'unknown'
     */
    public function powerState(WebinoSiteProvision $provision): string
    {
        return $this->shouldUseLocal($provision)
            ? $this->local->powerState($provision)
            : $this->remote->powerState($provision);
    }

    /**
     * Align Postgres role with .env and recreate backend.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function repairDatabase(WebinoSiteProvision $provision): array
    {
        $result = $this->shouldUseLocal($provision)
            ? $this->local->repairDatabase($provision)
            : $this->remote->repairDatabase($provision);
        $this->audit->log($provision->created_by, 'provision.repair_db', $provision, [
            'exit_code' => $result['exit_code'] ?? null,
        ]);

        return $result;
    }

    /**
     * Re-run tenant provision/bootstrap (creates tenant row, content, admin user).
     * Used when the site is up but stuck in ssl_pending because the initial
     * bootstrap call failed (e.g. bad health probe).
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string}
     */
    public function bootstrapSite(WebinoSiteProvision $provision): array
    {
        $provision->loadMissing(['license', 'package.businessType.category', 'crmAccount']);
        $token = (string) ($provision->provision_token ?? '');
        if ($token === '') {
            throw new \RuntimeException('توکن پروویژن این سایت خالی است؛ سایت را دوباره provision کنید.');
        }

        $domain = strtolower(trim((string) $provision->domain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw new \RuntimeException('platform.invalid_domain');
        }

        $seed = $this->buildSeedJson($provision, $provision->wizard_payload ?? []);
        $log = [];
        $log[] = 'bootstrap target=https://'.$domain.'/api/v1/provision/bootstrap';

        try {
            $this->bootstrapRemoteSite($domain, $token, $seed);
            $provision->update([
                'status' => WebinoSiteProvision::STATUS_READY,
                'ready_at' => $provision->ready_at ?? now(),
                'error_log' => null,
            ]);
            $log[] = 'bootstrap: ok';
            $this->markComingSoon($provision);
            $this->audit->log($provision->created_by, 'provision.bootstrap', $provision->fresh());

            return [
                'exit_code' => 0,
                'stdout' => '',
                'stderr' => '',
                'log' => implode("\n", $log),
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $provision->update(['error_log' => $message]);
            $log[] = 'bootstrap: FAIL '.$message;

            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => $message,
                'log' => implode("\n", $log),
            ];
        }
    }

    /**
     * Recover a row stuck in "provisioning" whose launch already finished
     * (progress done/100%) or whose stack is healthy. May bootstrap an empty
     * tenant DB. Public DNS/SSL failure lands in ssl_pending, not ready.
     */
    public function reconcileStuckProvisioning(WebinoSiteProvision $provision): string
    {
        if ($provision->status !== WebinoSiteProvision::STATUS_PROVISIONING) {
            return 'not_provisioning';
        }

        $progress = is_array($provision->progress) ? $provision->progress : [];
        $phase = (string) ($progress['phase'] ?? '');
        $percent = (int) ($progress['percent'] ?? 0);
        $done = $phase === ProvisionProgress::PHASE_DONE || $percent >= 100;

        $result = $this->syncStatus($provision, bootstrap: $done);
        $status = (string) ($result['status'] ?? $provision->fresh()?->status ?? $provision->status);
        if ($status !== WebinoSiteProvision::STATUS_PROVISIONING) {
            return $status;
        }

        if ($done) {
            $checks = $result['checks'] ?? [];
            $publicOk = (bool) ($checks['public_readiness'] ?? false);
            $provision->update([
                'status' => $publicOk ? WebinoSiteProvision::STATUS_READY : WebinoSiteProvision::STATUS_SSL_PENDING,
                'ready_at' => $publicOk ? ($provision->ready_at ?? now()) : $provision->ready_at,
                'error_log' => $publicOk ? null : ($provision->error_log ?: 'دامنه در DNS/HTTPS عمومی در دسترس نیست.'),
                'progress' => $publicOk
                    ? ProvisionProgress::make(ProvisionProgress::PHASE_DONE)
                    : ProvisionProgress::make(ProvisionProgress::PHASE_SSL),
            ]);
            if ($publicOk) {
                $this->markComingSoon($provision);
            }

            return $publicOk ? 'ready' : 'ssl_pending';
        }

        return 'still_provisioning';
    }

    /**
     * Inspect live container/health/tenant DB/DNS and reconcile status.
     * When $bootstrap is true and the tenant DB is empty but the stack is healthy,
     * re-runs provision bootstrap (with origin fallback if public DNS fails).
     *
     * @return array{status:string,message:string,checks:array<string,mixed>,bootstrapped:bool}
     */
    public function syncStatus(WebinoSiteProvision $provision, bool $bootstrap = false): array
    {
        $checks = $this->collectStatusChecks($provision);
        $bootstrapped = false;
        $dbEmpty = ($checks['tenants'] === 0 && $checks['users'] !== null)
            || ($checks['tenants'] !== null && $checks['tenants'] === 0)
            || ($checks['users'] !== null && $checks['users'] === 0);
        $stackHealthy = (bool) $checks['origin_readiness'] || (bool) $checks['containers_running'];

        if ($bootstrap && $dbEmpty && $stackHealthy && (string) $provision->provision_token !== '') {
            $boot = $this->bootstrapSite($provision);
            $bootstrapped = ((int) ($boot['exit_code'] ?? 1)) === 0;
            $checks['bootstrap'] = [
                'ok' => $bootstrapped,
                'log' => $boot['log'] ?? '',
            ];
            $checks = array_merge($checks, $this->inspectTenantDatabase($provision));
            // Re-probe after bootstrap; bootstrapSite may have set status=ready
            // via the public domain, which we may still need to correct.
            $checks = array_merge($checks, $this->collectStatusChecks($provision->fresh() ?? $provision));
            $provision = $provision->fresh() ?? $provision;
        } elseif ($dbEmpty && $stackHealthy) {
            $checks['bootstrap_needed'] = true;
        }

        $target = $this->reconcileLifecycleStatus($provision, $checks);
        $updates = [];
        if ($target !== $provision->status) {
            $updates['status'] = $target;
            if ($target === WebinoSiteProvision::STATUS_READY) {
                $updates['ready_at'] = $provision->ready_at ?? now();
                $updates['error_log'] = null;
                $updates['progress'] = ProvisionProgress::make(ProvisionProgress::PHASE_DONE);
            } elseif ($target === WebinoSiteProvision::STATUS_SSL_PENDING) {
                $updates['progress'] = ProvisionProgress::make(ProvisionProgress::PHASE_SSL);
            }
        } elseif ($target === WebinoSiteProvision::STATUS_READY && $provision->ready_at === null) {
            $updates['ready_at'] = now();
            $updates['progress'] = ProvisionProgress::make(ProvisionProgress::PHASE_DONE);
        }

        if ($updates !== []) {
            $provision->update($updates);
            $provision = $provision->fresh() ?? $provision;
            if ($target === WebinoSiteProvision::STATUS_READY) {
                $this->markComingSoon($provision);
            }
        }

        $message = $this->syncStatusMessage($provision, $checks, $bootstrapped, $dbEmpty, $stackHealthy);

        return [
            'status' => $provision->status,
            'message' => $message,
            'checks' => $checks,
            'bootstrapped' => $bootstrapped,
        ];
    }

    /**
     * Advance one ssl_pending provision without relying on the status page poll:
     * health probe -> signed bootstrap -> ready. Failures go to error_log and
     * back off exponentially (1, 2, 4 … max 30 minutes) per provision.
     *
     * @return string one of: not_pending, backoff, unhealthy, ready, failed
     */
    public function advanceSslPending(WebinoSiteProvision $provision): string
    {
        if ($provision->status !== WebinoSiteProvision::STATUS_SSL_PENDING) {
            return 'not_pending';
        }
        $attemptKey = 'site_builder:auto_bootstrap:attempts:'.$provision->id;
        $nextKey = 'site_builder:auto_bootstrap:next:'.$provision->id;
        $next = Cache::get($nextKey);
        if ($next !== null && (int) $next > now()->getTimestamp()) {
            return 'backoff';
        }
        $attempts = (int) Cache::get($attemptKey, 0);
        $backoff = function (string $message) use ($provision, $attempts, $attemptKey, $nextKey): void {
            $n = $attempts + 1;
            $minutes = min(30, 2 ** min($attempts, 5));
            Cache::put($attemptKey, $n, now()->addDay());
            Cache::put($nextKey, now()->addMinutes($minutes)->getTimestamp(), now()->addDay());
            $provision->update(['error_log' => '['.now()->toIso8601String().'] auto-bootstrap attempt '.$n.': '.mb_substr($message, 0, 1000)]);
        };

        if (! $this->waitForHealthy((string) $provision->domain, attempts: 1, timeoutSeconds: 5)) {
            $backoff('health check failed for https://'.$provision->domain.'/api/v1/health/readiness');

            return 'unhealthy';
        }

        try {
            $seed = $this->buildSeedJson($provision, $provision->wizard_payload ?? []);
            $this->bootstrapRemoteSite((string) $provision->domain, (string) $provision->provision_token, $seed);
        } catch (Throwable $e) {
            $backoff($e->getMessage());

            return 'failed';
        }

        Cache::forget($attemptKey);
        Cache::forget($nextKey);
        $provision->update([
            'status' => WebinoSiteProvision::STATUS_READY,
            'ready_at' => $provision->ready_at ?? now(),
            'error_log' => null,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_DONE),
        ]);
        $this->markComingSoon($provision);

        return 'ready';
    }

    public function logs(WebinoSiteProvision $provision, int $tail = 200): string
    {
        if ($this->shouldUseLocal($provision)) {
            return $this->local->logs($provision, $tail);
        }

        return $this->remote->logs($provision, $tail);
    }

    /**
     * @param  'frontend'|'backend'|'migrate'|'full'  $target
     * @return array{exit_code:int,stdout:string,stderr:string,log?:string}
     */
    public function runUpdate(WebinoSiteProvision $provision, string $target): array
    {
        $payload = $provision->wizard_payload ?? [];
        $payload['update'] = [
            'target' => $target,
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
        ];
        $provision->update(['wizard_payload' => $payload]);

        if ($this->shouldUseLocal($provision)) {
            $result = match ($target) {
                'frontend' => $this->local->updateFrontend($provision),
                'backend' => $this->local->updateBackend($provision),
                'migrate' => $this->local->migrate($provision),
                'full' => $this->local->updateApp($provision),
                default => throw new \InvalidArgumentException(__('messages.invalid_update_target')),
            };
        } else {
            $result = $this->remote->runUpdate($provision, $target);
        }

        $payload = $provision->fresh()->wizard_payload ?? [];
        $fullLog = (string) ($result['log'] ?? '');
        // Keep the tail so Syncing/Dashboard HEAD and recreate lines survive truncation.
        $maxLog = 24000;
        if (strlen($fullLog) > $maxLog) {
            $fullLog = "...(truncated)...\n".substr($fullLog, -$maxLog);
        }
        $payload['update'] = [
            'target' => $target,
            'status' => ($result['exit_code'] ?? 1) === 0 ? 'done' : 'failed',
            'log' => $fullLog,
            'finished_at' => now()->toIso8601String(),
        ];
        $provision->update(['wizard_payload' => $payload]);
        $this->audit->log($provision->created_by, 'provision.update_'.$target, $provision);

        return $result;
    }

    public function changeDomain(WebinoSiteProvision $provision, string $newDomain): void
    {
        if ($this->shouldUseLocal($provision)) {
            $this->local->changeDomain($provision, $newDomain);
        } else {
            $this->remote->changeDomain($provision, $newDomain);
        }
    }

    /**
     * Apply stored runtime_limits to live site .env + compose and recreate containers.
     *
     * @return array{exit_code:int,stdout:string,stderr:string,log:string,limits:array}
     */
    public function applyRuntimeLimits(WebinoSiteProvision $provision): array
    {
        $result = $this->shouldUseLocal($provision)
            ? $this->local->applyRuntimeLimits($provision)
            : $this->remote->applyRuntimeLimits($provision);
        $this->audit->log($provision->created_by, 'provision.runtime_limits', $provision->fresh());

        return $result;
    }

    /**
     * Sync LinkHub alias subdomain (Caddy multi-host + Sanctum/CORS + platform_domains).
     */
    public function syncAliasDomains(WebinoSiteProvision $provision, ?string $aliasLabel = null): void
    {
        if ($aliasLabel !== null) {
            $label = \Modules\Platform\Support\TenantAliasDomains::normalizeLabel($aliasLabel);
            $provision->alias_subdomain = $label;
            $wizard = $provision->wizard_payload ?? [];
            $wizard['alias_subdomain'] = $label;
            $provision->wizard_payload = $wizard;
            $provision->save();
        }

        if ($this->shouldUseLocal($provision)) {
            $this->local->syncAliasDomains($provision);
        } else {
            $this->remote->syncAliasDomains($provision);
        }

        $this->audit->log($provision->created_by, 'provision.alias_sync', $provision->fresh());
    }

    /**
     * @return array{ok:bool,applied:bool,log:string}
     */
    public function applySiteDomains(WebinoSiteProvision $provision): array
    {
        if (! $this->shouldUseLocal($provision)) {
            return ['ok' => true, 'applied' => false, 'log' => 'remote server: Caddy not managed from ERP'];
        }
        $r = $this->local->applySiteDomains($provision);
        $this->audit->log($provision->created_by, 'provision.domains_apply', $provision->fresh());

        return $r + ['applied' => true];
    }

    public function reloadCaddyProxy(): void
    {
        $this->local->reloadCaddyPublic();
    }

    /**
     * Reload Caddy / optionally re-issue cert for one domain. Never deletes caddy_data volume.
     *
     * @return array{ok:bool,ssl_status:?string,expires_at:?string,forced:bool,log?:string}
     */
    public function renewSsl(WebinoSiteProvision $provision, bool $force = false): array
    {
        $result = $this->shouldUseLocal($provision)
            ? $this->local->renewSsl($provision, $force)
            : $this->remote->renewSsl($provision, $force);

        $payload = $provision->wizard_payload ?? [];
        $payload['ssl'] = [
            'ssl_status' => $result['ssl_status'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'log' => $result['log'] ?? null,
            'forced' => $force,
            'updated_at' => now()->toIso8601String(),
        ];
        $updates = ['wizard_payload' => $payload];

        if (($result['ssl_status'] ?? '') === 'active'
            && $provision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            $updates['status'] = WebinoSiteProvision::STATUS_READY;
            $updates['ready_at'] = $provision->ready_at ?? now();
            $updates['error_log'] = null;
        }

        $provision->update($updates);

        $this->audit->log($provision->created_by, 'provision.ssl_renew', $provision, [
            'force' => $force,
            'ok' => $result['ok'] ?? false,
            'ssl_status' => $result['ssl_status'] ?? null,
        ]);

        return $result;
    }

    /**
     * @return array{ssl_status:?string,expires_at:?string,domain:?string}
     */
    public function sslInfo(WebinoSiteProvision $provision): array
    {
        try {
            return $this->shouldUseLocal($provision)
                ? $this->local->sslInfo($provision)
                : $this->remote->sslInfo($provision);
        } catch (Throwable $e) {
            report($e);

            return [
                'ssl_status' => null,
                'expires_at' => null,
                'domain' => $provision->domain,
                'log' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function stackDiagnostics(WebinoSiteProvision $provision, float $budgetSeconds = 120): array
    {
        try {
            return $this->shouldUseLocal($provision)
                ? $this->local->stackDiagnostics($provision, $budgetSeconds)
                : $this->remote->stackDiagnostics($provision, $budgetSeconds);
        } catch (Throwable $e) {
            report($e);

            return StackHealthReport::notRun($e->getMessage());
        }
    }

    /**
     * HMAC tenant API works over HTTPS for both local and remote sites.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callTenantApi(WebinoSiteProvision $provision, string $path, array $payload = []): array
    {
        return $this->local->callTenantApi($provision, $path, $payload);
    }

    public function poll(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if ($provision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            // UI polls /status every ~3s while ssl_pending. Prod still runs single-threaded
            // `php artisan serve`, so a multi-second HTTPS probe + sleep starved the whole site
            // (webina.dev hung ~05:15–05:30 Asia/Tehran 2026-10-16 until backend restart).
            $skipKey = 'site_builder:ssl_probe_skip:'.$provision->id;
            if (! Cache::has($skipKey)) {
                // Short timeout, no inter-attempt sleep (attempts=1): keep the request under ~2s.
                if ($this->waitForHealthy($provision->domain, attempts: 1, timeoutSeconds: 2)) {
                    $payload = $provision->wizard_payload ?? [];
                    $seed = $this->buildSeedJson($provision, $payload);
                    try {
                        $this->bootstrapRemoteSite($provision->domain, (string) $provision->provision_token, $seed);
                        $provision->update([
                            'status' => WebinoSiteProvision::STATUS_READY,
                            'ready_at' => now(),
                        ]);
                        // Run after the poll response is sent so the status request stays fast.
                        app()->terminating(fn () => $this->markComingSoon($provision));
                    } catch (Throwable $e) {
                        $provision->update(['error_log' => $e->getMessage()]);
                    }
                } else {
                    // Negative cache so overlapping polls do not re-hit a dead/unreachable domain.
                    Cache::put($skipKey, 1, now()->addSeconds(15));
                }
            }
        }

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function cancel(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        if (! in_array($provision->status, [
            WebinoSiteProvision::STATUS_PENDING,
            WebinoSiteProvision::STATUS_PROVISIONING,
            WebinoSiteProvision::STATUS_SSL_PENDING,
        ], true)) {
            throw new \InvalidArgumentException(__('messages.only_in_progress_provisions_can_be_cancelled'));
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_CANCELLED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_CANCELLED),
            'error_log' => trim(($provision->error_log ?? '')."\nCancelled by user."),
        ]);

        try {
            if ($this->shouldUseLocal($provision)) {
                $this->local->destroyStack($provision);
            } else {
                $this->remote->destroyStack($provision);
            }
        } catch (Throwable $e) {
            report($e);
        }

        $this->audit->log($provision->created_by, 'provision.cancelled', $provision);

        return $provision->fresh(['license', 'package', 'crmAccount']);
    }

    public function rollback(WebinoSiteProvision $provision): WebinoSiteProvision
    {
        try {
            if ($this->shouldUseLocal($provision)) {
                $this->local->destroyStack($provision);
            } else {
                $this->remote->destroyStack($provision);
            }
        } catch (Throwable $e) {
            report($e);
        }

        if ($provision->license) {
            $this->licenses->revoke($provision->license);
        }

        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_FAILED),
            'error_log' => trim(($provision->error_log ?? '')."\nRolled back."),
        ]);

        return $provision->fresh();
    }

    protected function shouldUseLocal(WebinoSiteProvision $provision): bool
    {
        $serverId = (int) (($provision->wizard_payload['server_id'] ?? 0) ?: 0);
        if (! $serverId) {
            return true;
        }
        $server = PlatformServer::query()->find($serverId);

        return ! $server || $this->isLocalhostServer($server);
    }

    protected function isLocalhostServer(PlatformServer $server): bool
    {
        return (bool) $server->is_localhost
            || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true)
            || strcasecmp((string) $server->name, 'localhost') === 0;
    }

    protected function ensureLocalhostServer(): PlatformServer
    {
        $settings = CoreHostingSetting::current();
        $dirty = false;
        if (! filled($this->hostingValue($settings, 'platform_base_domain'))) {
            $settings->platform_base_domain = 'webina.dev';
            $dirty = true;
        }
        if (! filled($this->hostingValue($settings, 'provision_webhook_secret'))) {
            $settings->provision_webhook_secret = bin2hex(random_bytes(32));
            $dirty = true;
        }
        if ($dirty) {
            $settings->save();
        }

        return PlatformServer::query()->firstOrCreate(
            ['name' => 'localhost'],
            [
                'ip' => '127.0.0.1',
                'port' => 22,
                'user' => 'root',
                'status' => 'ready',
                'is_localhost' => true,
                'proxy_type' => 'caddy',
                'meta' => ['managed_by' => 'site_builder'],
            ]
        );
    }

    /**
     * Encrypted hosting columns throw DecryptException ("The payload is invalid.")
     * when the stored value is not ciphertext. That used to escape the sync
     * launch job and become HTTP 500. Treat it as empty so the caller can rewrite it.
     *
     * The raw value is also cleared on the model. `save()` compares encrypted
     * casts by decrypting the original, which would throw the same exception.
     */
    protected function hostingValue(CoreHostingSetting $settings, string $attribute): mixed
    {
        try {
            return $settings->getAttribute($attribute);
        } catch (DecryptException) {
            $raw = $settings->getAttributes();
            $raw[$attribute] = null;
            $settings->setRawAttributes($raw);
            $settings->syncOriginalAttribute($attribute);

            return null;
        }
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
        $siteType = $payload['site_type_slug'] ?? $type?->slug ?? 'corporate';

        return [
            'tenant_name' => $payload['site_name'] ?? $provision->slug,
            'store_display_name' => $payload['site_name'] ?? $provision->slug,
            'default_currency' => $payload['currency'] ?? 'IRT',
            'domain' => $provision->domain,
            'product' => $provision->license?->product ?? 'webinodashboard',
            'domain' => $provision->domain,
            'provision_token' => $provision->provision_token,
            'business_category_slug' => $category?->slug,
            'business_type_slug' => $type?->slug,
            'site_type_slug' => $siteType,
            'vertical' => $type?->slug,
            'package_sku' => $package?->sku,
            'theme_preset' => $type?->theme_preset,
            'nav_preset' => $type?->nav_preset,
            'branding' => [
                'logo_url' => $payload['logo_url'] ?? null,
                'description' => $payload['description'] ?? null,
                'currency_symbol' => $payload['currency_symbol'] ?? 'default',
            ],
            'admin_email' => $payload['admin_email'] ?? null,
            'admin_name' => $payload['admin_name'] ?? 'Admin',
            'crm_account_id' => $provision->crm_account_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectStatusChecks(WebinoSiteProvision $provision): array
    {
        $domain = strtolower(trim((string) $provision->domain));
        $power = 'unknown';
        try {
            $power = $this->powerState($provision);
        } catch (Throwable) {
        }

        $publicHome = $domain !== '' ? $this->probePublicUrl('https://'.$domain.'/', 5) : false;
        $publicReady = $domain !== '' ? $this->probePublicUrl('https://'.$domain.'/api/v1/health/readiness', 5) : false;
        $dnsOk = $publicHome || $publicReady || ($domain !== '' && $this->publicDomainResolves($domain));
        $originReady = $this->probeOriginReadiness($domain, 5);
        $db = $this->inspectTenantDatabase($provision);

        return array_merge([
            'domain' => $domain,
            'power_state' => $power,
            'containers_running' => $power === 'running',
            'dns_ok' => $dnsOk,
            'public_https' => $publicHome,
            'public_readiness' => $publicReady,
            'origin_readiness' => $originReady,
        ], $db);
    }

    /**
     * @return array{tenants:?int,users:?int,db_ok:bool,db_error:?string}
     */
    public function inspectTenantDatabase(WebinoSiteProvision $provision): array
    {
        $slug = (string) $provision->slug;
        if ($slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['tenants' => null, 'users' => null, 'db_ok' => false, 'db_error' => 'invalid slug'];
        }
        $container = 'ws-'.$slug.'-db';
        $sql = "SELECT (SELECT COUNT(*) FROM tenants)::int || ',' || (SELECT COUNT(*) FROM users)::int;";
        $script = 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c '.escapeshellarg($sql);
        $process = new \Symfony\Component\Process\Process([
            'docker', 'exec', $container, 'sh', '-c', $script,
        ]);
        $process->setTimeout(20);
        try {
            $process->run();
        } catch (Throwable $e) {
            return ['tenants' => null, 'users' => null, 'db_ok' => false, 'db_error' => $e->getMessage()];
        }
        if ($process->getExitCode() !== 0) {
            $err = trim($process->getErrorOutput().' '.$process->getOutput());

            return ['tenants' => null, 'users' => null, 'db_ok' => false, 'db_error' => $err !== '' ? $err : 'docker exec failed'];
        }
        $out = trim($process->getOutput());
        if (! preg_match('/^(\d+),(\d+)$/', $out, $m)) {
            return ['tenants' => null, 'users' => null, 'db_ok' => false, 'db_error' => 'unexpected psql output'];
        }

        return [
            'tenants' => (int) $m[1],
            'users' => (int) $m[2],
            'db_ok' => true,
            'db_error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $checks
     */
    protected function reconcileLifecycleStatus(WebinoSiteProvision $provision, array $checks): string
    {
        $current = (string) $provision->status;
        if (in_array($current, [WebinoSiteProvision::STATUS_CANCELLED, WebinoSiteProvision::STATUS_DRAFT, WebinoSiteProvision::STATUS_PENDING], true)) {
            return $current;
        }

        $publicOk = (bool) ($checks['public_readiness'] ?? false) || (bool) ($checks['public_https'] ?? false);
        $originOk = (bool) ($checks['origin_readiness'] ?? false);
        $running = (bool) ($checks['containers_running'] ?? false);
        $progress = is_array($provision->progress) ? $provision->progress : [];
        $done = (($progress['phase'] ?? '') === ProvisionProgress::PHASE_DONE) || ((int) ($progress['percent'] ?? 0) >= 100);

        if ($publicOk && ($running || $originOk || $done || $current === WebinoSiteProvision::STATUS_READY || $current === WebinoSiteProvision::STATUS_SSL_PENDING || $current === WebinoSiteProvision::STATUS_PROVISIONING)) {
            return WebinoSiteProvision::STATUS_READY;
        }

        if ($originOk || $running || $done) {
            if ($current === WebinoSiteProvision::STATUS_FAILED) {
                return WebinoSiteProvision::STATUS_SSL_PENDING;
            }
            if (in_array($current, [WebinoSiteProvision::STATUS_PROVISIONING, WebinoSiteProvision::STATUS_SSL_PENDING, WebinoSiteProvision::STATUS_READY], true)) {
                // Keep ready only when public is actually reachable; otherwise ssl_pending.
                return $publicOk ? WebinoSiteProvision::STATUS_READY : WebinoSiteProvision::STATUS_SSL_PENDING;
            }
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $checks
     */
    protected function syncStatusMessage(
        WebinoSiteProvision $provision,
        array $checks,
        bool $bootstrapped,
        bool $dbEmpty,
        bool $stackHealthy,
    ): string {
        $status = (string) $provision->status;
        $tenants = $checks['tenants'];
        $users = $checks['users'];
        $dbPart = ($tenants === null && $users === null)
            ? 'شمارش پایگاه داده در دسترس نبود'
            : 'تعداد مستأجر '.$tenants.' و کاربر '.$users;

        if ($bootstrapped) {
            $suffix = $status === WebinoSiteProvision::STATUS_READY
                ? 'وضعیت روی «آماده» تنظیم شد.'
                : ($status === WebinoSiteProvision::STATUS_SSL_PENDING
                    ? 'کانتینر سالم است اما دامنه در DNS/HTTPS عمومی در دسترس نیست؛ وضعیت روی «منتظر SSL» ماند.'
                    : 'وضعیت فعلی: '.$status.'.');

            return 'راه‌اندازی اولیه انجام شد ('.$dbPart.'). '.$suffix;
        }

        if ($dbEmpty && $stackHealthy) {
            return 'کانتینرها سالم‌اند اما پایگاه داده مستأجر خالی است ('.$dbPart.'). برای راه‌اندازی اولیه، همگام‌سازی را با گزینه «راه‌اندازی در صورت نیاز» اجرا کنید.';
        }

        if ($status === WebinoSiteProvision::STATUS_READY) {
            return 'وضعیت سایت همگام شد؛ سایت آماده است ('.$dbPart.').';
        }

        if ($status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            if (! ($checks['dns_ok'] ?? false)) {
                return 'کانتینر و مبدأ سالم هستند، اما دامنه در DNS عمومی resolve نمی‌شود؛ وضعیت روی «منتظر SSL» ماند. NS دامنه را در پنل ایرنیک/CDN بررسی کنید. ('.$dbPart.')';
            }

            return 'سایت از مبدأ در دسترس است اما HTTPS/SSL عمومی کامل نیست؛ وضعیت روی «منتظر SSL» ماند. ('.$dbPart.')';
        }

        if ($status === WebinoSiteProvision::STATUS_PROVISIONING) {
            return 'سایت هنوز در حال راه‌اندازی است و برای تغییر وضعیت شواهد کافی (سلامت کانتینر یا پیشرفت ۱۰۰٪) دیده نشد.';
        }

        if ($status === WebinoSiteProvision::STATUS_FAILED) {
            return 'وضعیت سایت «ناموفق» است و همگام‌سازی آن را تغییر نداد.';
        }

        return 'وضعیت سایت همگام شد: '.$status.' ('.$dbPart.').';
    }

    protected function publicDomainResolves(string $domain): bool
    {
        if ($domain === '' || ! str_contains($domain, '.')) {
            return false;
        }
        // Avoid blocking DNS lookups in tests (example.test can hang for seconds).
        if (app()->environment('testing')) {
            return false;
        }
        $ips = @gethostbynamel($domain);

        return is_array($ips) && $ips !== [];
    }

    protected function originResolveIp(): ?string
    {
        foreach (['webinoerm-web-1'] as $host) {
            $ip = gethostbyname($host);
            if (is_string($ip) && $ip !== '' && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    protected function probePublicUrl(string $url, int $timeoutSeconds = 5): bool
    {
        try {
            $res = Http::timeout(max(1, $timeoutSeconds))
                ->connectTimeout(min(2, $timeoutSeconds))
                ->get($url);

            return $res->successful();
        } catch (Throwable) {
            return false;
        }
    }

    protected function probeOriginReadiness(string $domain, int $timeoutSeconds = 5): bool
    {
        if ($domain === '') {
            return false;
        }
        $ip = $this->originResolveIp();
        if ($ip === null) {
            return false;
        }
        try {
            $res = Http::timeout(max(1, $timeoutSeconds))
                ->connectTimeout(min(2, $timeoutSeconds))
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => [
                            $domain.':443:'.$ip,
                            $domain.':80:'.$ip,
                        ],
                    ],
                ])
                ->get('https://'.$domain.'/api/v1/health/readiness');

            return $res->successful();
        } catch (Throwable) {
            return false;
        }
    }

    protected function waitForHealthy(string $domain, int $attempts = 12, int $timeoutSeconds = 8): bool
    {
        // Public DNS/HTTPS only — origin fallback is reserved for bootstrap/sync.
        // A site whose apex is NXDOMAIN must stay ssl_pending, not flip to ready.
        // Must hit /api/* so Caddy routes to the Laravel backend.
        // Plain /up is handled by Next (or 404 on older images) and never reaches Octane.
        // Tenant (WebinoDashboard) /health/metrics is token-gated and 404s without
        // X-Health-Token; /health/readiness is public and checks db/redis/queue.
        $url = 'https://'.$domain.'/api/v1/health/readiness';
        $timeoutSeconds = max(1, $timeoutSeconds);
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $res = Http::timeout($timeoutSeconds)->connectTimeout(min(2, $timeoutSeconds))->get($url);
                if ($res->successful()) {
                    return true;
                }
            } catch (Throwable) {
            }
            // Sleep only BETWEEN attempts — never after the last one (status poll uses attempts=1).
            if ($i < $attempts - 1) {
                sleep(5);
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $seed
     */
    protected function bootstrapRemoteSite(string $domain, string $token, array $seed): void
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($this->hostingValue($settings, 'provision_webhook_secret') ?? '');
        if ($secret === '') {
            throw new \RuntimeException('platform.provision_hmac_missing');
        }
        $body = json_encode(['seed' => $seed], JSON_UNESCAPED_UNICODE);
        $headers = [
            'X-Provision-Token' => $token,
            'X-Provision-Signature' => hash_hmac('sha256', $body, $secret),
        ];
        $request = Http::withHeaders($headers)
            ->withBody($body, 'application/json')
            ->timeout(60);
        try {
            $request->post('https://'.$domain.'/api/v1/provision/bootstrap')->throw();

            return;
        } catch (Throwable $e) {
            // Only fall back to origin when the public hostname is unreachable
            // (NXDOMAIN / connection). HTTP 4xx/5xx from a reached host must
            // surface so callers can back off correctly.
            if ($e instanceof \Illuminate\Http\Client\RequestException) {
                throw $e;
            }
            $ip = $this->originResolveIp();
            if ($ip === null) {
                throw $e;
            }
            Http::withHeaders($headers)
                ->withBody($body, 'application/json')
                ->timeout(60)
                ->withOptions([
                    'verify' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => [
                            $domain.':443:'.$ip,
                            $domain.':80:'.$ip,
                        ],
                    ],
                ])
                ->post('https://'.$domain.'/api/v1/provision/bootstrap')
                ->throw();
        }
    }

    /** New sites open as "coming soon" once the tenant is bootstrapped (no-op if a status was already set). */
    protected function markComingSoon(WebinoSiteProvision $provision): void
    {
        try {
            app(SiteStatusService::class)->markNewSiteComingSoon($provision->fresh() ?? $provision);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function fail(WebinoSiteProvision $provision, string $message): WebinoSiteProvision
    {
        $provision->update([
            'status' => WebinoSiteProvision::STATUS_FAILED,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_FAILED),
            'error_log' => $message,
        ]);
        $this->audit->log($provision->created_by, 'provision.failed', $provision, ['error' => $message]);

        return $provision->fresh();
    }
}
