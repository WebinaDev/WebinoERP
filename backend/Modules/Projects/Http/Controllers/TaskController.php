<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Projects\Entities\PrjTaskTemplate;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\TaskComment;
use Modules\Projects\Entities\TaskLink;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;

class TaskController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request): JsonResponse
    {
        $query = ProjectTask::query()->orderByDesc('created_at');
        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'nullable|exists:prj_projects,id',
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|max:50',
            'assignee_id' => 'nullable|exists:users,id',
            'due_at' => 'nullable|date',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'open';
        $data['workflow_status_id'] = $data['workflow_status_id'] ?? $this->defaultWorkflowStatusId();
        $task = ProjectTask::query()->create($data);

        return response()->json(['data' => $task], 201);
    }

    public function quick(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate([
            'status' => 'nullable|string|max:50',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
        ]);
        $task->update($data);

        return response()->json(['data' => $task->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        ProjectTask::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function updateAssignee(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate(['assignee_id' => 'nullable|exists:users,id']);
        $task->update($data);

        return response()->json(['data' => $task->fresh()]);
    }

    public function addComment(Request $request, int $id): JsonResponse
    {
        ProjectTask::query()->findOrFail($id);
        $data = $request->validate(['body' => 'required|string']);
        $c = TaskComment::query()->create([
            'task_id' => $id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $c], 201);
    }

    public function saveContent(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate(['content' => 'nullable|string']);
        $task->update($data);

        return response()->json(['data' => $task]);
    }

    public function manageChecklist(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate(['checklist' => 'required|array']);
        $task->update(['checklist' => $data['checklist']]);

        return response()->json(['data' => $task]);
    }

    public function logTime(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $logs = $task->time_logs ?? [];
        $logs[] = array_merge($request->validate([
            'minutes' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]), ['at' => now()->toIso8601String()]);
        $task->update(['time_logs' => $logs]);

        return response()->json(['data' => ['task_id' => $id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:20',
            'label' => 'nullable|string|max:100',
            'project_id' => 'nullable|exists:prj_projects,id',
            'assignee_id' => 'nullable|exists:users,id',
            'due_at' => 'nullable|date',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
        ]);
        $task->update($data);

        return response()->json(['data' => $task->fresh()]);
    }

    public function addLink(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'target_task_id' => 'required|exists:prj_tasks,id',
            'link_type' => 'nullable|string|max:50',
        ]);
        $link = TaskLink::query()->create([
            'source_task_id' => $id,
            'target_task_id' => $data['target_task_id'],
            'link_type' => $data['link_type'] ?? 'relates',
        ]);

        return response()->json(['data' => ['link_id' => $link->id]], 201);
    }

    public function removeLink(int $id, int $linkId): JsonResponse
    {
        TaskLink::query()->where('source_task_id', $id)->whereKey($linkId)->delete();

        return response()->json([], 204);
    }

    public function search(Request $request): JsonResponse
    {
        $q = ProjectTask::query()->orderByDesc('id')->limit(30);
        if ($request->filled('q')) {
            $s = '%'.$request->string('q').'%';
            $q->where('title', 'like', $s);
        }

        return response()->json(['data' => $q->get()]);
    }

    public function bulkEdit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:prj_tasks,id',
            'assignee_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
        ]);
        $count = ProjectTask::query()->whereIn('id', $data['ids'])->update(array_filter([
            'assignee_id' => $data['assignee_id'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => ['updated' => $count]]);
    }

    public function saveAsTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:prj_projects,id',
            'payload' => 'nullable|array',
        ]);
        $row = PrjTaskTemplate::query()->create(array_merge($data, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function calendar(Request $request): JsonResponse
    {
        $tasks = ProjectTask::query()->whereNotNull('due_at')->orderBy('due_at')->limit(500)->get();

        return response()->json(['data' => $tasks]);
    }

    public function gantt(Request $request): JsonResponse
    {
        return response()->json(['data' => ProjectTask::query()->limit(200)->get()]);
    }

    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        ProjectTask::query()->findOrFail($id);
        $request->validate(['file' => 'required|file|max:10240']);
        $path = $request->file('file')->store('task_attachments', 'public');
        $aid = DB::table('prj_task_attachments')->insertGetId([
            'task_id' => $id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'size_bytes' => $request->file('file')->getSize(),
            'uploaded_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $aid]], 201);
    }

    public function deleteAttachment(int $taskId, int $attachmentId): JsonResponse
    {
        $row = DB::table('prj_task_attachments')->where('task_id', $taskId)->where('id', $attachmentId)->first();
        if ($row) {
            Storage::disk($row->disk)->delete($row->path);
            DB::table('prj_task_attachments')->where('id', $attachmentId)->delete();
        }

        return response()->json([], 204);
    }

    public function attachments(int $id): JsonResponse
    {
        $rows = DB::table('prj_task_attachments')->where('task_id', $id)->get();

        return response()->json(['data' => $rows]);
    }
}
