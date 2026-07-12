<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\PrjSprint;
use Modules\Projects\Entities\PrjSprintTask;
use Modules\Projects\Entities\ProjectTask;

class SprintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PrjSprint::query()->with('project')->orderByDesc('id');
        if ($request->filled('project_id')) {
            $q->where('project_id', $request->integer('project_id'));
        }

        return response()->json(['data' => $q->limit(200)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|exists:prj_projects,id',
            'name' => 'required|string|max:191',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);
        $s = PrjSprint::query()->create($data);

        return response()->json(['data' => $s], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $s = PrjSprint::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);
        $s->update($data);

        return response()->json(['data' => $s->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        PrjSprint::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function backlog(Request $request): JsonResponse
    {
        $q = ProjectTask::query()->whereNull('sprint_id');
        if ($request->filled('project_id')) {
            $q->where('project_id', $request->integer('project_id'));
        }

        return response()->json(['data' => $q->orderByDesc('id')->limit(500)->get()]);
    }

    public function addTask(Request $request, int $id): JsonResponse
    {
        PrjSprint::query()->findOrFail($id);
        $request->validate(['task_id' => 'required|exists:prj_tasks,id']);
        $taskId = (int) $request->input('task_id');
        $maxOrder = 0;
        if (Schema::hasTable('prj_sprint_tasks')) {
            $maxOrder = (int) PrjSprintTask::query()->where('sprint_id', $id)->max('sort_order');
        }

        DB::transaction(function () use ($id, $taskId, $maxOrder) {
            ProjectTask::query()->whereKey($taskId)->update(['sprint_id' => $id]);
            if (Schema::hasTable('prj_sprint_tasks')) {
                PrjSprintTask::query()->updateOrCreate(
                    ['sprint_id' => $id, 'project_task_id' => $taskId],
                    ['sort_order' => $maxOrder + 1]
                );
            }
        });

        return response()->json(['data' => ['sprint_id' => $id]], 201);
    }

    public function removeTask(int $taskId): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($taskId);
        $sid = $task->sprint_id;
        DB::transaction(function () use ($task, $taskId) {
            if (Schema::hasTable('prj_sprint_tasks')) {
                PrjSprintTask::query()
                    ->where('project_task_id', $taskId)
                    ->delete();
            }
            $task->sprint_id = null;
            $task->save();
        });

        return response()->json(['data' => ['removed' => true, 'previous_sprint_id' => $sid]]);
    }

    public function start(int $id): JsonResponse
    {
        $s = PrjSprint::query()->findOrFail($id);
        $s->update(['status' => 'active']);

        return response()->json(['data' => $s->fresh()]);
    }

    public function finish(int $id): JsonResponse
    {
        $s = PrjSprint::query()->with('tasks')->findOrFail($id);
        $tasks = $s->tasks;
        $total = $tasks->count();
        $completed = $tasks->filter(function (ProjectTask $t) {
            $st = strtolower((string) $t->status);

            return str_contains($st, 'done') || str_contains($st, 'complete') || $st === 'closed';
        })->count();

        $s->update(['status' => 'completed']);

        return response()->json([
            'data' => [
                'sprint' => $s->fresh(),
                'burndown' => [
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'remaining_tasks' => max(0, $total - $completed),
                ],
            ],
        ]);
    }
}
