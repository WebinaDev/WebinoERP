<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use App\Support\MutationAudit;
use App\Support\StaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Projects\Entities\PrjApproval;
use Modules\Projects\Entities\PrjConnector;
use Modules\Projects\Entities\PrjFile;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\TimeEntry;
use Modules\Projects\Services\ProjectTemplateCloner;
use Modules\Projects\Services\TicketSla;

class PmDeliveryController extends Controller
{
    public function workload(Request $request): JsonResponse
    {
        $closed = ['done', 'completed', 'cancelled', 'closed'];
        $tasks = ProjectTask::query()->whereNotIn('status', $closed);
        if ($request->filled('project_id')) {
            $tasks->where('project_id', $request->integer('project_id'));
        }
        $rows = $tasks->get();
        $logged = TimeEntry::query()
            ->select('user_id', DB::raw('sum(duration_seconds) as seconds'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->pluck('seconds', 'user_id');

        $byUser = [];
        foreach ($rows as $task) {
            $userId = (int) ($task->assignee_id ?? 0);
            if (! isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'user_id' => $userId ?: null,
                    'open_tasks' => 0,
                    'overdue' => 0,
                    'estimate_hours' => 0,
                    'logged_hours' => round(((int) ($logged[$userId] ?? 0)) / 3600, 2),
                ];
            }
            $byUser[$userId]['open_tasks']++;
            $byUser[$userId]['estimate_hours'] += (float) ($task->estimate_hours ?? 0);
            if ($task->due_at && $task->due_at->isPast()) {
                $byUser[$userId]['overdue']++;
            }
        }

        return response()->json(['data' => array_values($byUser)]);
    }

    public function budget(int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $seconds = (int) TimeEntry::query()->where('project_id', $project->id)->sum('duration_seconds');
        $hours = round($seconds / 3600, 2);
        $rate = (float) ($project->hourly_rate ?? 0);
        $actualCost = round($hours * $rate, 2);
        $budget = (float) ($project->budget_amount ?? 0);

        return response()->json(['data' => [
            'project_id' => $project->id,
            'budget_amount' => $project->budget_amount,
            'budget_hours' => $project->budget_hours,
            'hourly_rate' => $project->hourly_rate,
            'actual_hours' => $hours,
            'actual_cost' => $actualCost,
            'variance_amount' => $budget > 0 ? round($budget - $actualCost, 2) : null,
            'variance_hours' => $project->budget_hours !== null ? round((float) $project->budget_hours - $hours, 2) : null,
        ]]);
    }

    public function updateBudget(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $data = $request->validate([
            'budget_amount' => 'nullable|numeric|min:0',
            'budget_hours' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);
        $project->update($data);
        MutationAudit::record($request->user()->id, 'projects', 'budget.update', Project::class, $project->id, $data);

        return $this->budget($project->id);
    }

    public function delays(): JsonResponse
    {
        $closed = ['done', 'completed', 'cancelled', 'closed'];
        $tasks = ProjectTask::query()
            ->whereNotIn('status', $closed)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(100)
            ->get(['id', 'title', 'project_id', 'assignee_id', 'due_at', 'status']);

        $projects = Project::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['done', 'completed', 'cancelled', 'archived'])
            ->orderBy('due_date')
            ->limit(50)
            ->get(['id', 'name', 'due_date', 'status', 'manager_user_id']);

        return response()->json(['data' => [
            'tasks' => $tasks,
            'projects' => $projects,
        ]]);
    }

    public function notifyDelays(Request $request): JsonResponse
    {
        $payload = $this->delays()->getData(true);
        $sent = 0;
        foreach ($payload['data']['tasks'] as $task) {
            $userId = (int) ($task['assignee_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            StaffNotifier::notify($userId, 'pm.delay', 'تاخیر وظیفه', (string) $task['title'], [
                'task_id' => $task['id'],
            ]);
            $sent++;
        }
        MutationAudit::record($request->user()->id, 'projects', 'delay.notify', ProjectTask::class, null, ['sent' => $sent]);

        return response()->json(['data' => ['sent' => $sent]]);
    }

    public function files(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $project = Project::query()->whereKey($id);
        $access->scopeProjects($project, $request->user());
        $project->firstOrFail();
        $query = PrjFile::query()->where('project_id', $id)->orderByDesc('id');
        if ($access->isPortalCustomer($request->user())) {
            $query->where('shared_with_client', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeFile(Request $request, ?int $id = null): JsonResponse
    {
        $projectId = $id ?: (int) $request->input('project_id');
        abort_if($projectId <= 0, 422, 'Project is required');
        $id = $projectId;
        Project::query()->findOrFail($id);
        $request->validate([
            'file' => 'required|file|max:20480',
            'shared_with_client' => 'nullable|boolean',
            'task_id' => 'nullable|integer',
        ]);
        $upload = $request->file('file');
        $path = $upload->store('project_files', 'public');
        $row = PrjFile::query()->create([
            'project_id' => $id,
            'task_id' => $request->input('task_id'),
            'version' => 1,
            'name' => $upload->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'size_bytes' => $upload->getSize(),
            'shared_with_client' => $request->boolean('shared_with_client'),
            'uploaded_by' => $request->user()->id,
        ]);
        $row->update(['family_id' => $row->id]);
        MutationAudit::record($request->user()->id, 'projects', 'file.upload', PrjFile::class, $row->id, ['name' => $row->name]);

        return response()->json(['data' => $row->fresh()], 201);
    }

    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $current = PrjFile::query()->findOrFail($id);
        $request->validate(['file' => 'required|file|max:20480']);
        $upload = $request->file('file');
        $path = $upload->store('project_files', 'public');
        $family = $current->family_id ?: $current->id;
        $version = (int) PrjFile::query()->where('family_id', $family)->max('version') + 1;
        $row = PrjFile::query()->create([
            'project_id' => $current->project_id,
            'task_id' => $current->task_id,
            'family_id' => $family,
            'version' => $version,
            'name' => $upload->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'size_bytes' => $upload->getSize(),
            'shared_with_client' => $current->shared_with_client,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function destroyFile(int $id): JsonResponse
    {
        $row = PrjFile::query()->findOrFail($id);
        Storage::disk($row->disk)->delete($row->path);
        $row->delete();

        return response()->json([], 204);
    }

    public function instantiate(Request $request, int $id, ProjectTemplateCloner $cloner): JsonResponse
    {
        $template = Project::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'customer_account_id' => 'nullable|integer|exists:crm_accounts,id',
        ]);
        $project = $cloner->instantiate($template, $data['name'], $request->user()->id, $data['customer_account_id'] ?? null);

        return response()->json(['data' => $project], 201);
    }

    public function approvals(Request $request, CustomerAccess $access): JsonResponse
    {
        $query = PrjApproval::query()->orderByDesc('id');
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($access->isPortalCustomer($request->user())) {
            $query->whereIn('customer_account_id', $access->accountIds($request->user()));
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function storeApproval(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|exists:prj_projects,id',
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
        ]);
        $project = Project::query()->findOrFail($data['project_id']);
        $row = PrjApproval::query()->create([
            'project_id' => $project->id,
            'customer_account_id' => $data['customer_account_id'] ?? $project->customer_account_id,
            'title' => $data['title'],
            'note' => $data['note'] ?? null,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);
        MutationAudit::record($request->user()->id, 'projects', 'approval.create', PrjApproval::class, $row->id, ['title' => $row->title]);

        return response()->json(['data' => $row], 201);
    }

    public function decideApproval(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $row = PrjApproval::query()->findOrFail($id);
        if ($access->isPortalCustomer($request->user())) {
            abort_unless(in_array((int) $row->customer_account_id, $access->accountIds($request->user()), true), 403);
        }
        $data = $request->validate([
            'status' => 'required|string|in:approved,changes,rejected',
            'decision_note' => 'nullable|string|max:2000',
        ]);
        $row->update([
            'status' => $data['status'],
            'decision_note' => $data['decision_note'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);
        if ($row->created_by) {
            StaffNotifier::notify((int) $row->created_by, 'pm.approval', $row->title, (string) ($data['decision_note'] ?? $data['status']), [
                'approval_id' => $row->id,
                'status' => $data['status'],
            ]);
        }
        MutationAudit::record($request->user()->id, 'projects', 'approval.decide', PrjApproval::class, $row->id, $data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function connectors(Request $request): JsonResponse
    {
        $kinds = ['google_calendar', 'outlook_calendar', 'slack', 'bale'];
        $stored = PrjConnector::query()
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->get()
            ->keyBy('kind');
        $rows = collect($kinds)->map(fn (string $kind) => $stored[$kind] ?? [
            'kind' => $kind,
            'status' => 'disconnected',
            'label' => null,
            'config' => null,
            'last_message' => null,
        ]);

        return response()->json(['data' => $rows->values()]);
    }

    public function saveConnector(Request $request, string $kind): JsonResponse
    {
        abort_unless(in_array($kind, ['google_calendar', 'outlook_calendar', 'slack', 'bale'], true), 422);
        $data = $request->validate([
            'status' => 'nullable|string|in:disconnected,stub,connected',
            'label' => 'nullable|string|max:120',
            'webhook_url' => 'nullable|url|max:500',
            'external_id' => 'nullable|string|max:191',
        ]);
        $status = $data['status'] ?? (empty($data['webhook_url']) ? 'stub' : 'connected');
        $row = PrjConnector::query()->updateOrCreate(
            ['kind' => $kind, 'user_id' => $request->user()->id],
            [
                'status' => $status,
                'label' => $data['label'] ?? null,
                'config' => [
                    'webhook_url' => $data['webhook_url'] ?? null,
                    'external_id' => $data['external_id'] ?? null,
                ],
            ],
        );
        MutationAudit::record($request->user()->id, 'projects', 'connector.save', PrjConnector::class, $row->id, ['kind' => $kind]);

        return response()->json(['data' => $row]);
    }

    public function testConnector(Request $request, string $kind): JsonResponse
    {
        $row = PrjConnector::query()->where('kind', $kind)->where('user_id', $request->user()->id)->first();
        abort_unless($row, 404);
        $url = (string) ($row->config['webhook_url'] ?? '');
        if ($url === '') {
            $row->update([
                'status' => 'stub',
                'last_checked_at' => now(),
                'last_message' => 'stub',
            ]);

            return response()->json(['data' => $row->fresh()]);
        }

        try {
            $res = Http::timeout(4)->asJson()->post($url, [
                'source' => 'webino',
                'kind' => $kind,
                'ping' => true,
            ]);
            $row->update([
                'status' => $res->successful() ? 'connected' : 'stub',
                'last_checked_at' => now(),
                'last_message' => $res->successful() ? 'ok' : 'http '.$res->status(),
            ]);
        } catch (\Throwable $e) {
            $row->update([
                'status' => 'stub',
                'last_checked_at' => now(),
                'last_message' => 'unreachable',
            ]);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function sla(TicketSla $sla): JsonResponse
    {
        $sla->refreshBreaches();
        $open = PrjTicket::query()->whereNotIn('status', ['closed', 'resolved']);

        return response()->json(['data' => [
            'breached' => (clone $open)->whereNotNull('sla_breached_at')->count(),
            'waiting' => (clone $open)->whereNull('first_responded_at')->count(),
            'due_soon' => (clone $open)
                ->whereNull('first_responded_at')
                ->whereNotNull('sla_first_due_at')
                ->whereBetween('sla_first_due_at', [now(), now()->addHours(8)])
                ->count(),
            'rows' => (clone $open)->orderBy('sla_first_due_at')->limit(30)->get([
                'id', 'subject', 'status', 'priority', 'sla_first_due_at', 'sla_resolve_due_at', 'first_responded_at', 'sla_breached_at',
            ]),
        ]]);
    }

    public function audit(): JsonResponse
    {
        $rows = DB::table('ops_audit_logs')->where('module', 'projects')->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows]);
    }
}
