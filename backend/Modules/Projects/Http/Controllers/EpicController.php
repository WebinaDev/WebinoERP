<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjEpic;
use Modules\Projects\Entities\Project;

class EpicController extends Controller
{
    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $project = $this->project($request, $request->integer('project_id'), $access);
        $rows = PrjEpic::query()
            ->where('project_id', $project->id)
            ->withCount('tasks')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $data = $request->validate([
            'project_id' => 'required|exists:prj_projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'state' => 'nullable|string|max:50',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);
        $this->project($request, (int) $data['project_id'], $access);
        $data['state'] = $data['state'] ?? 'open';
        $epic = PrjEpic::query()->create($data);

        return response()->json(['data' => $epic], 201);
    }

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $epic = PrjEpic::query()->findOrFail($id);
        $this->project($request, (int) $epic->project_id, $access);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'state' => 'nullable|string|max:50',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);
        $epic->update($data);

        return response()->json(['data' => $epic->fresh()]);
    }

    public function destroy(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $epic = PrjEpic::query()->findOrFail($id);
        $this->project($request, (int) $epic->project_id, $access);
        $epic->delete();

        return response()->json([], 204);
    }

    private function project(Request $request, int $id, CustomerAccess $access): Project
    {
        $query = Project::query()->whereKey($id);
        $access->scopeProjects($query, $request->user());

        return $query->firstOrFail();
    }
}
