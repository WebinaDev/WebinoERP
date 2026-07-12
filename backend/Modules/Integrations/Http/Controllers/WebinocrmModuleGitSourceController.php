<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Integrations\Jobs\PropagateModuleGitRepositoryJob;

class WebinocrmModuleGitSourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ModuleGitSource::query()->orderBy('slug')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:64|regex:/^[a-z0-9_]+$/|unique:module_git_sources,slug',
            'clone_url' => 'required|string|max:2048',
            'auth_type' => 'nullable|string|max:32|in:none,deploy_token,ssh',
            'credential_ref' => 'nullable|string|max:255',
        ]);

        $row = ModuleGitSource::query()->create([
            'slug' => $data['slug'],
            'clone_url' => $data['clone_url'],
            'auth_type' => $data['auth_type'] ?? 'none',
            'credential_ref' => $data['credential_ref'] ?? null,
        ]);

        PropagateModuleGitRepositoryJob::dispatch($row->slug);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = ModuleGitSource::query()->findOrFail($id);
        $data = $request->validate([
            'clone_url' => 'sometimes|string|max:2048',
            'auth_type' => 'sometimes|string|max:32|in:none,deploy_token,ssh',
            'credential_ref' => 'sometimes|nullable|string|max:255',
        ]);
        $row->update($data);

        PropagateModuleGitRepositoryJob::dispatch($row->slug);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $slug = (string) (ModuleGitSource::query()->whereKey($id)->value('slug') ?? '');
        ModuleGitSource::query()->whereKey($id)->delete();
        if ($slug !== '') {
            PropagateModuleGitRepositoryJob::dispatch($slug);
        }

        return response()->json([], 204);
    }
}
