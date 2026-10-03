<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Hrm\Entities\HrmLessonProgress;
use Modules\Hrm\Entities\HrmTrainingCertificate;
use Modules\Hrm\Entities\HrmTrainingEnrollment;
use Modules\Hrm\Entities\HrmTrainingLesson;
use Modules\Hrm\Services\HrmCertificateService;
use Modules\Hrm\Support\HrmAccess;

class LmsController extends Controller
{
    use PaginatesApi;

    public function lessonsIndex(Request $request): JsonResponse
    {
        $q = HrmTrainingLesson::query()->with('course')->orderBy('sort_order');
        if ($request->filled('course_id')) {
            $q->where('course_id', $request->integer('course_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function lessonsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => 'required|exists:hrm_training_courses,id',
            'title' => 'required|string|max:200',
            'body' => 'nullable|string',
            'content_type' => 'nullable|in:text,link,document,video',
            'material_url' => 'nullable|string|max:500',
            'duration_minutes' => 'nullable|integer|min:0|max:2000',
            'sort_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
        ]);
        $lesson = HrmTrainingLesson::query()->create($data);

        return response()->json(['data' => $lesson->load('course'), 'message' => 'Lesson saved'], 201);
    }

    public function complete(Request $request, HrmTrainingLesson $lesson, HrmCertificateService $certs): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'progress_percent' => 'nullable|integer|min:0|max:100',
        ]);
        $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : HrmAccess::employee()?->id;
        abort_unless($employeeId, 422, 'employee_id required');
        if (! HrmAccess::canManageStaff()) {
            abort_unless(HrmAccess::employee()?->id === $employeeId, 403);
        }
        $percent = (int) ($data['progress_percent'] ?? 100);
        $enrollment = HrmTrainingEnrollment::query()
            ->where('course_id', $lesson->course_id)
            ->where('employee_id', $employeeId)
            ->first();
        $progress = HrmLessonProgress::query()->updateOrCreate(
            ['lesson_id' => $lesson->id, 'employee_id' => $employeeId],
            [
                'enrollment_id' => $enrollment?->id,
                'progress_percent' => $percent,
                'completed_at' => $percent >= 100 ? now() : null,
            ]
        );
        $certificate = $percent >= 100 ? $certs->syncEnrollment($lesson->course_id, $employeeId) : null;

        return response()->json([
            'data' => [
                'progress' => $progress,
                'certificate' => $certificate ? $this->certificatePayload($certificate) : null,
            ],
            'message' => 'Progress saved',
        ]);
    }

    public function issueCertificate(Request $request, HrmTrainingEnrollment $enrollment, HrmCertificateService $certs): JsonResponse
    {
        $data = $request->validate(['signer_name' => 'nullable|string|max:150']);
        $cert = $certs->issue($enrollment->course_id, $enrollment->employee_id, $enrollment->id, $data['signer_name'] ?? null);

        return response()->json(['data' => $this->certificatePayload($cert)], 201);
    }

    public function certificateShow(HrmTrainingCertificate $certificate)
    {
        if (! HrmAccess::seesAllStaff()) {
            abort_unless(HrmAccess::employee()?->id === $certificate->employee_id, 403);
        }
        if (request()->string('format')->toString() === 'html') {
            $html = $certificate->html ?: app(HrmCertificateService::class)->html($certificate);

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return response()->json(['data' => $this->certificatePayload($certificate)]);
    }

    public function myLearning(): JsonResponse
    {
        $employee = HrmAccess::employee();
        if (! $employee) {
            return response()->json(['data' => ['enrollments' => [], 'lessons' => [], 'certificates' => []]]);
        }
        $enrollments = HrmTrainingEnrollment::query()->with('course')->where('employee_id', $employee->id)->get();
        $courseIds = $enrollments->pluck('course_id');
        $lessons = HrmTrainingLesson::query()->whereIn('course_id', $courseIds)->orderBy('sort_order')->get();
        $progress = HrmLessonProgress::query()->where('employee_id', $employee->id)->get()->keyBy('lesson_id');
        $certs = HrmTrainingCertificate::query()->where('employee_id', $employee->id)->get()->map(fn ($c) => $this->certificatePayload($c));

        return response()->json(['data' => [
            'enrollments' => $enrollments,
            'lessons' => $lessons->map(function ($l) use ($progress) {
                $row = $progress->get($l->id);

                return [
                    'id' => $l->id,
                    'course_id' => $l->course_id,
                    'title' => $l->title,
                    'content_type' => $l->content_type,
                    'material_url' => $l->material_url,
                    'body' => $l->body,
                    'is_required' => $l->is_required,
                    'progress' => $row->progress_percent ?? 0,
                    'completed_at' => optional($row?->completed_at)?->toIso8601String(),
                ];
            }),
            'certificates' => $certs,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function certificatePayload(HrmTrainingCertificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'serial_no' => $certificate->serial_no,
            'course_id' => $certificate->course_id,
            'employee_id' => $certificate->employee_id,
            'issued_at' => optional($certificate->issued_at)?->toIso8601String(),
            'signer_name' => $certificate->signer_name,
            'html' => $certificate->html,
            'pdf_available' => $certificate->pdf_path !== null,
            'pdf_url' => $certificate->pdf_path ? Storage::disk('public')->url($certificate->pdf_path) : null,
        ];
    }
}
