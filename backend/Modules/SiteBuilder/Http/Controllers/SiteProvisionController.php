<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use Modules\SiteBuilder\Services\SiteProvisionAuditLogger;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

class SiteProvisionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = WebinoSiteProvision::query()
            ->with(['license', 'package.businessType', 'crmAccount'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json(['data' => $q->paginate($request->integer('per_page', 20))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'crm_account_id' => 'nullable|integer|exists:crm_accounts,id',
            'package_id' => 'nullable|integer|exists:webino_packages,id',
            'slug' => 'nullable|string|max:64',
            'wizard_payload' => 'nullable|array',
        ]);

        try {
            $wizard = is_array($data['wizard_payload'] ?? null) ? $data['wizard_payload'] : [];
            $slug = $this->uniqueSlug($data['slug'] ?? null, $wizard['site_name'] ?? null);
            $usesCustom = (bool) ($wizard['uses_custom_domain'] ?? false);
            $domain = $this->resolveDomain($slug, $usesCustom, $wizard['custom_domain'] ?? null);

            $row = WebinoSiteProvision::query()->create([
                'crm_account_id' => $data['crm_account_id'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'slug' => $slug,
                'domain' => $domain,
                'subdomain' => $usesCustom ? null : $slug,
                'uses_custom_domain' => $usesCustom,
                'status' => WebinoSiteProvision::STATUS_DRAFT,
                'wizard_payload' => $wizard,
                'provision_token' => Str::random(48),
                'created_by' => $request->user()?->id,
            ]);

            app(SiteProvisionAuditLogger::class)->log($request->user()?->id, 'provision.created', $row);

            return response()->json(['data' => $row->fresh(['license', 'package', 'crmAccount'])], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: 'Failed to create provision.'], 422);
        }
    }

    public function show(WebinoSiteProvision $siteProvision): JsonResponse
    {
        $siteProvision->load(['license', 'package.businessType.category', 'crmAccount']);

        return response()->json(['data' => $siteProvision]);
    }

    public function update(Request $request, WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        $isLive = $this->isControlEditable($siteProvision);
        $isDraft = in_array($siteProvision->status, [WebinoSiteProvision::STATUS_DRAFT, WebinoSiteProvision::STATUS_PENDING], true);

        if (! $isLive && ! $isDraft) {
            return response()->json([
                'message' => 'سایت در این وضعیت قابل ویرایش نیست: '.$siteProvision->status,
            ], 422);
        }

        if ($isDraft) {
            $data = $request->validate([
                'crm_account_id' => 'nullable|integer|exists:crm_accounts,id',
                'package_id' => 'nullable|integer|exists:webino_packages,id',
                'slug' => 'nullable|string|max:64',
                'wizard_payload' => 'nullable|array',
                'uses_custom_domain' => 'nullable|boolean',
            ]);

            try {
                $wizard = array_merge($siteProvision->wizard_payload ?? [], $data['wizard_payload'] ?? []);
                unset($data['wizard_payload']);

                $incomingSlug = $data['slug'] ?? $wizard['slug'] ?? null;
                $slug = $this->uniqueSlug(
                    is_string($incomingSlug) && $incomingSlug !== '' ? $incomingSlug : $siteProvision->slug,
                    $wizard['site_name'] ?? null,
                    $siteProvision->id,
                );
                $usesCustom = (bool) ($data['uses_custom_domain'] ?? $wizard['uses_custom_domain'] ?? $siteProvision->uses_custom_domain);
                $data['slug'] = $slug;
                $data['domain'] = $this->resolveDomain($slug, $usesCustom, $wizard['custom_domain'] ?? $siteProvision->domain);
                $data['subdomain'] = $usesCustom ? null : $slug;
                $data['uses_custom_domain'] = $usesCustom;
                $data['wizard_payload'] = $wizard;
                $siteProvision->update($data);

                return response()->json(['data' => $siteProvision->fresh(['license', 'package', 'crmAccount'])]);
            } catch (Throwable $e) {
                report($e);

                return response()->json(['message' => $e->getMessage() ?: 'Failed to update provision.'], 422);
            }
        }

        $data = $request->validate([
            'crm_account_id' => 'nullable|integer|exists:crm_accounts,id',
            'domain' => 'nullable|string|max:255',
            'logo_url' => 'nullable|string|max:2048',
            'site_name' => 'nullable|string|max:255',
            'channel' => 'nullable|string|in:beta,stable,latest',
            'license' => 'nullable|array',
            'license.status' => 'nullable|string|max:32',
            'license.start_date' => 'nullable|date',
            'license.expires_at' => 'nullable|date',
            'license.max_users' => 'nullable|integer|min:1',
        ]);

        try {
            $wizard = $siteProvision->wizard_payload ?? [];
            if (array_key_exists('crm_account_id', $data)) {
                $siteProvision->crm_account_id = $data['crm_account_id'];
            }
            if (! empty($data['site_name'])) {
                $wizard['site_name'] = $data['site_name'];
            }
            if (! empty($data['logo_url'])) {
                $wizard['logo_url'] = $data['logo_url'];
            }
            if (! empty($data['channel'])) {
                if ($data['channel'] === 'stable') {
                    return response()->json(['message' => 'Stable channel is not available yet.'], 503);
                }
                $wizard['channel'] = $data['channel'];
            }

            $oldDomain = $siteProvision->domain;
            if (! empty($data['domain']) && strtolower($data['domain']) !== strtolower((string) $oldDomain)) {
                $newDomain = strtolower(trim($data['domain']));
                $orchestrator->changeDomain($siteProvision, $newDomain);
                $siteProvision->domain = $newDomain;
                $siteProvision->uses_custom_domain = true;
                $wizard['custom_domain'] = $newDomain;
                $wizard['uses_custom_domain'] = true;
                if ($siteProvision->license) {
                    $siteProvision->license->update(['domain' => $newDomain]);
                }
            }

            $siteProvision->wizard_payload = $wizard;
            $siteProvision->save();

            if ($siteProvision->license && ! empty($data['logo_url'])) {
                $siteProvision->license->update(['logo_url' => $data['logo_url']]);
            }
            if ($siteProvision->license && ! empty($data['license'])) {
                $lic = $data['license'];
                $siteProvision->license->fill(array_filter([
                    'status' => $lic['status'] ?? null,
                    'start_date' => $lic['start_date'] ?? null,
                    'expires_at' => $lic['expires_at'] ?? null,
                    'max_users' => $lic['max_users'] ?? null,
                    'project_name' => $data['site_name'] ?? null,
                ], fn ($v) => $v !== null));
                $siteProvision->license->save();
                \Modules\Core\Services\CoreLicenseMetaNormalizer::forgetCheckCache(
                    $siteProvision->license->domain,
                    $siteProvision->license->license_key,
                );
            }

            if (! empty($data['logo_url']) || ($siteProvision->domain !== $oldDomain)) {
                try {
                    $orchestrator->callTenantApi($siteProvision, 'provision/branding', [
                        'logo_url' => $wizard['logo_url'] ?? $siteProvision->license?->logo_url,
                        'domain' => $siteProvision->domain,
                        'site_name' => $wizard['site_name'] ?? null,
                    ]);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return response()->json(['data' => $siteProvision->fresh(['license', 'package', 'crmAccount'])]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: 'Failed to update site.'], 422);
        }
    }

    public function prepareLicense(WebinoSiteProvision $siteProvision, LicenseProvisionerService $licenses, Request $request): JsonResponse
    {
        if (! $siteProvision->package_id) {
            return response()->json(['message' => 'Package is required.'], 422);
        }

        $siteProvision->load(['package.businessType.category', 'package.features']);

        if (! $siteProvision->license_id) {
            $payload = $siteProvision->wizard_payload ?? [];
            $siteType = (string) ($payload['site_type_slug'] ?? $siteProvision->package?->businessType?->slug ?? 'corporate');
            if (CoreLicense::query()->where('domain', $siteProvision->domain)->exists()) {
                return response()->json(['message' => 'Domain already licensed. Choose another slug or domain.'], 422);
            }
            try {
                $license = $licenses->createForProvision(
                    $siteProvision->domain,
                    $siteProvision->package,
                    [
                        'selected_feature_slugs' => $payload['selected_feature_slugs'] ?? [],
                        'site_type' => $siteType,
                        'site_type_slug' => $siteType,
                        'site_name' => $payload['site_name'] ?? $siteProvision->slug,
                        'project_name' => $payload['site_name'] ?? $siteProvision->slug,
                    ],
                    $request->user()?->id,
                );
            } catch (Throwable $e) {
                report($e);

                return response()->json(['message' => $e->getMessage() ?: 'Failed to prepare license.'], 422);
            }
            $siteProvision->update(['license_id' => $license->id]);
            app(SiteProvisionAuditLogger::class)->log($request->user()?->id, 'license.prepared', $siteProvision, [
                'license_key' => $license->license_key,
            ]);
        }

        return response()->json([
            'data' => $siteProvision->fresh(['license', 'package']),
        ]);
    }

    public function launch(WebinoSiteProvision $siteProvision, Request $request): JsonResponse
    {
        if (! in_array($siteProvision->status, [
            WebinoSiteProvision::STATUS_DRAFT,
            WebinoSiteProvision::STATUS_PENDING,
            WebinoSiteProvision::STATUS_FAILED,
            WebinoSiteProvision::STATUS_CANCELLED,
        ], true)) {
            return response()->json(['message' => 'Provision already launched.'], 422);
        }

        if (! $siteProvision->package_id) {
            return response()->json(['message' => 'Package is required.'], 422);
        }

        $siteProvision->update([
            'status' => WebinoSiteProvision::STATUS_PENDING,
            'error_log' => null,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_QUEUED),
        ]);
        ProvisionWebinoSiteJob::dispatch($siteProvision->id);
        app(SiteProvisionAuditLogger::class)->log($request->user()?->id, 'provision.launch_queued', $siteProvision);

        return response()->json([
            'data' => $siteProvision->fresh(['license', 'package']),
            'message' => 'Provisioning queued.',
        ]);
    }

    public function status(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        if ($siteProvision->status === WebinoSiteProvision::STATUS_SSL_PENDING) {
            $siteProvision = $orchestrator->poll($siteProvision);
        }

        return response()->json(['data' => $siteProvision->load(['license', 'package'])]);
    }

    public function cancel(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator, Request $request): JsonResponse
    {
        try {
            $row = $orchestrator->cancel($siteProvision);
            app(SiteProvisionAuditLogger::class)->log($request->user()?->id, 'provision.cancel_requested', $row);

            return response()->json(['data' => $row, 'message' => 'Cancelled']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function retry(WebinoSiteProvision $siteProvision): JsonResponse
    {
        if (! in_array($siteProvision->status, [
            WebinoSiteProvision::STATUS_FAILED,
            WebinoSiteProvision::STATUS_CANCELLED,
        ], true)) {
            return response()->json(['message' => 'Only failed or cancelled provisions can be retried.'], 422);
        }

        $siteProvision->update([
            'status' => WebinoSiteProvision::STATUS_PENDING,
            'error_log' => null,
            'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_QUEUED),
        ]);
        ProvisionWebinoSiteJob::dispatch($siteProvision->id);

        return response()->json(['data' => $siteProvision, 'message' => 'Retry queued.']);
    }

    public function start(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        try {
            $result = $orchestrator->start($siteProvision);

            return response()->json([
                'data' => $siteProvision->fresh(['license', 'package']),
                'compose' => $result,
                'message' => $result['exit_code'] === 0 ? 'Started' : 'Start failed',
            ], $result['exit_code'] === 0 ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function stop(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        try {
            $result = $orchestrator->stop($siteProvision);

            return response()->json([
                'data' => $siteProvision->fresh(['license', 'package']),
                'compose' => $result,
                'message' => $result['exit_code'] === 0 ? 'Stopped' : 'Stop failed',
            ], $result['exit_code'] === 0 ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function repairDatabase(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        try {
            $result = $orchestrator->repairDatabase($siteProvision);

            return response()->json([
                'data' => $siteProvision->fresh(['license', 'package']),
                'compose' => $result,
                'message' => $result['exit_code'] === 0 ? 'Database repaired' : 'Database repair failed',
            ], $result['exit_code'] === 0 ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function logs(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator, Request $request): JsonResponse
    {
        try {
            $tail = max(1, min(2000, $request->integer('tail', 200)));
            $logs = $orchestrator->logs($siteProvision, $tail);

            return response()->json([
                'data' => [
                    'provision_id' => $siteProvision->id,
                    'slug' => $siteProvision->slug,
                    'logs' => $logs,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function control(WebinoSiteProvision $siteProvision): JsonResponse
    {
        try {
            $siteProvision->load(['license', 'package.businessType.category', 'crmAccount']);
        } catch (Throwable $e) {
            report($e);
        }

        $wizard = is_array($siteProvision->wizard_payload) ? $siteProvision->wizard_payload : [];
        $license = $siteProvision->license;
        $meta = is_array($license?->meta) ? $license->meta : [];
        $modules = $meta['modules'] ?? $meta['licensed_modules'] ?? [];
        if (! is_array($modules)) {
            $modules = [];
        }
        $channel = (string) (($wizard['channel'] ?? null) ?: 'beta');

        $ssl = [
            'ssl_status' => null,
            'expires_at' => null,
            'domain' => $siteProvision->domain,
            'log' => null,
        ];
        try {
            $ssl = app(SiteProvisionOrchestrator::class)->sslInfo($siteProvision);
        } catch (Throwable $e) {
            report($e);
            $ssl['log'] = $e->getMessage();
        }

        $stack = [
            'project' => null,
            'containers' => [],
            'on_webino_sites' => ['backend' => false, 'frontend' => false],
            'caddy_to_backend' => false,
            'frontend_to_backend' => false,
            'log' => null,
        ];
        try {
            $stack = app(SiteProvisionOrchestrator::class)->stackDiagnostics($siteProvision);
        } catch (Throwable $e) {
            report($e);
            $stack['log'] = $e->getMessage();
        }

        $licensePayload = null;
        try {
            if ($license) {
                $expires = $license->expires_at;
                $licensePayload = [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'status' => $license->status,
                    'domain' => $license->domain,
                    'logo_url' => $license->logo_url,
                    'project_name' => $license->project_name,
                    'start_date' => optional($license->start_date)?->toDateString(),
                    'expires_at' => optional($expires)?->toIso8601String(),
                    'created_at' => optional($license->created_at)?->toIso8601String(),
                    'max_users' => $license->max_users,
                    'modules' => array_values(array_filter(array_map('strval', $modules))),
                    'module_matrix' => $meta['module_matrix'] ?? new \stdClass,
                    'is_expired' => $expires ? $expires->isPast() : false,
                    'days_remaining' => $expires ? (int) now()->diffInDays($expires, false) : null,
                ];
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(
            [
                'data' => [
                    'provision' => $siteProvision,
                    'channel' => $channel,
                    'admin' => [
                        'name' => $wizard['admin_name'] ?? null,
                        'email' => $wizard['admin_email'] ?? null,
                    ],
                    'license' => $licensePayload,
                    'update' => $wizard['update'] ?? null,
                    'customer' => $siteProvision->crmAccount,
                    'ssl' => $ssl,
                    'stack' => $stack,
                ],
            ],
            200,
            [],
            JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    public function renewSsl(Request $request, WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        if (! $this->isControlEditable($siteProvision)) {
            return response()->json([
                'message' => 'سایت هنوز آماده/SSL نیست: '.$siteProvision->status,
            ], 422);
        }

        $data = $request->validate([
            'force' => 'nullable|boolean',
        ]);

        try {
            $result = $orchestrator->renewSsl($siteProvision, (bool) ($data['force'] ?? false));
            $ok = (bool) ($result['ok'] ?? false);
            $active = ($result['ssl_status'] ?? '') === 'active';

            return response()->json([
                'data' => $siteProvision->fresh(['license', 'crmAccount']),
                'meta' => ['ssl' => $result],
                'message' => $active
                    ? 'گواهی SSL فعال است.'
                    : 'درخواست SSL به Caddy ارسال شد. اگر گواهی نیامد، DNS و پورت ۸۰/۴۴۳ را بررسی کنید.',
            ], $ok ? 200 : 422);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'تمدید SSL ناموفق بود.'], 422);
        }
    }

    public function updateAdmin(Request $request, WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        if (! $this->isControlEditable($siteProvision)) {
            return response()->json([
                'message' => 'سایت هنوز آماده/SSL نیست: '.$siteProvision->status,
            ], 422);
        }

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:8|max:255',
        ]);

        $data = array_filter(
            $data,
            static fn ($v) => $v !== null && $v !== ''
        );

        if ($data === []) {
            return response()->json(['message' => 'حداقل یکی از فیلدهای نام، ایمیل یا رمز عبور را وارد کنید.'], 422);
        }

        try {
            $result = $orchestrator->callTenantApi($siteProvision, 'provision/admin', $data);
            $wizard = $siteProvision->wizard_payload ?? [];
            if (! empty($data['name'])) {
                $wizard['admin_name'] = $data['name'];
            }
            if (! empty($data['email'])) {
                $wizard['admin_email'] = $data['email'];
            }
            $siteProvision->update(['wizard_payload' => $wizard]);

            return response()->json(['data' => $siteProvision->fresh(['license', 'crmAccount']), 'tenant' => $result]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'به‌روزرسانی ادمین سایت ناموفق بود.'], 422);
        }
    }

    public function updateModules(Request $request, WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        if (! $this->isControlEditable($siteProvision)) {
            return response()->json([
                'message' => 'سایت هنوز آماده/SSL نیست: '.$siteProvision->status,
            ], 422);
        }

        if (! $siteProvision->license_id) {
            return response()->json(['message' => 'لایسنسی به این سایت وصل نیست.'], 422);
        }

        $data = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'string|max:64',
            'enable' => 'nullable|array',
            'enable.*' => 'string|max:64',
            'disable' => 'nullable|array',
            'disable.*' => 'string|max:64',
            'install' => 'nullable|string|max:64',
            'replace' => 'nullable|boolean',
        ]);

        $license = $siteProvision->license()->firstOrFail();
        $meta = is_array($license->meta) ? $license->meta : [];
        $current = $meta['modules'] ?? [];
        if (! is_array($current)) {
            $current = [];
        }
        $current = array_values(array_unique(array_filter(array_map('strval', $current))));

        if (! empty($data['replace']) && isset($data['modules'])) {
            $current = array_values(array_unique(array_filter(array_map('strval', $data['modules']))));
        } else {
            foreach ($data['enable'] ?? [] as $slug) {
                $current[] = (string) $slug;
            }
            $disable = array_map('strval', $data['disable'] ?? []);
            $current = array_values(array_filter(
                array_unique($current),
                fn ($s) => ! in_array($s, $disable, true)
            ));
            if (isset($data['modules']) && is_array($data['modules'])) {
                $current = array_values(array_unique(array_merge($current, array_map('strval', $data['modules']))));
            }
        }

        $meta['modules'] = $current;
        $license->meta = $meta;
        $license->save();
        \Modules\Core\Services\CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        $installResult = null;
        if (! empty($data['install'])) {
            try {
                $installResult = $orchestrator->callTenantApi($siteProvision, 'provision/modules/install', [
                    'slug' => $data['install'],
                ]);
            } catch (Throwable $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'data' => $siteProvision->fresh(['license']),
                ], 422);
            }
        }

        try {
            $orchestrator->callTenantApi($siteProvision, 'provision/license-sync', []);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'data' => $siteProvision->fresh(['license', 'package', 'crmAccount']),
            'install' => $installResult,
        ]);
    }

    public function setChannel(Request $request, WebinoSiteProvision $siteProvision): JsonResponse
    {
        if (! $this->isControlEditable($siteProvision)) {
            return response()->json([
                'message' => 'سایت هنوز آماده/SSL نیست: '.$siteProvision->status,
            ], 422);
        }

        $data = $request->validate([
            'channel' => 'required|string|in:beta,stable,latest',
        ]);

        if ($data['channel'] === 'stable') {
            return response()->json(['message' => 'کانال استیبل هنوز در دسترس نیست.'], 503);
        }

        $wizard = $siteProvision->wizard_payload ?? [];
        $wizard['channel'] = $data['channel'];
        $siteProvision->update(['wizard_payload' => $wizard]);

        if ($data['channel'] === 'beta') {
            \Modules\SiteBuilder\Jobs\UpdateWebinoSiteJob::dispatch($siteProvision->id, 'full');
            $wizard = $siteProvision->wizard_payload ?? [];
            $wizard['update'] = [
                'target' => 'full',
                'status' => 'queued',
                'started_at' => now()->toIso8601String(),
            ];
            $siteProvision->update(['wizard_payload' => $wizard]);
        }

        return response()->json(['data' => $siteProvision->fresh(['license', 'crmAccount'])]);
    }

    public function queueUpdate(Request $request, WebinoSiteProvision $siteProvision): JsonResponse
    {
        if (! $this->isControlEditable($siteProvision)) {
            return response()->json([
                'message' => 'سایت هنوز آماده/SSL نیست: '.$siteProvision->status,
            ], 422);
        }

        $data = $request->validate([
            'target' => 'required|string|in:frontend,backend,migrate,full',
        ]);

        $wizard = $siteProvision->wizard_payload ?? [];
        $wizard['update'] = [
            'target' => $data['target'],
            'status' => 'queued',
            'started_at' => now()->toIso8601String(),
        ];
        $siteProvision->update(['wizard_payload' => $wizard]);

        \Modules\SiteBuilder\Jobs\UpdateWebinoSiteJob::dispatch($siteProvision->id, $data['target']);

        return response()->json([
            'data' => $siteProvision->fresh(['license']),
            'message' => 'Update queued.',
        ]);
    }

    public function destroy(WebinoSiteProvision $siteProvision, SiteProvisionOrchestrator $orchestrator): JsonResponse
    {
        if (in_array($siteProvision->status, [
            WebinoSiteProvision::STATUS_PROVISIONING,
            WebinoSiteProvision::STATUS_READY,
            WebinoSiteProvision::STATUS_SSL_PENDING,
        ], true)) {
            $orchestrator->rollback($siteProvision);
        } else {
            $siteProvision->delete();
        }

        return response()->json(['message' => 'Deleted']);
    }

    protected function uniqueSlug(?string $requested, mixed $siteName, ?int $ignoreId = null): string
    {
        $fromRequest = $this->normalizeSlug((string) ($requested ?? ''));
        $fromName = $this->normalizeSlug(is_string($siteName) ? $siteName : '');
        $base = $fromRequest !== '' ? $fromRequest : $fromName;
        if ($base === '' || $base === '-') {
            $base = 'site-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $i = 2;
        while (
            WebinoSiteProvision::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
            if ($i > 50) {
                $slug = $base.'-'.Str::lower(Str::random(4));
                break;
            }
        }

        return $slug;
    }

    protected function normalizeSlug(string $value): string
    {
        $slug = Str::slug($value);
        if ($slug !== '') {
            return $slug;
        }

        $ascii = strtolower(trim($value));
        $ascii = preg_replace('/[\s_]+/', '-', $ascii) ?? '';
        $ascii = preg_replace('/[^a-z0-9-]/', '', $ascii) ?? '';
        $ascii = trim(preg_replace('/-+/', '-', $ascii) ?? '', '-');

        return $ascii;
    }

    protected function resolveDomain(string $slug, bool $usesCustom, mixed $customDomain): string
    {
        if ($usesCustom && is_string($customDomain) && trim($customDomain) !== '') {
            return strtolower(trim($customDomain));
        }

        return $slug.'.'.$this->platformBaseDomain();
    }

    protected function platformBaseDomain(): string
    {
        try {
            $row = CoreHostingSetting::query()->first();
            $domain = $row?->getAttributes()['platform_base_domain'] ?? null;
            if (is_string($domain) && trim($domain) !== '') {
                return trim($domain);
            }
        } catch (Throwable) {
            /* settings table / decrypt may be unavailable */
        }

        return 'webinaagency.ir';
    }

    protected function isControlEditable(WebinoSiteProvision $siteProvision): bool
    {
        return in_array($siteProvision->status, [
            WebinoSiteProvision::STATUS_READY,
            WebinoSiteProvision::STATUS_SSL_PENDING,
        ], true);
    }
}
