<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformProject;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PlatformProject::query()->with(['environments'])->latest()->get();
        return $this->ok($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:500',
            'crm_account_id' => 'nullable|integer',
        ]);
        $project = PlatformProject::query()->create($data);
        PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'production']);
        PlatformEnvironment::query()->create(['project_id' => $project->id, 'name' => 'staging']);
        return $this->ok($project->load('environments'), 201);
    }

    public function show(PlatformProject $project): JsonResponse
    {
        return $this->ok($project->load(['environments']));
    }

    public function update(Request $request, PlatformProject $project): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'description' => 'nullable|string|max:500',
            'crm_account_id' => 'nullable|integer',
        ]);
        $project->update($data);
        return $this->ok($project->fresh('environments'));
    }

    public function destroy(PlatformProject $project): JsonResponse
    {
        $project->delete();
        return $this->ok(['deleted' => true]);
    }

    public function storeEnvironment(Request $request, PlatformProject $project): JsonResponse
    {
        $name = $request->validate(['name' => 'required|string|max:64'])['name'];
        $env = PlatformEnvironment::query()->firstOrCreate(['project_id' => $project->id, 'name' => $name]);
        return $this->ok($env, 201);
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => null, 'meta' => null, 'errors' => null], $status);
    }
}
