<?php

namespace Modules\Projects\Http\Controllers;

use App\Models\User;
use App\Support\CustomerAccess;
use App\Support\StaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Projects\Entities\PrjTaskTemplate;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\TaskComment;
use Modules\Projects\Entities\TaskLink;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;
use Modules\Projects\Support\StatusMachine;

class TaskController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $query = ProjectTask::query()->orderByDesc('created_at');
        $access->scopeTasks($query, $request->user());
        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->input('project_id'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', (int) $request->input('assignee_id'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('label')) {
            $query->where('label', 'like', '%'.$request->string('label').'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
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
        StatusMachine::assert(null, $data['status'], 'task');
        $data['workflow_status_id'] = $data['workflow_status_id'] ?? $this->defaultWorkflowStatusId();
        $task = ProjectTask::query()->create($data);

        return response()->json(['data' => $task], 201);
    }

    public function quick(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    public function updateStatus(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $task = $this->visibleTask($request, $id, $access);
        $data = $request->validate([
            'status' => 'nullable|string|max:50',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
        ]);
        if (! empty($data['status'])) {
            StatusMachine::assert($task->status, $data['status'], 'task');
        }
        $task->update($data);

        return response()->json(['data' => $task->fresh()]);
    }

    public function show(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $task = $this->visibleTask($request, $id, $access);
        $comments = TaskComment::query()
            ->where('task_id', $id)
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (TaskComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user_id' => $c->user_id,
                'user_name' => $c->user?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);
        $attachments = DB::table('prj_task_attachments')->where('task_id', $id)->get()->map(fn ($row) => [
            'id' => $row->id,
            'original_name' => $row->original_name,
            'path' => $row->path,
            'url' => Storage::disk($row->disk)->url($row->path),
            'size_bytes' => $row->size_bytes,
            'created_at' => $row->created_at,
        ]);

        $links = TaskLink::query()
            ->where('source_task_id', $id)
            ->with('targetTask:id,title')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'task' => $task,
                'comments' => $comments,
                'attachments' => $attachments,
                'links' => $links,
                'dependencies' => $links
                    ->where('link_type', 'depends')
                    ->map(fn (TaskLink $link) => [
                        'link_id' => $link->id,
                        'id' => $link->target_task_id,
                        'title' => $link->targetTask?->title,
                    ])
                    ->values(),
            ],
        ]);
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
        $this->notifyMentions($request->user()->id, $id, $data['body']);

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

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $task = $this->visibleTask($request, $id, $access);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:20',
            'label' => 'nullable|string|max:100',
            'project_id' => 'nullable|exists:prj_projects,id',
            'assignee_id' => 'nullable|exists:users,id',
            'due_at' => 'nullable|date',
            'starts_at' => 'nullable|date',
            'estimate_hours' => 'nullable|numeric|min:0|max:1000',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
            'epic_id' => 'nullable|exists:prj_epics,id',
            'sprint_id' => 'nullable|exists:prj_sprints,id',
            'recurrence' => 'nullable|array',
            'recurrence.repeat' => 'nullable|in:daily,weekly,monthly',
            'recurrence.interval' => 'nullable|integer|min:1|max:365',
            'dependencies' => 'sometimes|array',
            'dependencies.*' => 'integer|exists:prj_tasks,id',
        ]);
        if (! empty($data['status'])) {
            StatusMachine::assert($task->status, $data['status'], 'task');
        }
        if (array_key_exists('recurrence', $data)) {
            $repeat = is_array($data['recurrence']) ? ($data['recurrence']['repeat'] ?? null) : null;
            if (! $repeat) {
                $data['recurrence'] = null;
            }
        }
        $dependencyIds = null;
        if ($request->exists('dependencies')) {
            $dependencyIds = collect($data['dependencies'] ?? [])
                ->map(fn ($targetId) => (int) $targetId)
                ->unique()
                ->reject(fn (int $targetId) => $targetId === (int) $task->id)
                ->values();
            unset($data['dependencies']);
        }
        $task->update($data);
        if ($dependencyIds !== null) {
            $this->syncDependencies($task->id, $dependencyIds->all());
        }

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

    public function search(Request $request, CustomerAccess $access): JsonResponse
    {
        $q = ProjectTask::query()->orderByDesc('id')->limit(30);
        $access->scopeTasks($q, $request->user());
        if ($request->filled('q')) {
            $s = '%'.$request->string('q').'%';
            $q->where('title', 'like', $s);
        }

        return response()->json(['data' => $q->get()]);
    }

    public function bulkEdit(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:prj_tasks,id',
            'assignee_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|max:50',
        ]);
        $count = 0;
        $tasks = ProjectTask::query()->whereIn('id', $data['ids'])->get();
        foreach ($tasks as $task) {
            if (! empty($data['status'])) {
                StatusMachine::assert($task->status, $data['status'], 'task');
                $task->status = $data['status'];
            }
            if (! empty($data['assignee_id'])) {
                $task->assignee_id = $data['assignee_id'];
            }
            $task->save();
            $count++;
        }

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
        $q = ProjectTask::query()->whereNotNull('due_at')->orderBy('due_at')->limit(500);
        if ($request->filled('start')) {
            $q->whereDate('due_at', '>=', $request->input('start'));
        }
        if ($request->filled('end')) {
            $q->whereDate('due_at', '<=', $request->input('end'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', (int) $request->input('project_id'));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function gantt(Request $request): JsonResponse
    {
        $q = ProjectTask::query()->orderBy('due_at')->limit(200);
        if ($request->filled('project_id')) {
            $q->where('project_id', (int) $request->input('project_id'));
        }
        $tasks = $q->get();
        $links = TaskLink::query()
            ->whereIn('source_task_id', $tasks->pluck('id'))
            ->where('link_type', 'depends')
            ->get()
            ->groupBy('source_task_id');
        $data = $tasks->map(function (ProjectTask $task) use ($links) {
            $row = $task->toArray();
            $row['depends_on'] = ($links[$task->id] ?? collect())->pluck('target_task_id')->map(fn ($id) => (int) $id)->values();

            return $row;
        });

        return response()->json(['data' => $data]);
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

    /**
     * @param  list<int>  $targetIds
     */
    private function syncDependencies(int $taskId, array $targetIds): void
    {
        $existing = TaskLink::query()
            ->where('source_task_id', $taskId)
            ->where('link_type', 'depends');

        if ($targetIds === []) {
            $existing->delete();

            return;
        }

        $existing->whereNotIn('target_task_id', $targetIds)->delete();

        foreach ($targetIds as $targetId) {
            TaskLink::query()->updateOrCreate(
                [
                    'source_task_id' => $taskId,
                    'target_task_id' => $targetId,
                    'link_type' => 'depends',
                ],
                []
            );
        }
    }

    private function notifyMentions(int $authorId, int $taskId, string $body): void
    {
        if (! preg_match_all('/@([\p{L}\p{N}._-]{2,})/u', $body, $matches)) {
            return;
        }
        $tokens = array_unique($matches[1]);
        $users = User::query()
            ->where('id', '!=', $authorId)
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('name', $token)->orWhere('email', 'like', $token.'@%');
                }
            })
            ->get();
        foreach ($users as $user) {
            StaffNotifier::notify((int) $user->id, 'pm.mention', 'اشاره در وظیفه', $body, [
                'task_id' => $taskId,
            ]);
        }
    }

    private function visibleTask(Request $request, int $id, CustomerAccess $access): ProjectTask
    {
        $query = ProjectTask::query()->whereKey($id);
        $access->scopeTasks($query, $request->user());

        return $query->firstOrFail();
    }
}
