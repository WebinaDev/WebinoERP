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

    public function update(Request $request, WebinoSiteProvision $siteProvision): JsonResponse
    {
        if (! in_array($siteProvision->status, [WebinoSiteProvision::STATUS_DRAFT, WebinoSiteProvision::STATUS_PENDING], true)) {
            return response()->json(['message' => 'Provision cannot be edited in current status.'], 422);
        }

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
}
