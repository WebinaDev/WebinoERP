<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Services\OrgGit\AbstractOrgGitProvider;
use Modules\Marketplace\Entities\MarketplaceDistributionAudit;
use Modules\Marketplace\Entities\MarketplaceRelease;
use Modules\Marketplace\Services\MarketplaceGiteaClient;
use Modules\Marketplace\Services\ReleaseDistributionValidator;

class ReleaseController extends Controller
{
    public function publish(
        MarketplaceRelease $release,
        MarketplaceGiteaClient $gitea,
        ReleaseDistributionValidator $validator
    ): JsonResponse {
        $release->loadMissing('module.repo');
        $module = $release->module;
        if (! $module) {
            return response()->json(['message' => __('messages.release_module_missing')], 422);
        }

        $tag = (string) ($release->tag_name ?: $release->version);
        if ($tag === '') {
            return response()->json(['message' => __('messages.release_version_tag_is_required')], 422);
        }

        $packagePath = $release->package_path;
        $packageSource = $release->package_source;

        $canFetchGitea = ($module->package_source === 'gitea' || $module->gitea_repo || $module->repo)
            && (! $packagePath || ! is_readable($packagePath));

        if ($canFetchGitea) {
            $owner = (string) ($module->gitea_owner ?: '');
            $repo = (string) ($module->gitea_repo ?: '');
            if ($owner === '' || $repo === '') {
                $parsed = AbstractOrgGitProvider::parseOwnerRepo(
                    (string) ($module->repo?->gitea_repo ?: $module->repo?->repo_url ?: '')
                );
                if ($parsed) {
                    $owner = $parsed['owner'];
                    $repo = $parsed['repo'];
                }
            }

            if ($owner !== '' && $repo !== '') {
                $archive = $gitea->downloadArchive($owner, $repo, $tag);
                if (! empty($archive['ok']) && ! empty($archive['body'])) {
                    $relative = 'marketplace-packages/'.$module->slug.'-'.$release->version.'.zip';
                    Storage::disk('local')->put($relative, $archive['body']);
                    $packagePath = Storage::disk('local')->path($relative);
                    $packageSource = 'crm_build';
                }
            }
        }

        $sha256 = null;
        $sizeBytes = null;
        $hasClientDist = false;
        $minCore = null;

        if ($packagePath && is_readable($packagePath)) {
            // اعمال قرارداد سخت‌گیرانه بیلد و فایل‌های حیاتی ZIP
            $validation = $validator->inspectAndValidate($packagePath);
            if (! $validation['is_valid']) {
                return response()->json([
                    'message' => 'اعتبارسنجی قرارداد توزیع ناموفق بود.',
                    'errors' => $validation['errors'],
                    'warnings' => $validation['warnings'],
                ], 422);
            }

            $sha256 = $validation['sha256'];
            $sizeBytes = $validation['size_bytes'];
            $hasClientDist = $validation['has_client_dist'];
            $minCore = $validation['manifest_data']['requires_core_min'] ?? ($validation['manifest_data']['min_core_version'] ?? null);

            // ثبت لاگ حسابرسی انتشار پکیج
            MarketplaceDistributionAudit::create([
                'release_id' => $release->id,
                'module_id' => $module->id,
                'user_id' => auth()->id(),
                'action' => 'publish',
                'checksum_sha256' => $sha256,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'validation_details' => [
                    'warnings' => $validation['warnings'],
                    'has_client_dist' => $hasClientDist,
                    'size_bytes' => $sizeBytes,
                ],
            ]);
        }

        $release->update([
            'status' => 'published',
            'published_at' => now(),
            'tag_name' => $release->tag_name ?: $tag,
            'package_path' => $packagePath,
            'package_source' => $packageSource,
            'checksum_sha256' => $sha256,
            'package_size_bytes' => $sizeBytes,
            'has_client_dist' => $hasClientDist,
            'min_core_version' => $minCore,
            'published_by_user_id' => auth()->id(),
        ]);

        $module->update([
            'status' => 'published',
            'version' => $release->version,
            'latest_release_id' => $release->id,
            'package_path' => $packagePath ?: $module->package_path,
            'package_source' => $packageSource ?: $module->package_source,
        ]);

        return response()->json([
            'data' => $release->fresh('module'),
            'message' => __('messages.release_published'),
            'checksum_sha256' => $sha256,
        ]);
    }

    public function destroy(MarketplaceRelease $release): Response
    {
        $release->delete();

        return response()->noContent();
    }
}
