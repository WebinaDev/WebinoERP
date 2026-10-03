<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjMilestone;
use Modules\Projects\Entities\Project;

class MilestoneController extends Controller
{
    public function index(Request $request, int $projectId, CustomerAccess $access): JsonResponse
    {
        $project = $this->project($request, $projectId, $access);

        return response()->json([
            'data' => $project->milestones()->orderBy('due_date')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, int $projectId, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $project = $this->project($request, $projectId, $access);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:open,done',
        ]);
        $milestone = $project->milestones()->create([
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'open',
            'completed_at' => ($data['status'] ?? 'open') === 'done' ? now() : null,
        ]);

        return response()->json(['data' => $milestone], 201);
    }

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $milestone = PrjMilestone::query()->findOrFail($id);
        $this->project($request, (int) $milestone->project_id, $access);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'due_date' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:open,done',
        ]);
        if (($data['status'] ?? null) === 'done' && $milestone->status !== 'done') {
            $data['completed_at'] = now();
        }
        if (($data['status'] ?? null) === 'open') {
            $data['completed_at'] = null;
        }
        $milestone->update($data);

        return response()->json(['data' => $milestone->fresh()]);
    }

    private function project(Request $request, int $id, CustomerAccess $access): Project
    {
        $query = Project::query()->whereKey($id);
        $access->scopeProjects($query, $request->user());

        return $query->firstOrFail();
    }
}
