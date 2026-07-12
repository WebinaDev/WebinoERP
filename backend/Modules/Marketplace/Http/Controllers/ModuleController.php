<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Marketplace\Entities\MarketplaceModule;
use Modules\Marketplace\Entities\MarketplaceModuleRepo;
use Modules\Marketplace\Entities\MarketplaceRelease;

class ModuleController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            MarketplaceModule::query()->with(['repo', 'releases'])->orderByDesc('updated_at')->paginate($this->perPage($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:marketplace_modules,slug',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'price' => 'nullable|numeric|min:0',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $module = MarketplaceModule::create($data);

        return response()->json(['data' => $module, 'message' => 'Module created'], 201);
    }

    public function show(MarketplaceModule $module): JsonResponse
    {
        $module->load(['repo', 'releases']);

        return response()->json(['data' => $module]);
    }

    public function update(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:20',
            'category_id' => 'nullable|exists:marketplace_categories,id',
            'price' => 'nullable|numeric|min:0',
        ]);
        $module->update($data);

        return response()->json(['data' => $module->fresh(['repo', 'releases']), 'message' => 'Module updated']);
    }

    public function destroy(MarketplaceModule $module): JsonResponse
    {
        $module->delete();

        return response()->noContent();
    }

    public function attachRepo(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate([
            'repo_url' => 'required|url',
            'repo_branch' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:255',
        ]);
        $repo = MarketplaceModuleRepo::query()->updateOrCreate(['module_id' => $module->id], $data);

        return response()->json(['data' => $repo, 'message' => 'Repo linked'], 201);
    }

    public function syncRepo(MarketplaceModule $module): JsonResponse
    {
        $repo = MarketplaceModuleRepo::query()->firstOrCreate(['module_id' => $module->id]);
        $repo->update(['last_synced_at' => now()]);

        return response()->json(['data' => $repo->fresh(), 'message' => 'Repo sync queued']);
    }

    public function patchRepo(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate([
            'repo_url' => 'sometimes|url',
            'repo_branch' => 'nullable|string|max:100',
            'gitea_repo' => 'nullable|string|max:255',
        ]);
        $repo = MarketplaceModuleRepo::query()->updateOrCreate(['module_id' => $module->id], $data);

        return response()->json(['data' => $repo, 'message' => 'Repo updated']);
    }

    public function syncReadme(MarketplaceModule $module): JsonResponse
    {
        return response()->json(['data' => ['module_id' => $module->id, 'synced' => true], 'message' => 'Readme sync queued']);
    }

    public function releasesIndex(MarketplaceModule $module): JsonResponse
    {
        return response()->json(['data' => $module->releases()->orderByDesc('id')->get()]);
    }

    public function releasesStore(Request $request, MarketplaceModule $module): JsonResponse
    {
        $data = $request->validate([
            'version' => 'required|string|max:50',
            'changelog' => 'nullable|string',
        ]);
        $release = $module->releases()->create([...$data, 'status' => 'draft']);

        return response()->json(['data' => $release, 'message' => 'Release created'], 201);
    }
}
