<?php

namespace Modules\Hrm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmWorkforceBudget;
use Modules\Hrm\Services\HrmAnalyticsService;
use Modules\Hrm\Services\HrmWorkforceBudgetService;

class HrAnalyticsController extends Controller
{
    public function summary(Request $request, HrmAnalyticsService $analytics): JsonResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        abort_if($from->gt($to), 422, 'Invalid range');

        return response()->json(['data' => $analytics->summary($from, $to)]);
    }

    public function budgets(Request $request, HrmWorkforceBudgetService $budgets): JsonResponse
    {
        abort_unless(\Modules\Hrm\Support\HrmAccess::seesAllStaff(), 403);
        $year = $request->integer('year') ?: null;
        $month = $request->integer('month') ?: null;
        $result = $budgets->compare($year, $month);

        return response()->json([
            'data' => $result + [
                'budgets' => HrmWorkforceBudget::query()->where('year', $result['year'])->orderBy('department')->orderBy('month')->get(),
                'departments' => \Modules\Hrm\Entities\HrmEmployee::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department'),
            ],
        ]);
    }

    public function budgetsStore(Request $request): JsonResponse
    {
        abort_unless(\Modules\Hrm\Support\HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'department' => 'required|string|max:150',
            'year' => 'required|integer|min:1300|max:2100',
            'month' => 'nullable|integer|min:0|max:12',
            'headcount' => 'required|integer|min:0|max:100000',
            'cost_budget' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
        $month = ! empty($data['month']) ? (int) $data['month'] : null;
        $row = HrmWorkforceBudget::query()->updateOrCreate(
            ['department' => trim($data['department']), 'year' => $data['year'], 'month' => $month],
            ['headcount' => $data['headcount'], 'cost_budget' => $data['cost_budget'], 'notes' => $data['notes'] ?? null]
        );

        return response()->json(['data' => $row, 'message' => 'Budget saved'], 201);
    }

    public function budgetsDestroy(HrmWorkforceBudget $budget): JsonResponse
    {
        abort_unless(\Modules\Hrm\Support\HrmAccess::canManageStaff(), 403);
        $budget->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
