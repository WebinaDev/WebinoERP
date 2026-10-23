<?php

namespace Modules\Crm\Http\Controllers\Hub;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Http\Requests\Hub\ProjectRequest;
use Modules\Crm\Http\Requests\Hub\ProjectTaskRequest;
use Modules\Crm\Http\Resources\Hub\ProjectResource;
use Modules\Projects\Entities\Project;
use Modules\Projects\Services\ProjectTemplateCloner;
use Modules\Projects\Support\StatusMachine;

/** Customer 360 — Projects tab (contract §5.3). No delete here: the hub links to /projects/{id} for that. */
class HubProjectController extends HubTabController
{
    private const T = 'prj_projects';

    private const DONE = ['done', 'completed'];

    public function index(Request $r, int $id): JsonResponse
    {
        $this->gate($r, 'projects');
        $a = $this->account($id);
        $r->validate([
            'status' => 'nullable|in:planning,'.implode(',', ProjectRequest::STATUSES), 'manager_id' => 'nullable|integer',
            'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $q = DB::table(self::T)->where('customer_account_id', $a->id)->where(fn ($w) => $w->whereNull('is_template')->orWhere('is_template', false));
        if ($s = $r->query('status')) {
            $q->where('status', $s === 'planning' ? 'draft' : $s);
        }
        if ($r->filled('manager_id')) {
            $q->where('manager_user_id', (int) $r->query('manager_id'));
        }
        if ($r->filled('q')) {
            $like = $this->like((string) $r->query('q'));
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }
        $counts = DB::table(self::T)->where('customer_account_id', $a->id)->where(fn ($w) => $w->whereNull('is_template')->orWhere('is_template', false))
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $p = $this->sort($q, $r, ['created_at', 'due_date', 'name', 'start_date'], '-created_at')->paginate($this->perPage($r), ['*'], 'page', max(1, (int) $r->query('page', 1)));

        return response()->json(['data' => $this->present($r, collect($p->items())), 'meta' => $this->meta($p, [
            'status_counts' => $counts, 'can' => ['create' => $this->canManage($r, 'projects')],
        ])]);
    }

    public function show(Request $r, int $id, int $projectId): JsonResponse
    {
        $this->gate($r, 'projects');
        $a = $this->account($id);
        $p = $this->find($a, $projectId);
        $data = $this->present($r, collect([$p]))->first();
        $now = now();
        $data['milestones'] = DB::table('prj_milestones')->where('project_id', $p->id)->orderBy('due_date')->limit(50)->get()
            ->map(fn ($m) => ['id' => (int) $m->id, 'title' => $m->title, 'due_date' => $m->due_date, 'status' => $m->status, 'completed_at' => $m->completed_at ?? null])->values();
        $data['open_tasks'] = DB::table('prj_tasks')->where('project_id', $p->id)->whereNotIn('status', [...self::DONE, 'cancelled'])
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderByDesc('id')->limit(20)->get(['id', 'title', 'status', 'assignee_id', 'due_at'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'title' => $t->title, 'status' => $t->status, 'assignee' => $this->userRef($t->assignee_id), 'due_at' => $t->due_at, 'overdue' => $t->due_at && $t->due_at < $now])->values();
        $data['recent_time'] = $this->has('prj_time_entries') ? DB::table('prj_time_entries')->where('project_id', $p->id)->orderByDesc('id')->limit(10)
            ->get(['id', 'user_id', 'task_id', 'duration_seconds', 'is_billable', 'started_at'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'user' => $this->userRef($e->user_id), 'task_id' => $e->task_id, 'hours' => round($e->duration_seconds / 3600, 2), 'billable' => (bool) $e->is_billable, 'started_at' => $e->started_at])->values() : [];
        $data['files_count'] = $this->has('prj_files') ? DB::table('prj_files')->where('project_id', $p->id)->count() : 0;
        $data['tickets_count'] = $this->has('prj_tickets', 'project_id') ? DB::table('prj_tickets')->where('project_id', $p->id)->count() : 0;

        return response()->json(['data' => $data]);
    }

    public function store(ProjectRequest $r, int $id): JsonResponse
    {
        $this->gateManage($r, 'projects');
        $a = $this->account($id);
        $d = $r->validated();
        $pid = DB::transaction(function () use ($r, $a, $d) {
            if (! empty($d['template_id'])) {
                $tpl = Project::query()->findOrFail($d['template_id']);
                $pid = app(ProjectTemplateCloner::class)->instantiate($tpl, $d['name'], $r->user()->id, $a->id)->id;
                DB::table(self::T)->where('id', $pid)->update($this->onlyColumns(self::T, $this->fields($d) + ['status' => $d['status'] ?? 'draft', 'updated_at' => now()]));
            } else {
                $pid = DB::table(self::T)->insertGetId($this->onlyColumns(self::T, $this->fields($d) + [
                    'status' => $d['status'] ?? 'draft', 'customer_account_id' => $a->id, 'created_by' => $r->user()->id,
                    'is_template' => false, 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $this->linkContract($pid, $d['contract_id'] ?? null);

            return $pid;
        });
        $this->audit($r, $a, 'project.create', ['project_id' => $pid, 'name' => $d['name'], 'contract_id' => $d['contract_id'] ?? null]);

        return $this->show($r, $a->id, $pid)->setStatusCode(201);
    }

    public function update(ProjectRequest $r, int $id, int $projectId): JsonResponse
    {
        $this->gateManage($r, 'projects');
        $a = $this->account($id);
        $p = $this->find($a, $projectId);
        $d = $r->validated();
        if (isset($d['status']) && $d['status'] !== $p->status) {
            StatusMachine::assert($p->status, $d['status'], 'project');
        }
        $start = $d['start_date'] ?? $p->start_date;
        $due = $d['due_date'] ?? $p->due_date;
        if ($start && $due && substr((string) $due, 0, 10) < substr((string) $start, 0, 10)) {
            throw ValidationException::withMessages(['due_date' => [__('validation.after_or_equal', ['attribute' => 'due_date', 'date' => 'start_date'])]]);
        }
        DB::transaction(function () use ($p, $d) {
            $row = $this->fields($d);
            if (isset($d['status'])) {
                $row['status'] = $d['status'];
            }
            DB::table(self::T)->where('id', $p->id)->update($this->onlyColumns(self::T, $row + ['updated_at' => now()]));
            if (array_key_exists('contract_id', $d)) {
                $this->linkContract($p->id, $d['contract_id']);
            }
        });
        $this->audit($r, $a, 'project.update', ['project_id' => $p->id, 'fields' => array_keys($d)]);

        return $this->show($r, $a->id, $p->id);
    }

    public function storeTask(ProjectTaskRequest $r, int $id, int $projectId): JsonResponse
    {
        $this->gateManage($r, 'projects');
        $a = $this->account($id);
        $p = $this->find($a, $projectId);
        abort_if(in_array($p->status, ProjectResource::CLOSED, true), 422, __('Cannot add tasks to a closed project.'));
        $d = $r->validated();
        $tid = DB::table('prj_tasks')->insertGetId($this->onlyColumns('prj_tasks', [
            'project_id' => $p->id, 'title' => $d['title'], 'status' => 'todo', 'assignee_id' => $d['assignee_id'] ?? null,
            'due_at' => $d['due_at'] ?? null, 'priority' => $d['priority'] ?? null, 'created_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
        $this->audit($r, $a, 'project.task_create', ['project_id' => $p->id, 'task_id' => $tid, 'title' => $d['title']]);

        return response()->json(['data' => ['id' => (int) $tid, 'title' => $d['title'], 'status' => 'todo', 'assignee' => $this->userRef($d['assignee_id'] ?? null), 'due_at' => $d['due_at'] ?? null]], 201);
    }

    // ---------------------------------------------------------------------

    private function find(CrmAccount $a, int $projectId): object
    {
        $p = DB::table(self::T)->where('customer_account_id', $a->id)->where('id', $projectId)->first();
        abort_unless($p && ! $p->is_template, 404);

        return $p;
    }

    private function fields(array $d): array
    {
        $row = array_intersect_key($d, array_flip(['name', 'description', 'manager_user_id', 'start_date', 'due_date', 'progress_pct']));
        if (array_key_exists('budget', $d)) {
            $row['budget'] = $d['budget']; // Phase 0 column
            $row['budget_amount'] = $d['budget']; // legacy PM column, kept in sync
        }

        return $row;
    }

    /** Prefers Phase 0 prj_projects.contract_id; falls back to the legacy prj_contracts.project_id link. */
    private function linkContract(int $pid, ?int $contractId): void
    {
        if ($this->has(self::T, 'contract_id')) {
            DB::table(self::T)->where('id', $pid)->update(['contract_id' => $contractId]);
        }
        if ($contractId) {
            DB::table('prj_contracts')->where('id', $contractId)->whereNull('project_id')->update(['project_id' => $pid]);
        }
    }

    private function present(Request $r, Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }
        $ids = $rows->pluck('id');
        $now = now();
        $tasks = DB::table('prj_tasks')->whereIn('project_id', $ids)->get(['id', 'project_id', 'status', 'due_at', 'assignee_id'])->groupBy('project_id');
        $time = $this->has('prj_time_entries') ? DB::table('prj_time_entries')->whereIn('project_id', $ids)->get(['project_id', 'user_id', 'duration_seconds', 'is_billable'])->groupBy('project_id') : collect();
        $inv = DB::table('prj_pro_invoices')->whereIn('project_id', $ids)->where('status', '!=', 'cancelled')->selectRaw('project_id, SUM(total) as t')->groupBy('project_id')->pluck('t', 'project_id');
        $userIds = $rows->pluck('manager_user_id')->concat($tasks->flatten()->pluck('assignee_id'))->concat($time->flatten()->pluck('user_id'))->filter()->unique();
        $users = $userIds->isEmpty() ? collect() : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $contractIds = $this->has(self::T, 'contract_id') ? $rows->pluck('contract_id')->filter() : collect();
        $legacy = DB::table('prj_contracts')->whereIn('project_id', $ids)->get(['id', 'title', 'status', 'project_id'])->keyBy('project_id');
        $contracts = $contractIds->isEmpty() ? collect() : DB::table('prj_contracts')->whereIn('id', $contractIds)->get(['id', 'title', 'status'])->keyBy('id');
        $money = $this->seesMoney($r);
        $manage = $this->canManage($r, 'projects');
        $ref = fn ($uid) => $uid ? ['id' => (int) $uid, 'name' => $users[$uid] ?? null] : null;

        return $rows->map(function ($p) use ($tasks, $time, $inv, $contracts, $legacy, $money, $manage, $now, $ref) {
            $ts = $tasks->get($p->id, collect());
            $te = $time->get($p->id, collect());
            $c = ! empty($p->contract_id) ? $contracts->get($p->contract_id) : $legacy->get($p->id);
            $rate = (float) ($p->hourly_rate ?? 0);
            // members: no project-members table exists — derived from manager + task assignees + people who logged time
            $members = collect([$p->manager_user_id])->concat($ts->pluck('assignee_id'))->concat($te->pluck('user_id'))->filter()->unique()->values()
                ->map(fn ($uid) => $ref($uid) + ['role' => (int) $uid === (int) $p->manager_user_id ? 'manager' : 'member'])->all();

            return ProjectResource::fromRow($p, [
                'money' => $money, 'manage' => $manage, 'members' => $members,
                'tasks' => [
                    'total' => $ts->where('status', '!=', 'cancelled')->count(),
                    'done' => $ts->whereIn('status', self::DONE)->count(),
                    'overdue' => $ts->filter(fn ($t) => $t->due_at && $t->due_at < $now && ! in_array($t->status, [...self::DONE, 'cancelled'], true))->count(),
                ],
                'spent_seconds' => (int) $te->sum('duration_seconds'),
                'billable_amount' => round($te->where('is_billable', true)->sum('duration_seconds') / 3600 * $rate, 2),
                'invoiced_amount' => (float) ($inv[$p->id] ?? 0),
                'manager' => $ref($p->manager_user_id),
                'contract' => $c ? ['id' => (int) $c->id, 'title' => $c->title, 'status' => $c->status] : null,
            ]);
        })->values();
    }
}
