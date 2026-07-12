<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\PrjTicketReply;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Http\Controllers\Concerns\UsesProjectHelpers;

class TicketController extends Controller
{
    use UsesProjectHelpers;

    public function index(Request $request): JsonResponse
    {
        $q = PrjTicket::query()->with(['customer', 'assignee'])->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('department')) {
            $q->where('department', $request->string('department'));
        }
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where(function ($w) use ($s) {
                $w->where('subject', 'like', $s)->orWhere('body', 'like', $s);
            });
        }
        $perPage = min((int) $request->input('per_page', 25), 100);

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
            'customer_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:20',
        ]);
        $data['status'] = 'open';
        $data['assignee_id'] = $request->input('assignee_id');
        $t = PrjTicket::query()->create($data);

        return response()->json(['data' => $t], 201);
    }

    public function show(int $id): JsonResponse
    {
        $t = PrjTicket::query()->with(['replies.user', 'customer', 'assignee'])->findOrFail($id);

        return response()->json(['data' => $t]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = PrjTicket::query()->findOrFail($id);
        $data = $request->validate([
            'status' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:20',
            'assignee_id' => 'nullable|exists:users,id',
        ]);
        $t->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['data' => $t->fresh(['replies.user', 'customer', 'assignee'])]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        PrjTicket::query()->findOrFail($id);
        $data = $request->validate(['body' => 'required|string']);
        $reply = PrjTicketReply::query()->create([
            'ticket_id' => $id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $reply], 201);
    }

    public function convertTask(Request $request, int $id): JsonResponse
    {
        $ticket = PrjTicket::query()->findOrFail($id);
        $ws = $this->defaultWorkflowStatusId();
        $task = ProjectTask::query()->create([
            'title' => $ticket->subject,
            'content' => $ticket->body,
            'status' => 'open',
            'workflow_status_id' => $ws,
            'assignee_id' => $ticket->assignee_id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['task_id' => $task->id, 'task' => $task]]);
    }

    public function rating(Request $request, int $id): JsonResponse
    {
        $t = PrjTicket::query()->findOrFail($id);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5']);
        $t->update(['rating' => $data['rating']]);

        return response()->json(['data' => ['ticket_id' => $id, 'saved' => true]]);
    }
}
