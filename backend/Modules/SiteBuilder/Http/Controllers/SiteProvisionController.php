<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use Modules\SiteBuilder\Services\SiteProvisionAuditLogger;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;

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
            'crm_account_id' => 'nullable|exists:crm_accounts,id',
            'package_id' => 'nullable|exists:webino_packages,id',
            'slug' => 'nullable|string|max:64|regex:/^[a-z0-9-]+$/|unique:webino_site_provisions,slug',
            'wizard_payload' => 'nullable|array',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['wizard_payload']['site_name'] ?? 'site-'.Str::random(6));
        $settings = CoreHostingSetting::current();
        $baseDomain = $settings->platform_base_domain ?: 'webina.local';
        $usesCustom = (bool) ($data['wizard_payload']['uses_custom_domain'] ?? false);
        $domain = $usesCustom
            ? ($data['wizard_payload']['custom_domain'] ?? $slug.'.'.$baseDomain)
            : $slug.'.'.$baseDomain;

        $row = WebinoSiteProvision::query()->create([
            'crm_account_id' => $data['crm_account_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'slug' => $slug,
            'domain' => $domain,
            'subdomain' => $usesCustom ? null : $slug,
            'uses_custom_domain' => $usesCustom,
            'status' => WebinoSiteProvision::STATUS_DRAFT,
            'wizard_payload' => $data['wizard_payload'] ?? [],
            'provision_token' => Str::random(48),
            'created_by' => $request->user()?->id,
        ]);

        app(SiteProvisionAuditLogger::class)->log($request->user()?->id, 'provision.created', $row);

        return response()->json(['data' => $row], 201);
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
            'crm_account_id' => 'nullable|exists:crm_accounts,id',
            'package_id' => 'nullable|exists:webino_packages,id',
            'slug' => 'sometimes|string|max:64|regex:/^[a-z0-9-]+$/|unique:webino_site_provisions,slug,'.$siteProvision->id,
            'wizard_payload' => 'nullable|array',
            'uses_custom_domain' => 'nullable|boolean',
        ]);

        $wizard = array_merge($siteProvision->wizard_payload ?? [], $data['wizard_payload'] ?? []);
        unset($data['wizard_payload']);

        if (isset($data['slug']) || isset($data['uses_custom_domain']) || isset($wizard['uses_custom_domain'])) {
            $settings = CoreHostingSetting::current();
            $baseDomain = $settings->platform_base_domain ?: 'webina.local';
            $slug = $data['slug'] ?? $siteProvision->slug;
            $usesCustom = $data['uses_custom_domain'] ?? $wizard['uses_custom_domain'] ?? $siteProvision->uses_custom_domain;
            $data['domain'] = $usesCustom
                ? ($wizard['custom_domain'] ?? $siteProvision->domain)
                : $slug.'.'.$baseDomain;
            $data['subdomain'] = $usesCustom ? null : $slug;
            $data['uses_custom_domain'] = $usesCustom;
        }

        $data['wizard_payload'] = $wizard;
        $siteProvision->update($data);

        return response()->json(['data' => $siteProvision->fresh(['license', 'package', 'crmAccount'])]);
    }

    public function prepareLicense(WebinoSiteProvision $siteProvision, LicenseProvisionerService $licenses, Request $request): JsonResponse
    {
        if (! $siteProvision->package_id) {
            return response()->json(['message' => 'Package is required.'], 422);
        }

        $siteProvision->load(['package.businessType.category', 'package.features']);

        if (! $siteProvision->license_id) {
            $payload = $siteProvision->wizard_payload ?? [];
            $license = $licenses->createForProvision(
                $siteProvision->domain,
                $siteProvision->package,
                [
                    'selected_feature_slugs' => $payload['selected_feature_slugs'] ?? [],
                    'extra_module_slugs' => $payload['extra_module_slugs'] ?? ['dashboard', 'modules'],
                ],
                $request->user()?->id,
            );
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
        ], true)) {
            return response()->json(['message' => 'Provision already launched.'], 422);
        }

        if (! $siteProvision->package_id) {
            return response()->json(['message' => 'Package is required.'], 422);
        }

        $siteProvision->update(['status' => WebinoSiteProvision::STATUS_PENDING]);
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

    public function retry(WebinoSiteProvision $siteProvision): JsonResponse
    {
        if ($siteProvision->status !== WebinoSiteProvision::STATUS_FAILED) {
            return response()->json(['message' => 'Only failed provisions can be retried.'], 422);
        }

        $siteProvision->update(['status' => WebinoSiteProvision::STATUS_PENDING, 'error_log' => null]);
        ProvisionWebinoSiteJob::dispatch($siteProvision->id);

        return response()->json(['data' => $siteProvision, 'message' => 'Retry queued.']);
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
}
