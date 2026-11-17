<?php

namespace Modules\Workflow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Workflow\Entities\WfDefinition;
use Modules\Workflow\Entities\WfDelegation;
use Modules\Workflow\Entities\WfInstance;
use Modules\Workflow\Entities\WfStepApproval;
use Modules\Workflow\Services\CentralApprovalEngine;

class WorkflowController extends Controller
{
    public function definitions(Request $request): JsonResponse
    {
        $query = WfDefinition::query()->with('creator');
        if ($request->filled('module_slug')) {
            $query->where('module_slug', $request->input('module_slug'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'module_slug' => 'required|string|max:32',
            'entity_type' => 'required|string|max:64',
            'conditions' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.order' => 'required|integer',
            'steps.*.type' => 'required|in:role,user,direct_manager',
            'steps.*.target' => 'required',
            'steps.*.mode' => 'nullable|in:sequential,parallel',
            'steps.*.sla_hours' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $def = WfDefinition::query()->create([
            'name' => $data['name'],
            'module_slug' => $data['module_slug'],
            'entity_type' => $data['entity_type'],
            'conditions' => $data['conditions'] ?? null,
            'steps' => $data['steps'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $def], 201);
    }

    public function showDefinition(int $id): JsonResponse
    {
        $def = WfDefinition::query()->with('creator')->findOrFail($id);

        return response()->json(['data' => $def]);
    }

    public function inbox(Request $request, CentralApprovalEngine $engine): JsonResponse
    {
        $user = $request->user();
        $pending = WfStepApproval::query()
            ->with(['instance.definition', 'instance.initiator'])
            ->where('status', 'pending')
            ->orderBy('sla_due_at')
            ->get()
            ->filter(fn ($item) => $engine->userCanApprove($user, $item))
            ->values();

        return response()->json(['data' => $pending]);
    }

    public function decide(Request $request, int $id, CentralApprovalEngine $engine): JsonResponse
    {
        $approval = WfStepApproval::query()->with(['instance.definition'])->findOrFail($id);
        if (! $engine->userCanApprove($request->user(), $approval)) {
            return response()->json(['message' => __('messages.action_forbidden')], 403);
        }

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'comment' => 'nullable|string',
        ]);

        $instance = $engine->decide($approval, $request->user(), $data['decision'], $data['comment'] ?? null);

        return response()->json(['data' => $instance]);
    }

    public function delegations(Request $request): JsonResponse
    {
        $list = WfDelegation::query()->with(['delegator', 'delegate'])->orderByDesc('id')->get();

        return response()->json(['data' => $list]);
    }

    public function storeDelegation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delegate_id' => 'required|exists:users,id',
            'module_slug' => 'nullable|string|max:32',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $row = WfDelegation::query()->create([
            'delegator_id' => $request->user()->id,
            'delegate_id' => $data['delegate_id'],
            'module_slug' => $data['module_slug'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => $row->load(['delegator', 'delegate'])], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $query = WfInstance::query()->with(['definition', 'initiator', 'auditLogs.user', 'stepApprovals']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('module_slug')) {
            $query->whereHas('definition', fn ($q) => $q->where('module_slug', $request->input('module_slug')));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function initiate(Request $request, CentralApprovalEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'module_slug' => 'required|string|max:32',
            'entity_type' => 'required|string|max:64',
            'entity_id' => 'required|integer',
            'title' => 'required|string|max:200',
            'amount' => 'nullable|numeric|min:0',
            'context' => 'nullable|array',
        ]);

        $instance = $engine->initiate(
            $data['module_slug'],
            $data['entity_type'],
            (int) $data['entity_id'],
            $data['title'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            $request->user(),
            $data['context'] ?? []
        );

        if (! $instance) {
            return response()->json(['message' => 'No active workflow definition matching entity/conditions'], 422);
        }

        return response()->json(['data' => $instance->load(['definition', 'stepApprovals'])], 201);
    }
}
