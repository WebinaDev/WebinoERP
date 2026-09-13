<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Services\OrgGit\AbstractOrgGitProvider;
use Modules\Marketplace\Entities\MarketplaceRelease;
use Modules\Marketplace\Services\MarketplaceGiteaClient;
use ZipArchive;

class ReleaseController extends Controller
{
    public function publish(MarketplaceRelease $release, MarketplaceGiteaClient $gitea): JsonResponse
    {
        $release->loadMissing('module.repo');
        $module = $release->module;
        if (! $module) {
            return response()->json(['message' => 'Release module missing'], 422);
        }

        $tag = (string) ($release->tag_name ?: $release->version);
        if ($tag === '') {
            return response()->json(['message' => 'Release version/tag is required'], 422);
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

        if ($packagePath && is_readable($packagePath)) {
            $zipError = $this->validateZipBasics($packagePath);
            if ($zipError !== null) {
                return response()->json(['message' => $zipError], 422);
            }
        }

        $release->update([
            'status' => 'published',
            'published_at' => now(),
            'tag_name' => $release->tag_name ?: $tag,
            'package_path' => $packagePath,
            'package_source' => $packageSource,
        ]);

        $module->update([
            'status' => 'published',
            'version' => $release->version,
            'latest_release_id' => $release->id,
            'package_path' => $packagePath ?: $module->package_path,
            'package_source' => $packageSource ?: $module->package_source,
        ]);

        return response()->json(['data' => $release->fresh('module'), 'message' => 'Release published']);
    }

    public function destroy(MarketplaceRelease $release): Response
    {
        $release->delete();

        return response()->noContent();
    }

    protected function validateZipBasics(string $path): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return 'Package is not a valid ZIP archive.';
        }

        $hasEntry = $zip->numFiles > 0;
        $zip->close();

        if (! $hasEntry) {
            return 'Package ZIP is empty.';
        }

        return null;
    }
}
