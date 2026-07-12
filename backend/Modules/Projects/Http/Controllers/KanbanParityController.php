<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\PrjKanbanBoard;
use Modules\Projects\Entities\PrjKanbanCard;
use Modules\Projects\Entities\PrjKanbanColumn;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\WorkflowStatus;

/**
 * Kanban: legacy workflow-backed board and generic polymorphic boards (prj_kanban_*).
 */
class KanbanParityController extends Controller
{
    protected function genericKanbanEnabled(): bool
    {
        return Schema::hasTable('prj_kanban_boards');
    }

    public function data(Request $request): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->with(['columns.cards'])->findOrFail($request->integer('board_id'));

            return response()->json(['data' => $this->serializeGenericBoard($board)]);
        }

        if ($this->genericKanbanEnabled() && $request->boolean('ensure_board') && $request->filled('project_id')) {
            $board = $this->ensureProjectBoard($request->integer('project_id'));

            return response()->json(['data' => $this->serializeGenericBoard($board)]);
        }

        $projectId = $request->input('project_id');

        $columns = WorkflowStatus::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (WorkflowStatus $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'sort_order' => $s->sort_order,
            ]);

        $taskQuery = ProjectTask::query()
            ->with(['workflowStatus', 'project'])
            ->orderByDesc('id');

        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
        }
        if ($request->filled('assignee_id')) {
            $taskQuery->where('assignee_id', $request->integer('assignee_id'));
        }
        if ($request->filled('priority')) {
            $taskQuery->where('priority', $request->string('priority'));
        }
        if ($request->filled('label')) {
            $taskQuery->where('label', 'like', '%'.$request->string('label').'%');
        }

        $tasks = $taskQuery->get();

        $cards = $tasks->map(fn (ProjectTask $t) => [
            'id' => $t->id,
            'column_id' => $t->workflow_status_id,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'label' => $t->label,
            'project_id' => $t->project_id,
            'assignee_id' => $t->assignee_id,
            'due_at' => $t->due_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'mode' => 'workflow',
                'columns' => $columns,
                'cards' => $cards,
            ],
        ]);
    }

    protected function ensureProjectBoard(int $projectId): PrjKanbanBoard
    {
        Project::query()->findOrFail($projectId);
        $board = PrjKanbanBoard::query()->firstOrCreate(
            ['owner_type' => Project::class, 'owner_id' => $projectId],
            ['name' => 'Project Kanban', 'meta' => []]
        );
        if ($board->columns()->count() === 0) {
            $defaults = [
                ['name' => 'Backlog', 'color' => '#64748b', 'sort_order' => 0],
                ['name' => 'In progress', 'color' => '#2563eb', 'sort_order' => 1],
                ['name' => 'Done', 'color' => '#16a34a', 'sort_order' => 2],
            ];
            foreach ($defaults as $d) {
                PrjKanbanColumn::query()->create([
                    'board_id' => $board->id,
                    'name' => $d['name'],
                    'color' => $d['color'],
                    'sort_order' => $d['sort_order'],
                ]);
            }
        }

        return $board->load(['columns.cards']);
    }

    /**
     * @return array{mode:string,board_id?:int,columns:mixed,cards:mixed}
     */
    protected function serializeGenericBoard(PrjKanbanBoard $board): array
    {
        $columns = $board->columns->map(fn (PrjKanbanColumn $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'color' => $c->color,
            'sort_order' => $c->sort_order,
            'wip_limit' => $c->wip_limit,
        ]);
        $cards = $board->columns->flatMap(fn (PrjKanbanColumn $c) => $c->cards->map(fn (PrjKanbanCard $card) => [
            'id' => $card->id,
            'column_id' => $card->column_id,
            'title' => $card->title,
            'body' => $card->body,
            'sort_order' => $card->sort_order,
            'cardable_type' => $card->cardable_type,
            'cardable_id' => $card->cardable_id,
        ]));

        return [
            'mode' => 'generic',
            'board_id' => $board->id,
            'columns' => $columns,
            'cards' => $cards->values(),
        ];
    }

    public function updateCard(Request $request, int $id): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            $card = PrjKanbanCard::query()
                ->whereKey($id)
                ->whereHas('column', fn ($q) => $q->where('board_id', $board->id))
                ->firstOrFail();
            $data = $request->validate([
                'column_id' => 'sometimes|exists:prj_kanban_columns,id',
                'title' => 'sometimes|string|max:255',
                'body' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
            ]);
            if (isset($data['column_id'])) {
                PrjKanbanColumn::query()->where('board_id', $board->id)->whereKey($data['column_id'])->firstOrFail();
                $card->column_id = (int) $data['column_id'];
            }
            if (array_key_exists('title', $data)) {
                $card->title = $data['title'];
            }
            if (array_key_exists('body', $data)) {
                $card->body = $data['body'];
            }
            if (isset($data['sort_order'])) {
                $card->sort_order = $data['sort_order'];
            }
            $card->save();

            return response()->json(['data' => $card->fresh()]);
        }

        $task = ProjectTask::query()->findOrFail($id);
        $data = $request->validate([
            'workflow_status_id' => 'sometimes|exists:prj_workflow_statuses,id',
            'column_id' => 'sometimes|exists:prj_workflow_statuses,id',
        ]);
        $wid = $data['workflow_status_id'] ?? $data['column_id'] ?? null;
        if ($wid !== null) {
            $task->update(['workflow_status_id' => $wid]);
        }

        return response()->json(['data' => $task->fresh()]);
    }

    public function createCard(Request $request): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'body' => 'nullable|string',
                'column_id' => 'required|exists:prj_kanban_columns,id',
                'sort_order' => 'nullable|integer|min:0',
                'link_task_id' => 'nullable|exists:prj_tasks,id',
            ]);
            PrjKanbanColumn::query()->where('board_id', $board->id)->whereKey($data['column_id'])->firstOrFail();
            $max = (int) PrjKanbanCard::query()->where('column_id', $data['column_id'])->max('sort_order');
            $card = PrjKanbanCard::query()->create([
                'column_id' => $data['column_id'],
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'sort_order' => $data['sort_order'] ?? $max + 1,
                'cardable_type' => isset($data['link_task_id']) ? ProjectTask::class : null,
                'cardable_id' => $data['link_task_id'] ?? null,
            ]);

            return response()->json(['data' => ['id' => $card->id, 'card' => $card]], 201);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:prj_projects,id',
            'workflow_status_id' => 'nullable|exists:prj_workflow_statuses,id',
            'column_id' => 'nullable|exists:prj_workflow_statuses,id',
            'assignee_id' => 'nullable|exists:users,id',
        ]);
        $ws = $data['workflow_status_id'] ?? $data['column_id'] ?? WorkflowStatus::query()->orderBy('sort_order')->value('id');
        $task = ProjectTask::query()->create([
            'title' => $data['title'],
            'project_id' => $data['project_id'] ?? null,
            'workflow_status_id' => $ws,
            'status' => 'open',
            'assignee_id' => $data['assignee_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $task->id, 'task' => $task]], 201);
    }

    public function deleteCard(Request $request, int $id): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            PrjKanbanCard::query()
                ->whereKey($id)
                ->whereHas('column', fn ($q) => $q->where('board_id', $board->id))
                ->delete();

            return response()->json([], 204);
        }

        ProjectTask::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function createColumn(Request $request): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            $data = $request->validate([
                'name' => 'required|string|max:191',
                'color' => 'nullable|string|max:32',
                'wip_limit' => 'nullable|integer|min:0|max:500',
            ]);
            $max = (int) PrjKanbanColumn::query()->where('board_id', $board->id)->max('sort_order');
            $row = PrjKanbanColumn::query()->create([
                'board_id' => $board->id,
                'name' => $data['name'],
                'color' => $data['color'] ?? '#64748b',
                'sort_order' => $max + 1,
                'wip_limit' => $data['wip_limit'] ?? null,
            ]);

            return response()->json(['data' => $row], 201);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);
        $max = (int) WorkflowStatus::query()->max('sort_order');
        $row = WorkflowStatus::query()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#64748b',
            'sort_order' => $max + 1,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function updateColumn(Request $request, int $id): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            $col = PrjKanbanColumn::query()->where('board_id', $board->id)->whereKey($id)->firstOrFail();
            $data = $request->validate([
                'name' => 'sometimes|string|max:191',
                'color' => 'nullable|string|max:32',
                'sort_order' => 'nullable|integer|min:0',
                'wip_limit' => 'nullable|integer|min:0|max:500',
            ]);
            $col->update($data);

            return response()->json(['data' => $col->fresh()]);
        }

        $col = WorkflowStatus::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $col->update($data);

        return response()->json(['data' => $col->fresh()]);
    }

    public function deleteColumn(Request $request, int $id): JsonResponse
    {
        if ($this->genericKanbanEnabled() && $request->filled('board_id')) {
            $board = PrjKanbanBoard::query()->findOrFail($request->integer('board_id'));
            PrjKanbanColumn::query()->where('board_id', $board->id)->whereKey($id)->delete();

            return response()->json([], 204);
        }

        WorkflowStatus::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }
}
