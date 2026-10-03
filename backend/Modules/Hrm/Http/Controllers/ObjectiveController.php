<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmKeyResult;
use Modules\Hrm\Entities\HrmObjective;
use Modules\Hrm\Support\HrmAccess;

class ObjectiveController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $q = HrmObjective::query()->with(['employee', 'keyResults', 'cycle'])->orderByDesc('id');
        if (! HrmAccess::seesAllStaff()) {
            $q->where('employee_id', HrmAccess::employee()?->id ?? 0);
        } elseif ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'cycle_id' => 'nullable|exists:hrm_performance_cycles,id',
            'parent_id' => 'nullable|exists:hrm_objectives,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'period' => 'nullable|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:20',
            'key_results' => 'nullable|array',
            'key_results.*.title' => 'required_with:key_results|string|max:200',
            'key_results.*.target_value' => 'nullable|numeric',
            'key_results.*.current_value' => 'nullable|numeric',
            'key_results.*.unit' => 'nullable|string|max:30',
        ]);
        $objective = HrmObjective::query()->create([
            'employee_id' => $data['employee_id'],
            'cycle_id' => $data['cycle_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'period' => $data['period'] ?? null,
            'weight' => $data['weight'] ?? 1,
            'status' => $data['status'] ?? 'active',
        ]);
        foreach ($data['key_results'] ?? [] as $kr) {
            $this->makeKeyResult($objective, $kr);
        }
        $this->recalculate($objective);

        return response()->json(['data' => $objective->load(['employee', 'keyResults']), 'message' => 'Objective saved'], 201);
    }

    public function updateKeyResult(Request $request, HrmKeyResult $keyResult): JsonResponse
    {
        $objective = $keyResult->objective;
        if (! HrmAccess::canManageStaff()) {
            abort_unless(HrmAccess::employee()?->id === $objective->employee_id, 403);
        }
        $data = $request->validate([
            'current_value' => 'required|numeric',
            'title' => 'nullable|string|max:200',
        ]);
        $keyResult->current_value = $data['current_value'];
        if (! empty($data['title']) && HrmAccess::canManageStaff()) {
            $keyResult->title = $data['title'];
        }
        $target = (float) $keyResult->target_value;
        $keyResult->progress = $target == 0.0 ? 0 : (int) min(100, round(((float) $keyResult->current_value / $target) * 100));
        $keyResult->save();
        $this->recalculate($objective);

        return response()->json(['data' => $objective->fresh()->load('keyResults')]);
    }

    /**
     * @param  array<string, mixed>  $kr
     */
    private function makeKeyResult(HrmObjective $objective, array $kr): void
    {
        $target = (float) ($kr['target_value'] ?? 100);
        $current = (float) ($kr['current_value'] ?? 0);
        HrmKeyResult::query()->create([
            'objective_id' => $objective->id,
            'title' => $kr['title'],
            'target_value' => $target,
            'current_value' => $current,
            'unit' => $kr['unit'] ?? null,
            'progress' => $target == 0.0 ? 0 : (int) min(100, round(($current / $target) * 100)),
        ]);
    }

    private function recalculate(HrmObjective $objective): void
    {
        $krs = $objective->keyResults()->get();
        $objective->progress = $krs->isEmpty() ? 0 : (int) round($krs->avg('progress'));
        $objective->save();
    }
}
