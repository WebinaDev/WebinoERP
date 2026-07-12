<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Modules\Hrm\Entities\HrmTrainingEnrollment;
use Modules\Hrm\Entities\HrmTrainingSession;

class TrainingNestedController extends Controller
{
    use PaginatesApi;

    public function coursesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmTrainingCourse::query()->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function coursesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string|max:20',
        ]);
        $course = HrmTrainingCourse::create($data);

        return response()->json(['data' => $course, 'message' => 'Course saved'], 201);
    }

    public function sessionsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmTrainingSession::query()->with('course')->orderBy('starts_at')->paginate($this->perPage($request)));
    }

    public function sessionsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:hrm_training_courses,id',
            'title' => 'required|string|max:200',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'location' => 'nullable|string|max:200',
            'status' => 'nullable|string|max:20',
        ]);
        $session = HrmTrainingSession::create($data);

        return response()->json(['data' => $session->load('course'), 'message' => 'Session saved'], 201);
    }

    public function enrollmentsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmTrainingEnrollment::query()->with(['course', 'employee'])->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function enrollmentsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:hrm_training_courses,id',
            'employee_id' => 'required|exists:hrm_employees,id',
            'status' => 'nullable|string|max:20',
        ]);
        $enrollment = HrmTrainingEnrollment::query()->updateOrCreate(
            ['course_id' => $data['course_id'], 'employee_id' => $data['employee_id']],
            ['status' => $data['status'] ?? 'enrolled']
        );

        return response()->json(['data' => $enrollment->load(['course', 'employee']), 'message' => 'Enrollment saved'], 201);
    }
}
