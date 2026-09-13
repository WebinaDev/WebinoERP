<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Core\Services\OrgGit\AbstractOrgGitProvider;
use Modules\Core\Services\OrgGit\OrgGitProviderFactory;
use Modules\Marketplace\Entities\MarketplaceCategory;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceModuleRepo;
use Modules\Marketplace\Services\MarketplaceGiteaClient;

class ModuleController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $wantsAdminList = $request->boolean('include_core')
            || $request->boolean('list')
            || $request->query('format') === 'admin'
            || ! $request->has('page');

        $query = MarketplaceModule::query()
            ->with(['repo', 'releases', 'gitSource', 'category', 'parent', 'children'])
            ->orderBy('sort')
            ->orderByDesc('updated_at');

        if (! $request->boolean('include_core')) {
            $query->where(function ($q) {
                $q->where('is_core', false)->orWhereNull('is_core');
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($wantsAdminList) {
            $modules = $query->get();
            $categories = MarketplaceCategory::query()->orderBy('sort')->orderBy('name')->get();

            return response()->json([
                'data' => [
                    'modules' => $modules,
                    'categories' => $categories,
                ],
            ]);
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->storeRules());
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['distribution'] = $data['distribution'] ?? 'git';
        $data['requires_license'] = $data['requires_license'] ?? ($data['distribution'] === 'git');
        $data['is_free'] = $data['is_free'] ?? ((float) ($data['price'] ?? 0) <= 0);
        $data['currency'] = $data['currency'] ?? 'IRT';
        $data['settings_area'] = $data['settings_area'] ?? 'shop';
        $data['package_source'] = $data['package_source'] ?? 'local';
        $data['version'] = $data['version'] ?? '1.0.0';
        $status = $data['status'] ?? 'draft';
        if ($data['distribution'] === 'git'
            && in_array($status, ['published', 'active'], true)
            && empty($data['module_git_source_id'])
            && empty($data['gitea_repo'])) {
            return response()->json(['message' => 'Git-distributed modules require module_git_source_id or gitea_repo.'], 422);
        }
        $module = MarketplaceModule::create($data);

        return response()->json(['data' => $module->fresh(['repo', 'releases', 'category']), 'message' => 'Module created'], 201);
    }

    public function show(MarketplaceModule $module): JsonResponse
    {
        $module->load(['repo', 'releases', 'gitSource', 'category', 'parent', 'children']);

        return response()->json(['data' => $module]);
    }

    public function update(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate($this->updateRules($module));
        $distribution = $data['distribution'] ?? $module->distribution ?? 'git';
        $gitSourceId = array_key_exists('module_git_source_id', $data)
            ? $data['module_git_source_id']
            : $module->module_git_source_id;
        $giteaRepo = array_key_exists('gitea_repo', $data) ? $data['gitea_repo'] : $module->gitea_repo;
        $status = $data['status'] ?? $module->status;
        if ($distribution === 'git'
            && in_array($status, ['published', 'active'], true)
            && empty($gitSourceId)
            && empty($giteaRepo)) {
            return response()->json(['message' => 'Git-distributed modules require module_git_source_id or gitea_repo.'], 422);
        }
        if (array_key_exists('distribution', $data) && ! array_key_exists('requires_license', $data)) {
            $data['requires_license'] = $data['distribution'] === 'git';
        }
        $module->update($data);

        return response()->json(['data' => $module->fresh(['repo', 'releases', 'category']), 'message' => 'Module updated']);
    }

    public function destroy(Request $request, MarketplaceModule $module, MarketplaceGiteaClient $gitea): JsonResponse|Response
    {
        if ($module->is_core) {
            return response()->json(['message' => 'Core modules cannot be deleted'], 422);
        }

        if ($request->boolean('delete_gitea_repo') && ($module->package_source === 'gitea' || $module->gitea_repo)) {
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
                $gitea->deleteRepo($owner, $repo);
            }
        }

        $module->delete();

        return response()->noContent();
    }

    public function attachRepo(Request $request, MarketplaceModule $module, MarketplaceGiteaClient $gitea): JsonResponse
    {
        $data = $request->validate([
            'repo_url' => 'nullable|url',
            'repo_branch' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:255',
            'create' => 'nullable|boolean',
            'private' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($request->boolean('create') || (empty($data['repo_url']) && ! empty($data['gitea_repo']))) {
            $name = (string) ($data['gitea_repo'] ?: $module->slug);
            $created = $gitea->createRepo($name, $data['description'] ?? (string) $module->description, $data['private'] ?? true);
            if (! ($created['ok'] ?? false)) {
                return response()->json(['message' => $created['message'] ?? 'Failed to create repo'], 422);
            }
            $repoData = $created['data'] ?? [];
            $fullName = (string) ($repoData['full_name'] ?? '');
            $cloneUrl = (string) ($repoData['clone_url'] ?? '');
            $data['repo_url'] = $cloneUrl ?: ($data['repo_url'] ?? null);
            $data['gitea_repo'] = $fullName ?: ($data['gitea_repo'] ?? $name);
            $module->update([
                'gitea_owner' => $repoData['owner']['login'] ?? $module->gitea_owner,
                'gitea_repo' => $repoData['name'] ?? $module->gitea_repo ?? $name,
                'gitea_repo_id' => $repoData['id'] ?? $module->gitea_repo_id,
                'package_source' => 'gitea',
            ]);
        }

        if (empty($data['repo_url'])) {
            return response()->json(['message' => 'repo_url is required unless create=1'], 422);
        }

        unset($data['create'], $data['private'], $data['description']);
        $repo = MarketplaceModuleRepo::query()->updateOrCreate(['module_id' => $module->id], $data);

        return response()->json([
            'data' => [
                'repo' => $repo,
                'module' => $module->fresh(['repo', 'releases', 'category']),
            ],
            'message' => 'Repo linked',
        ], 201);
    }

    public function syncRepo(MarketplaceModule $module, OrgGitProviderFactory $factory): JsonResponse
    {
        $repo = MarketplaceModuleRepo::query()->firstOrCreate(['module_id' => $module->id]);
        $cloneUrl = $repo->repo_url ?: $module->gitSource?->clone_url;
        if (! is_string($cloneUrl) || $cloneUrl === '') {
            return response()->json(['message' => 'No repo_url configured for module'], 422);
        }

        try {
            $provider = $factory->make($repo->provider);
            $parsed = AbstractOrgGitProvider::parseOwnerRepo($repo->gitea_repo ?: $cloneUrl);
            if (! $parsed) {
                return response()->json(['message' => 'Unable to parse owner/repo from URL'], 422);
            }

            $branch = $provider->getDefaultBranch($parsed['owner'], $parsed['repo'])
                ?: ($repo->repo_branch ?: 'main');
            $tag = $provider->getLatestTag($parsed['owner'], $parsed['repo']);
            $readme = $provider->getReadme($parsed['owner'], $parsed['repo'], $branch);

            $repo->update([
                'provider' => $provider->provider(),
                'default_branch' => $branch,
                'repo_branch' => $branch,
                'latest_tag' => $tag,
                'readme_excerpt' => $readme,
                'last_synced_at' => now(),
                'repo_url' => $repo->repo_url ?: $provider->resolveCloneUrl($parsed['owner'], $parsed['repo']),
            ]);

            $module->update([
                'gitea_owner' => $parsed['owner'],
                'gitea_repo' => $parsed['repo'],
                'readme_md' => $readme ?: $module->readme_md,
            ]);

            if ($module->gitSource) {
                $module->gitSource->update([
                    'clone_url' => $repo->repo_url ?: $module->gitSource->clone_url,
                ]);
            }

            return response()->json([
                'data' => [
                    'repo' => $repo->fresh(),
                    'module' => $module->fresh(['repo', 'releases', 'category']),
                ],
                'message' => 'Repo synced',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function patchRepo(Request $request, MarketplaceModule $module, MarketplaceGiteaClient $gitea): JsonResponse
    {
        $data = $request->validate([
            'repo_url' => 'sometimes|url',
            'repo_branch' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:255',
            'provider' => 'nullable|in:gitea,github,gitlab',
            'private' => 'nullable|boolean',
            'visibility' => 'nullable|in:private,public',
        ]);

        $visibilityPrivate = null;
        if (array_key_exists('private', $data)) {
            $visibilityPrivate = (bool) $data['private'];
        } elseif (($data['visibility'] ?? null) === 'private') {
            $visibilityPrivate = true;
        } elseif (($data['visibility'] ?? null) === 'public') {
            $visibilityPrivate = false;
        }
        unset($data['private'], $data['visibility']);

        $repo = MarketplaceModuleRepo::query()->updateOrCreate(['module_id' => $module->id], $data);

        if ($visibilityPrivate !== null) {
            $owner = (string) ($module->gitea_owner ?: '');
            $name = (string) ($module->gitea_repo ?: '');
            if ($owner === '' || $name === '') {
                $parsed = AbstractOrgGitProvider::parseOwnerRepo((string) ($repo->gitea_repo ?: $repo->repo_url ?: ''));
                if ($parsed) {
                    $owner = $parsed['owner'];
                    $name = $parsed['repo'];
                }
            }
            if ($owner !== '' && $name !== '') {
                $result = $gitea->patchRepoVisibility($owner, $name, $visibilityPrivate);
                if (! ($result['ok'] ?? false)) {
                    return response()->json(['message' => $result['message'] ?? 'Failed to update visibility'], 422);
                }
            }
        }

        return response()->json([
            'data' => [
                'repo' => $repo->fresh(),
                'module' => $module->fresh(['repo', 'releases', 'category']),
            ],
            'message' => 'Repo updated',
        ]);
    }

    public function syncReadme(MarketplaceModule $module, OrgGitProviderFactory $factory): JsonResponse
    {
        $repo = $module->repo;
        if (! $repo?->repo_url) {
            return response()->json(['message' => 'No repo linked'], 422);
        }

        try {
            $provider = $factory->make($repo->provider);
            $parsed = AbstractOrgGitProvider::parseOwnerRepo($repo->gitea_repo ?: $repo->repo_url);
            if (! $parsed) {
                return response()->json(['message' => 'Unable to parse owner/repo'], 422);
            }
            $readme = $provider->getReadme($parsed['owner'], $parsed['repo'], $repo->repo_branch);
            $repo->update([
                'readme_excerpt' => $readme,
                'last_synced_at' => now(),
            ]);
            $module->update(['readme_md' => $readme ?: $module->readme_md]);

            return response()->json([
                'data' => [
                    'module_id' => $module->id,
                    'synced' => true,
                    'readme_excerpt' => $readme,
                    'module' => $module->fresh(['repo', 'releases', 'category']),
                ],
                'message' => 'Readme synced',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function releasesIndex(MarketplaceModule $module): JsonResponse
    {
        return response()->json(['data' => $module->releases()->orderByDesc('id')->get()]);
    }

    public function releasesStore(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate([
            'version' => 'required|string|max:50',
            'tag_name' => 'nullable|string|max:100',
            'changelog' => 'nullable|string',
        ]);
        $release = $module->releases()->create([
            ...$data,
            'tag_name' => $data['tag_name'] ?? $data['version'],
            'status' => 'draft',
        ]);

        return response()->json(['data' => $release, 'message' => 'Release created'], 201);
    }

    /** @return array<string, string> */
    protected function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:marketplace_modules,slug',
            'distribution' => 'nullable|in:bundled,git',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string|max:500',
            'detail_url' => 'nullable|string|max:500',
            'settings_route' => 'nullable|string|max:255',
            'settings_area' => 'nullable|string|max:20',
            'readme_md' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'parent_module_id' => 'nullable|exists:marketplace_modules,id',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'sort' => 'nullable|integer',
            'requires_license' => 'nullable|boolean',
            'is_core' => 'nullable|boolean',
            'is_builtin' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'module_git_source_id' => 'nullable|exists:module_git_sources,id',
            'gitea_owner' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:100',
            'gitea_repo_id' => 'nullable|integer',
            'package_path' => 'nullable|string|max:500',
            'package_source' => 'nullable|in:local,gitea',
            'latest_release_id' => 'nullable|integer',
        ];
    }

    /** @return array<string, string> */
    protected function updateRules(MarketplaceModule $module): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:marketplace_modules,slug,'.$module->id,
            'distribution' => 'sometimes|in:bundled,git',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|string|max:500',
            'detail_url' => 'nullable|string|max:500',
            'settings_route' => 'nullable|string|max:255',
            'settings_area' => 'nullable|string|max:20',
            'readme_md' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'status' => 'sometimes|string|max:20',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'parent_module_id' => 'nullable|exists:marketplace_modules,id',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'sort' => 'nullable|integer',
            'requires_license' => 'nullable|boolean',
            'is_core' => 'nullable|boolean',
            'is_builtin' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'module_git_source_id' => 'nullable|exists:module_git_sources,id',
            'gitea_owner' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:100',
            'gitea_repo_id' => 'nullable|integer',
            'package_path' => 'nullable|string|max:500',
            'package_source' => 'nullable|in:local,gitea',
            'latest_release_id' => 'nullable|integer',
        ];
    }
}
