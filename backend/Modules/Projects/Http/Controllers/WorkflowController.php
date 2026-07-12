<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\WorkflowStatus;

class WorkflowController extends Controller
{
    public function saveStatusOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:prj_workflow_statuses,id',
        ]);
        foreach ($data['order'] as $i => $statusId) {
            WorkflowStatus::query()->whereKey($statusId)->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['saved' => true]]);
    }

    public function addStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);
        $row = WorkflowStatus::query()->create($data + ['sort_order' => 99]);

        return response()->json(['data' => $row], 201);
    }

    public function destroyStatus(int $id): JsonResponse
    {
        WorkflowStatus::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }
}
