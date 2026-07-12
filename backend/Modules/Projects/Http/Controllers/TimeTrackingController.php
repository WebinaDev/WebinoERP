<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\TimeEntry;

class TimeTrackingController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => 'nullable|integer|exists:prj_tasks,id',
            'project_id' => 'nullable|integer|exists:prj_projects,id',
            'description' => 'nullable|string',
            'is_billable' => 'nullable|boolean',
        ]);

        $this->stopUserRunning($request->user()->id);

        $row = TimeEntry::query()->create([
            'user_id' => $request->user()->id,
            'task_id' => $data['task_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'started_at' => now(),
            'is_running' => true,
            'is_billable' => (bool) ($data['is_billable'] ?? false),
            'description' => $data['description'] ?? null,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function stop(Request $request): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('is_running', true)
            ->latest('id')
            ->first();

        if (! $entry) {
            return response()->json(['data' => ['stopped' => false, 'message' => 'No active timer']], 404);
        }

        $duration = max(0, now()->diffInSeconds($entry->started_at ?? now()));
        $entry->update([
            'ended_at' => now(),
            'is_running' => false,
            'paused_at' => null,
            'duration_seconds' => $duration,
        ]);

        return response()->json(['data' => ['stopped' => true, 'entry' => $entry->fresh()]]);
    }

    public function pause(Request $request): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('is_running', true)
            ->latest('id')
            ->firstOrFail();

        $entry->update(['is_running' => false, 'paused_at' => now()]);

        return response()->json(['data' => ['paused' => true, 'entry' => $entry->fresh()]]);
    }

    public function resume(Request $request): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('paused_at')
            ->latest('id')
            ->firstOrFail();

        $this->stopUserRunning($request->user()->id);
        $entry->update(['is_running' => true, 'paused_at' => null]);

        return response()->json(['data' => ['resumed' => true, 'entry' => $entry->fresh()]]);
    }

    public function addManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => 'nullable|integer|exists:prj_tasks,id',
            'project_id' => 'nullable|integer|exists:prj_projects,id',
            'date' => 'nullable|date',
            'duration_seconds' => 'required|integer|min:1|max:86400',
            'description' => 'nullable|string',
            'is_billable' => 'nullable|boolean',
        ]);

        $start = isset($data['date']) ? Carbon::parse($data['date']) : now();
        $entry = TimeEntry::query()->create([
            'user_id' => $request->user()->id,
            'task_id' => $data['task_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'started_at' => $start,
            'ended_at' => $start->copy()->addSeconds((int) $data['duration_seconds']),
            'duration_seconds' => (int) $data['duration_seconds'],
            'is_running' => false,
            'is_billable' => (bool) ($data['is_billable'] ?? false),
            'description' => $data['description'] ?? null,
        ]);

        return response()->json(['data' => $entry], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);
        $q = TimeEntry::query()->where('user_id', $request->user()->id)->orderByDesc('id');
        if ($request->filled('task_id')) {
            $q->where('task_id', (int) $request->input('task_id'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', (int) $request->input('project_id'));
        }
        if ($request->filled('from')) {
            $q->whereDate('started_at', '>=', $request->string('from')->toString());
        }
        if ($request->filled('to')) {
            $q->whereDate('started_at', '<=', $request->string('to')->toString());
        }

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        TimeEntry::query()->where('user_id', $request->user()->id)->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function report(Request $request): JsonResponse
    {
        $q = TimeEntry::query()->where('user_id', $request->user()->id);
        if ($request->filled('from')) {
            $q->whereDate('started_at', '>=', $request->string('from')->toString());
        }
        if ($request->filled('to')) {
            $q->whereDate('started_at', '<=', $request->string('to')->toString());
        }

        $total = (clone $q)->sum('duration_seconds');
        $billable = (clone $q)->where('is_billable', true)->sum('duration_seconds');
        $byTask = (clone $q)
            ->selectRaw('task_id, SUM(duration_seconds) as total_seconds')
            ->groupBy('task_id')
            ->orderByDesc('total_seconds')
            ->get();

        return response()->json([
            'data' => [
                'total_seconds' => (int) $total,
                'billable_seconds' => (int) $billable,
                'non_billable_seconds' => max(0, (int) $total - (int) $billable),
                'by_task' => $byTask,
            ],
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->where('is_running', true)
            ->latest('id')
            ->first();

        return response()->json(['data' => $entry]);
    }

    private function stopUserRunning(int $userId): void
    {
        $running = TimeEntry::query()->where('user_id', $userId)->where('is_running', true)->get();
        foreach ($running as $entry) {
            $entry->update([
                'is_running' => false,
                'ended_at' => now(),
                'duration_seconds' => max(0, now()->diffInSeconds($entry->started_at ?? now())),
            ]);
        }
    }
}
