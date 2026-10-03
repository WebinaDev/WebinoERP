<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Services\ErpAiAssistant;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\CalendarLink;
use Modules\Projects\Entities\GanttBaseline;
use Modules\Projects\Entities\OfflineOp;
use Modules\Projects\Entities\PrjKanbanBoard;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\ResourceCapacity;
use Modules\Projects\Entities\ResourceLeave;
use Modules\Projects\Entities\TaskLink;
use Modules\Projects\Services\CriticalPathService;

class PmPlanningController extends Controller
{
    public function criticalPath(Request $request, CriticalPathService $path): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'nullable|integer',
            'tasks' => 'nullable|array',
            'links' => 'nullable|array',
        ]);
        if (! empty($data['tasks'])) {
            $result = $path->compute($data['tasks'], $data['links'] ?? []);

            return response()->json(['data' => $result]);
        }
        $query = ProjectTask::query()->orderBy('id');
        if (! empty($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        }
        $tasks = $query->limit(300)->get();
        $ids = $tasks->pluck('id');
        $links = TaskLink::query()->whereIn('source_task_id', $ids)->whereIn('target_task_id', $ids)->get();
        $result = $path->compute(
            $tasks->map(fn (ProjectTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'duration_days' => $task->duration_days ?: 1,
            ])->all(),
            $links->map(fn (TaskLink $link) => [
                'source_id' => $link->source_task_id,
                'target_id' => $link->target_task_id,
                'type' => $link->link_type,
            ])->all()
        );

        return response()->json(['data' => $result]);
    }

    public function storeBaseline(Request $request, CriticalPathService $path): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'nullable|integer',
            'name' => 'required|string|max:160',
        ]);
        $request->merge(['project_id' => $data['project_id'] ?? null]);
        $computed = json_decode($this->criticalPath($request, $path)->getContent(), true);
        $snapshot = $computed['data'] ?? $computed;
        if (is_array($snapshot) && isset($snapshot['tasks']) && is_array($snapshot['tasks'])) {
            $snapshot['anchored_on'] = now()->toDateString();
            foreach ($snapshot['tasks'] as &$taskRow) {
                if (! is_array($taskRow)) {
                    continue;
                }
                $model = ProjectTask::query()->find($taskRow['id'] ?? 0);
                $taskRow['start_on'] = $model?->start_at?->toDateString();
            }
            unset($taskRow);
        }
        $row = GanttBaseline::query()->create([
            'project_id' => $data['project_id'] ?? null,
            'name' => $data['name'],
            'snapshot' => $snapshot,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function baselines(Request $request): JsonResponse
    {
        $query = GanttBaseline::query()->orderByDesc('id');
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json(['data' => $query->limit(50)->get()]);
    }

    public function saveCapacity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'weekday' => 'required|integer|min:0|max:6',
            'hours' => 'required|numeric|min:0|max:24',
        ]);
        $row = ResourceCapacity::query()->updateOrCreate(
            ['user_id' => $data['user_id'], 'weekday' => $data['weekday']],
            ['hours' => $data['hours']]
        );

        return response()->json(['data' => $row]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'kind' => 'nullable|string|max:32',
            'note' => 'nullable|string|max:200',
        ]);

        return response()->json(['data' => ResourceLeave::query()->create($data)], 201);
    }

    public function capacity(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfWeek();
        $to = $request->date('to') ?? now()->endOfWeek();
        $userId = $request->integer('user_id') ?: null;
        $caps = ResourceCapacity::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->get()->groupBy('user_id');
        $leaves = ResourceLeave::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereDate('ends_on', '>=', $from)
            ->whereDate('starts_on', '<=', $to)
            ->get()
            ->groupBy('user_id');
        $tasks = ProjectTask::query()
            ->when($userId, fn ($q) => $q->where('assignee_id', $userId))
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to])
            ->get()
            ->groupBy('assignee_id');

        $userIds = $caps->keys()->merge($leaves->keys())->merge($tasks->keys())->unique();
        $rows = [];
        foreach ($userIds as $id) {
            $hours = 0.0;
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $weekday = (int) $cursor->dayOfWeek;
                $cap = optional($caps->get($id))?->firstWhere('weekday', $weekday);
                $dayHours = $cap ? (float) $cap->hours : 8.0;
                $off = ($leaves->get($id) ?? collect())->contains(function (ResourceLeave $leave) use ($cursor) {
                    return $cursor->toDateString() >= $leave->starts_on->toDateString()
                        && $cursor->toDateString() <= $leave->ends_on->toDateString();
                });
                if (! $off) {
                    $hours += $dayHours;
                }
                $cursor->addDay();
            }
            $load = ($tasks->get($id) ?? collect())->sum(fn (ProjectTask $task) => ($task->duration_days ?: 1) * 8);
            $conflicts = $this->conflicts((int) $id, $from->copy(), $to->copy(), $leaves->get($id) ?? collect());
            $rows[] = [
                'user_id' => (int) $id,
                'available_hours' => round($hours, 2),
                'allocated_hours' => round((float) $load, 2),
                'remaining_hours' => round($hours - $load, 2),
                'conflict_count' => count($conflicts),
                'conflicts' => $conflicts,
            ];
        }

        return response()->json(['data' => $rows]);
    }

    public function timeline(Request $request, CriticalPathService $path): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfDay();
        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $days[] = $from->copy()->addDays($i)->toDateString();
        }
        $query = ProjectTask::query()->orderBy('id');
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        $tasks = $query->limit(200)->get();
        $ids = $tasks->pluck('id');
        $links = TaskLink::query()->whereIn('source_task_id', $ids)->whereIn('target_task_id', $ids)->get();
        $computed = $path->compute(
            $tasks->map(fn (ProjectTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'duration_days' => $task->duration_days ?: 1,
            ])->all(),
            $links->map(fn (TaskLink $link) => [
                'source_id' => $link->source_task_id,
                'target_id' => $link->target_task_id,
                'type' => $link->link_type,
            ])->all()
        );
        $critical = array_map('strval', $computed['critical_ids'] ?? []);
        $baseline = GanttBaseline::query()
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->orderByDesc('id')
            ->first();
        $baseTasks = [];
        foreach ((array) data_get($baseline, 'snapshot.tasks', []) as $row) {
            if (is_array($row) && isset($row['id'])) {
                $baseTasks[(string) $row['id']] = $row;
            }
        }
        $bars = $tasks->map(function (ProjectTask $task) use ($from, $critical, $baseTasks) {
            $start = $task->start_at?->copy()->startOfDay() ?? $from->copy();
            $duration = max(1, (int) ($task->duration_days ?: 1));
            $base = $baseTasks[(string) $task->id] ?? null;
            $baseStart = is_array($base) ? ($base['start_on'] ?? null) : null;
            $variance = null;
            if (is_string($baseStart) && $baseStart !== '' && $task->start_at) {
                $variance = (int) round((strtotime($task->start_at->toDateString()) - strtotime($baseStart)) / 86400);
            }

            return [
                'id' => $task->id,
                'title' => $task->title,
                'start_on' => $start->toDateString(),
                'day_index' => max(0, (int) $from->copy()->startOfDay()->diffInDays($start, false)),
                'duration_days' => $duration,
                'critical' => in_array((string) $task->id, $critical, true),
                'baseline_start' => $baseStart,
                'variance_days' => $variance,
            ];
        })->values();

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'days' => $days,
            'baseline_id' => $baseline?->id,
            'critical_ids' => $computed['critical_ids'] ?? [],
            'tasks' => $bars,
        ]]);
    }

    public function shiftTask(Request $request, int $id): JsonResponse
    {
        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate(['start_on' => 'required|date']);
        $duration = max(1, (int) ($task->duration_days ?: 1));
        $start = \Illuminate\Support\Carbon::parse($data['start_on'])->setTime(9, 0);
        $task->update([
            'start_at' => $start,
            'due_at' => $start->copy()->addDays($duration),
        ]);

        return response()->json(['data' => [
            'id' => $task->id,
            'start_on' => $start->toDateString(),
            'due_on' => $start->copy()->addDays($duration)->toDateString(),
        ]]);
    }

    public function updateBoard(Request $request, int $id): JsonResponse
    {
        $board = PrjKanbanBoard::query()->findOrFail($id);
        $data = $request->validate([
            'swimlane_field' => 'nullable|in:none,assignee,priority,label,custom',
            'name' => 'nullable|string|max:191',
        ]);
        $meta = $board->meta ?? [];
        if (isset($data['swimlane_field'])) {
            $meta['swimlane_field'] = $data['swimlane_field'];
        }
        $board->update([
            'meta' => $meta,
            'name' => $data['name'] ?? $board->name,
        ]);

        return response()->json(['data' => $board->fresh()]);
    }

    public function offline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ops' => 'required|array|min:1',
            'ops.*.client_id' => 'required|string|max:80',
            'ops.*.action' => 'required|in:create_task,update_task_status,shift_task',
            'ops.*.payload' => 'nullable|array',
        ]);
        $results = [];
        foreach ($data['ops'] as $op) {
            $existing = OfflineOp::query()->where('user_id', $request->user()->id)->where('client_id', $op['client_id'])->first();
            if ($existing) {
                $results[] = ['client_id' => $op['client_id'], 'replayed' => true, 'result' => $existing->result];
                continue;
            }
            $result = $this->applyOp($request, $op['action'], $op['payload'] ?? []);
            OfflineOp::query()->create([
                'user_id' => $request->user()->id,
                'client_id' => $op['client_id'],
                'action' => $op['action'],
                'payload' => $op['payload'] ?? [],
                'result' => $result,
            ]);
            $results[] = ['client_id' => $op['client_id'], 'replayed' => false, 'result' => $result];
        }

        return response()->json(['data' => $results]);
    }

    public function assist(Request $request, ErpAiAssistant $ai): JsonResponse
    {
        $data = $request->validate([
            'purpose' => 'required|in:task_plan,summarize_note,deal_risk',
            'context' => 'nullable|array',
        ]);

        return response()->json(['data' => $ai->assist($data['purpose'], $data['context'] ?? [])]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyOp(Request $request, string $action, array $payload): array
    {
        if ($action === 'create_task') {
            $task = ProjectTask::query()->create([
                'title' => (string) ($payload['title'] ?? 'Offline task'),
                'project_id' => $payload['project_id'] ?? null,
                'status' => 'open',
                'assignee_id' => $payload['assignee_id'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return ['task_id' => $task->id];
        }
        if ($action === 'shift_task') {
            $task = ProjectTask::query()->findOrFail((int) ($payload['task_id'] ?? 0));
            $start = \Illuminate\Support\Carbon::parse((string) ($payload['start_on'] ?? now()->toDateString()))->setTime(9, 0);
            $duration = max(1, (int) ($task->duration_days ?: 1));
            $task->update([
                'start_at' => $start,
                'due_at' => $start->copy()->addDays($duration),
            ]);

            return ['task_id' => $task->id, 'start_on' => $start->toDateString()];
        }
        $task = ProjectTask::query()->findOrFail((int) ($payload['task_id'] ?? 0));
        $task->update(['status' => (string) ($payload['status'] ?? $task->status)]);

        return ['task_id' => $task->id, 'status' => $task->status];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ResourceLeave>  $leaves
     * @return list<array<string, mixed>>
     */
    private function conflicts(int $userId, \Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to, $leaves): array
    {
        $conflicts = [];
        $work = ProjectTask::query()
            ->where('assignee_id', $userId)
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('due_at', [$from, $to->copy()->endOfDay()])
                    ->orWhereBetween('start_at', [$from, $to->copy()->endOfDay()]);
            })
            ->get();
        $appointments = \Illuminate\Support\Facades\DB::table('prj_appointments')
            ->where('created_by', $userId)
            ->whereBetween('starts_at', [$from, $to->copy()->endOfDay()])
            ->get();
        $accountIds = CalendarAccount::query()->where('user_id', $userId)->pluck('id');
        $linkedIds = $accountIds->isEmpty()
            ? collect()
            : CalendarLink::query()->whereIn('account_id', $accountIds)->where('local_type', 'appointment')->pluck('local_id');
        $linked = $linkedIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table('prj_appointments')->whereIn('id', $linkedIds)->get();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $off = $leaves->contains(function (ResourceLeave $leave) use ($day) {
                return $day >= $leave->starts_on->toDateString() && $day <= $leave->ends_on->toDateString();
            });
            if ($off) {
                foreach ($work as $task) {
                    $hit = $task->due_at?->toDateString() === $day || $task->start_at?->toDateString() === $day;
                    if ($hit) {
                        $conflicts[] = ['date' => $day, 'kind' => 'leave_task', 'task_id' => $task->id, 'title' => $task->title];
                    }
                }
                foreach ($appointments->merge($linked) as $appointment) {
                    $starts = substr((string) $appointment->starts_at, 0, 10);
                    if ($starts === $day) {
                        $conflicts[] = ['date' => $day, 'kind' => 'leave_calendar', 'appointment_id' => $appointment->id, 'title' => $appointment->title];
                    }
                }
            }
            $cursor->addDay();
        }

        return $conflicts;
    }
}
