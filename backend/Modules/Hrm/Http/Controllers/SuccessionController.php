<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmSuccessionPlan;

class SuccessionController extends Controller
{
    public function chart(): JsonResponse
    {
        $positions = HrmOrgPosition::query()->with(['incumbent', 'successors.successor'])->orderBy('sort_order')->get();
        $rows = [];
        foreach ($positions as $p) {
            $rows[] = [
                'id' => $p->id,
                'title' => $p->title,
                'department' => $p->department,
                'parent_id' => $p->parent_id,
                'is_active' => $p->is_active,
                'incumbent' => $p->incumbent ? [
                    'id' => $p->incumbent->id,
                    'name' => trim($p->incumbent->first_name.' '.$p->incumbent->last_name),
                ] : null,
                'successors' => $p->successors->map(fn (HrmSuccessionPlan $s) => [
                    'id' => $s->id,
                    'employee_id' => $s->successor_employee_id,
                    'name' => trim(($s->successor->first_name ?? '').' '.($s->successor->last_name ?? '')),
                    'readiness' => $s->readiness,
                    'is_primary' => $s->is_primary,
                    'notes' => $s->notes,
                ])->values(),
            ];
        }
        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row['parent_id'] ?? 0][] = $row;
        }
        $build = function (int $parentId) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $row) {
                $row['children'] = $build((int) $row['id']);
                $out[] = $row;
            }

            return $out;
        };

        return response()->json(['data' => $build(0)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'position_id' => 'required|exists:hrm_org_positions,id',
            'successor_employee_id' => 'required|exists:hrm_employees,id',
            'readiness' => 'nullable|in:ready_now,1_year,2_year,emergency',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'incumbent_employee_id' => 'nullable|exists:hrm_employees,id',
        ]);
        if (array_key_exists('incumbent_employee_id', $data)) {
            HrmOrgPosition::query()->where('id', $data['position_id'])->update([
                'incumbent_employee_id' => $data['incumbent_employee_id'],
            ]);
        }
        $plan = HrmSuccessionPlan::query()->updateOrCreate(
            [
                'position_id' => $data['position_id'],
                'successor_employee_id' => $data['successor_employee_id'],
            ],
            [
                'readiness' => $data['readiness'] ?? '1_year',
                'is_primary' => $data['is_primary'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json(['data' => $plan->load(['position', 'successor']), 'message' => 'Successor saved'], 201);
    }

    public function destroy(HrmSuccessionPlan $successionPlan): JsonResponse
    {
        $successionPlan->delete();

        return response()->noContent();
    }
}
