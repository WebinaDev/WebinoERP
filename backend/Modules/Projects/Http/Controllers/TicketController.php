<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\PrjTicketReply;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;
use Modules\Projects\Services\TicketSla;
use Modules\Projects\Support\StatusMachine;

class TicketController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $q = PrjTicket::query()->with(['customer', 'assignee'])->orderByDesc('id');
        $access->scopeTickets($q, $request->user());
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('department')) {
            $q->where('department', $request->string('department'));
        }
        if ($request->filled('account_id')) {
            $q->where('customer_account_id', $request->integer('account_id'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where(function ($w) use ($s) {
                $w->where('subject', 'like', $s)->orWhere('body', 'like', $s);
            });
        }
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $paginator = $q->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, CustomerAccess $access, TicketSla $sla): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'customer_user_id' => 'nullable|exists:users,id',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'project_id' => 'nullable|exists:prj_projects,id',
            'priority' => 'nullable|string|max:20',
            'assignee_id' => 'nullable|exists:users,id',
        ]);
        $data['status'] = 'open';
        if ($access->isPortalCustomer($request->user())) {
            $ids = $access->accountIds($request->user());
            $data['customer_user_id'] = $request->user()->id;
            $data['customer_account_id'] = $ids[0] ?? null;
            $data['assignee_id'] = null;
            if (! empty($data['project_id'])) {
                $owns = Project::query()->whereKey($data['project_id'])->whereIn('customer_account_id', $ids)->exists();
                abort_unless($owns, 422);
            }
        }
        $t = PrjTicket::query()->create($data);
        $sla->apply($t);

        return response()->json(['data' => $t->fresh()], 201);
    }

    public function show(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $t = $this->findVisible($request, $id, $access)->load(['replies.user', 'customer', 'assignee']);

        return response()->json(['data' => $t]);
    }

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $t = $this->findVisible($request, $id, $access);
        abort_if($access->isPortalCustomer($request->user()), 403);
        $data = $request->validate([
            'status' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:20',
            'assignee_id' => 'nullable|exists:users,id',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'project_id' => 'nullable|exists:prj_projects,id',
        ]);
        if (! empty($data['status'])) {
            StatusMachine::assert($t->status, $data['status'], 'ticket');
        }
        $t->fill(array_filter($data, fn ($v) => $v !== null));
        $t->save();

        return response()->json(['data' => $t->fresh(['replies.user', 'customer', 'assignee'])]);
    }

    public function reply(Request $request, int $id, CustomerAccess $access, TicketSla $sla): JsonResponse
    {
        $ticket = $this->findVisible($request, $id, $access);
        abort_if($ticket->status === 'closed' && $access->isPortalCustomer($request->user()), 422);
        $data = $request->validate(['body' => 'required|string']);
        $reply = PrjTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        if ($access->isPortalCustomer($request->user()) && $ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        } elseif (! $access->isPortalCustomer($request->user())) {
            $sla->markResponse($ticket);
        }

        return response()->json(['data' => $reply], 201);
    }

    public function convertTask(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $ticket = $this->findVisible($request, $id, $access);
        abort_if($ticket->converted_task_id, 422, 'Ticket already converted.');
        $data = $request->validate([
            'project_id' => 'nullable|exists:prj_projects,id',
        ]);
        $projectId = $data['project_id'] ?? $ticket->project_id;
        $ws = $this->defaultWorkflowStatusId();
        $task = ProjectTask::query()->create([
            'project_id' => $projectId,
            'title' => $ticket->subject,
            'content' => $ticket->body,
            'status' => 'open',
            'workflow_status_id' => $ws,
            'assignee_id' => $ticket->assignee_id,
            'created_by' => $request->user()->id,
        ]);
        $next = $ticket->status === 'open' ? 'in_progress' : $ticket->status;
        if ($ticket->status !== $next) {
            StatusMachine::assert($ticket->status, 'in_progress', 'ticket');
            $next = 'in_progress';
        }
        $ticket->update([
            'converted_task_id' => $task->id,
            'project_id' => $projectId,
            'status' => $next,
        ]);

        return response()->json(['data' => ['task_id' => $task->id, 'task' => $task, 'ticket' => $ticket->fresh()]]);
    }

    public function rating(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $t = $this->findVisible($request, $id, $access);
        $user = $request->user();
        $owns = (int) $t->customer_user_id === (int) $user->id
            || ($access->isPortalCustomer($user) && in_array((int) $t->customer_account_id, $access->accountIds($user), true));
        abort_unless($owns, 403);
        abort_unless(in_array($t->status, ['resolved', 'closed'], true), 422, 'Ticket is not ready for rating.');
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5']);
        $t->update(['rating' => $data['rating']]);

        return response()->json(['data' => ['ticket_id' => $id, 'saved' => true]]);
    }

    private function findVisible(Request $request, int $id, CustomerAccess $access): PrjTicket
    {
        $q = PrjTicket::query()->whereKey($id);
        $access->scopeTickets($q, $request->user());

        return $q->firstOrFail();
    }
}
