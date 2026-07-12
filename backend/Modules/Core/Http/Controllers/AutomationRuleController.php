<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreAutomationRule;
use Modules\Core\Services\AutomationEngine;

class AutomationRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);
        $q = CoreAutomationRule::query()->orderBy('priority')->orderByDesc('id');
        if ($request->filled('trigger')) {
            $q->where('trigger', $request->string('trigger')->toString());
        }

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'trigger' => 'required|string|max:80',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:1|max:100000',
        ]);

        $row = CoreAutomationRule::query()->create([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 100),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = CoreAutomationRule::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'trigger' => 'sometimes|string|max:80',
            'conditions' => 'nullable|array',
            'actions' => 'sometimes|array|min:1',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1|max:100000',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        CoreAutomationRule::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function execute(Request $request, int $id, AutomationEngine $engine): JsonResponse
    {
        $rule = CoreAutomationRule::query()->findOrFail($id);
        $event = $request->validate(['event' => 'nullable|array'])['event'] ?? [];
        $result = $engine->runRule($rule, $event);

        return response()->json(['data' => $result]);
    }

    public function trigger(Request $request, AutomationEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'trigger' => 'required|string|max:80',
            'event' => 'nullable|array',
        ]);

        return response()->json([
            'data' => $engine->dispatch(
                $data['trigger'],
                $data['event'] ?? []
            ),
        ]);
    }
}
