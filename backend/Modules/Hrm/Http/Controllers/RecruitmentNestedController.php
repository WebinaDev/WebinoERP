<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmInterview;
use Modules\Hrm\Entities\HrmJobApplicant;
use Modules\Hrm\Entities\HrmJobPosting;

class RecruitmentNestedController extends Controller
{
    use PaginatesApi;

    public function postingsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmJobPosting::query()->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function postingsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'department' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'closes_at' => 'nullable|date',
        ]);
        $posting = HrmJobPosting::create($data);

        return response()->json(['data' => $posting, 'message' => 'Posting saved'], 201);
    }

    public function applicantsIndex(Request $request): JsonResponse
    {
        $query = HrmJobApplicant::query()->with('jobPosting')->orderByDesc('created_at');
        if ($request->filled('job_posting_id')) {
            $query->where('job_posting_id', $request->integer('job_posting_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function applicantsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_posting_id' => 'required|exists:hrm_job_postings,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'resume_notes' => 'nullable|string',
        ]);
        $applicant = HrmJobApplicant::create([...$data, 'status' => 'applied']);

        return response()->json(['data' => $applicant->load('jobPosting'), 'message' => 'Applicant saved'], 201);
    }

    public function applicantsDestroy(HrmJobApplicant $applicant): JsonResponse
    {
        $applicant->delete();

        return response()->noContent();
    }

    public function applicantHire(Request $request, HrmJobApplicant $applicant): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => 'required|string|max:50|unique:hrm_employees,employee_code',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
        ]);
        $employee = HrmEmployee::create([
            'employee_code' => $data['employee_code'],
            'first_name' => $applicant->first_name,
            'last_name' => $applicant->last_name,
            'email' => $applicant->email,
            'mobile' => $applicant->mobile,
            'department' => $data['department'] ?? $applicant->jobPosting?->department,
            'position' => $data['position'] ?? $applicant->jobPosting?->title,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);
        $applicant->update(['status' => 'hired']);

        return response()->json(['data' => $employee, 'message' => 'Applicant hired'], 201);
    }

    public function interviewsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmInterview::query()->with('applicant')->orderBy('scheduled_at')->paginate($this->perPage($request)));
    }

    public function interviewsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'applicant_id' => 'required|exists:hrm_job_applicants,id',
            'scheduled_at' => 'required|date',
            'interviewer' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);
        $interview = HrmInterview::create($data);

        return response()->json(['data' => $interview->load('applicant'), 'message' => 'Interview scheduled'], 201);
    }
}
