<?php

namespace Modules\Hrm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmObjective;
use Modules\Hrm\Entities\HrmOnboarding;
use Modules\Hrm\Entities\HrmOnboardingTask;
use Modules\Hrm\Entities\HrmPersonnelDocument;
use Modules\Hrm\Entities\HrmReview360;
use Modules\Hrm\Entities\HrmReview360Rating;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Services\HrmDocumentSignatureService;
use Modules\Hrm\Services\HrmOnboardingService;
use Modules\Hrm\Support\HrmAccess;

class MeSuiteController extends Controller
{
    public function shiftCalendar(Request $request): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => []]);
        }
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to', now()->endOfMonth()->toDateString()))->toDateString();
        $rows = HrmShiftAssignment::query()
            ->with('template:id,name,start_time,end_time')
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function onboarding(): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => null]);
        }
        $row = HrmOnboarding::query()->with('tasks')->where('employee_id', $employee->id)->orderByDesc('id')->first();
        $done = $row ? $row->tasks->where('status', 'done')->count() : 0;
        $total = $row ? $row->tasks->count() : 0;

        return response()->json(['data' => [
            'onboarding' => $row,
            'progress' => ['done' => $done, 'total' => $total],
        ]]);
    }

    public function completeOnboardingTask(HrmOnboardingTask $onboardingTask, HrmOnboardingService $service): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee && $onboardingTask->onboarding?->employee_id === $employee->id, 404);
        if ($onboardingTask->document_category) {
            return response()->json(['message' => 'Upload the required document to complete this task'], 422);
        }
        $onboardingTask->update(['status' => 'done', 'completed_at' => now()]);

        return response()->json(['data' => $service->refreshProgress($onboardingTask->onboarding)->load('tasks')]);
    }

    public function objectives(): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => []]);
        }
        $rows = HrmObjective::query()->with('keyResults')->where('employee_id', $employee->id)->orderByDesc('id')->get();

        return response()->json(['data' => $rows]);
    }

    public function reviews(): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => ['pending' => [], 'about_me' => []]]);
        }
        $pending = HrmReview360Rating::query()
            ->with('review.employee')
            ->where('rater_employee_id', $employee->id)
            ->where('status', 'pending')
            ->get();
        $about = HrmReview360::query()->with('ratings')->where('employee_id', $employee->id)->get()
            ->each(function (HrmReview360 $review) {
                $review->ratings->each(function (HrmReview360Rating $rating) {
                    $rating->feedback = null;
                    $rating->setRelation('rater', null);
                });
            });

        return response()->json(['data' => ['pending' => $pending, 'about_me' => $about]]);
    }

    public function signDocument(Request $request, HrmPersonnelDocument $document, HrmDocumentSignatureService $signer): JsonResponse
    {
        $employee = HrmAccess::employee();
        abort_unless($employee && $document->employee_id === $employee->id, 404);
        $signed = $signer->sign($document, $request, false);

        return response()->json(['data' => $signed, 'message' => 'Signed']);
    }
}
