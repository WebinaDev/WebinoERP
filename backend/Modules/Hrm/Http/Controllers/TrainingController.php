<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Modules\Hrm\Entities\HrmTrainingEnrollment;

class TrainingController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmTrainingCourse::query()->withCount('enrollments')->orderByDesc('start_date');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string|max:20',
        ]);

        $course = HrmTrainingCourse::create($data);

        return response()->json(['data' => $course, 'message' => 'Course created'], 201);
    }

    public function show(HrmTrainingCourse $course): JsonResponse
    {
        $course->load(['enrollments.employee']);

        return response()->json(['data' => $course]);
    }

    public function update(Request $request, HrmTrainingCourse $course): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'sometimes|string|max:20',
        ]);

        $course->update($data);

        return response()->json(['data' => $course->fresh(), 'message' => 'Course updated']);
    }

    public function destroy(HrmTrainingCourse $course): JsonResponse
    {
        $course->delete();

        return response()->noContent();
    }

    public function enroll(Request $request, HrmTrainingCourse $course): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'status' => 'nullable|string|max:20',
        ]);

        $enrollment = HrmTrainingEnrollment::updateOrCreate(
            ['course_id' => $course->id, 'employee_id' => $data['employee_id']],
            ['status' => $data['status'] ?? 'enrolled']
        );

        return response()->json(['data' => $enrollment->load('employee'), 'message' => 'Enrolled'], 201);
    }
}
