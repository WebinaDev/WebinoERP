<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmDependent;
use Modules\Hrm\Services\HrmCompensationRules;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmPersonnelDocument;

class StaffNestedController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmEmployee::query()->orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->boolean('onboarding')) {
            $employee = app(\Modules\Hrm\Services\HrmEmployeeOnboarding::class)->create($request);

            return response()->json(['data' => $employee, 'message' => 'Staff created'], 201);
        }

        $data = $request->validate([
            'employee_code' => 'required|string|max:50|unique:hrm_employees,employee_code',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'contract_type' => 'nullable|string|max:40',
            'contract_end_date' => 'nullable|date',
            'contract_status' => 'nullable|string|max:30',

            'engagement_type' => 'nullable|in:full_time,part_time,contractor,freelance,project,remote',
            'pay_basis' => 'nullable|in:monthly,daily,hourly,project_fee',
            'project_fee' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'insurance_applicable' => 'nullable|boolean',
            'tax_applicable' => 'nullable|boolean',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
        ]);
        $data['created_by'] = $request->user()->id;
        $data = app(HrmCompensationRules::class)->applyDefaults($data);
        app(HrmCompensationRules::class)->assertEmployee(new HrmEmployee($data));
        $employee = HrmEmployee::create($data);

        return response()->json(['data' => $employee, 'message' => 'Staff created'], 201);
    }

    public function destroy(HrmEmployee $staff): JsonResponse
    {
        $staff->delete();

        return response()->noContent();
    }

    public function getProfile(HrmEmployee $staff): JsonResponse
    {
        $profile = HrmEmployeeProfile::query()->firstOrCreate(['employee_id' => $staff->id]);

        return response()->json(['data' => $profile->load('employee')]);
    }

    public function saveProfile(Request $request, HrmEmployee $staff): JsonResponse
    {
        $data = $request->validate([
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|max:10',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:100',
            'emergency_phone' => 'nullable|string|max:20',
            'custom_fields' => 'nullable|array',
            'sheba' => 'nullable|string|max:34',
            'bank_name' => 'nullable|string|max:80',
            'account_holder' => 'nullable|string|max:150',
            'father_name' => 'nullable|string|max:100',
            'insurance_number' => 'nullable|string|max:32',
        ]);
        $profile = HrmEmployeeProfile::query()->updateOrCreate(['employee_id' => $staff->id], $data);

        return response()->json(['data' => $profile, 'message' => 'Profile saved']);
    }

    public function orgPositionsIndex(Request $request): JsonResponse
    {
        $query = HrmOrgPosition::query()->orderBy('sort_order');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function orgPositionStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:hrm_org_positions,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'incumbent_employee_id' => 'nullable|exists:hrm_employees,id',
        ]);
        $position = HrmOrgPosition::create($data);

        return response()->json(['data' => $position, 'message' => 'Position saved'], 201);
    }

    public function orgPositionDestroy(HrmOrgPosition $orgPosition): JsonResponse
    {
        $orgPosition->delete();

        return response()->noContent();
    }

    public function dependentsIndex(HrmEmployee $staff): \Illuminate\Http\JsonResponse
    {
        $rows = HrmDependent::query()->where('employee_id', $staff->id)->orderBy('id')->get();

        return response()->json(['data' => ['dependents' => $rows]]);
    }

    public function dependentsStore(\Illuminate\Http\Request $request, HrmEmployee $staff): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'relation' => 'nullable|string|max:50',
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'is_insured' => 'nullable|boolean',
            'coverage_start' => 'nullable|date',
        ]);
        $data['employee_id'] = $staff->id;
        $row = HrmDependent::query()->create($data);

        return response()->json(['data' => $row, 'message' => 'Dependent saved'], 201);
    }

    public function documentsIndex(HrmEmployee $staff): \Illuminate\Http\JsonResponse
    {
        $rows = HrmPersonnelDocument::query()->where('employee_id', $staff->id)->orderByDesc('id')->get();

        return response()->json(['data' => ['documents' => $rows]]);
    }

    public function documentsStore(\Illuminate\Http\Request $request, HrmEmployee $staff): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:40',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'expires_at' => 'nullable|date',
        ]);
        $file = $request->file('file');
        $path = $file->store('hrm/documents/'.$staff->id, 'local');
        $doc = HrmPersonnelDocument::query()->create([
            'employee_id' => $staff->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'other',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()?->id,
            'expires_at' => $data['expires_at'] ?? null,
            'document_status' => 'pending_signature',
            'signature_placeholder' => 'محل امضا',
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_onboarding_tasks')) {
            app(\Modules\Hrm\Services\HrmOnboardingService::class)->attachDocument($staff->id, $doc->category, $doc->id);
        }

        return response()->json(['data' => $doc, 'message' => 'Document stored'], 201);
    }

    public function documentsDownload(HrmEmployee $staff, HrmPersonnelDocument $document)
    {
        abort_unless($document->employee_id === $staff->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function documentsSign(\Illuminate\Http\Request $request, HrmEmployee $staff, HrmPersonnelDocument $document): \Illuminate\Http\JsonResponse
    {
        abort_unless($document->employee_id === $staff->id, 404);
        $signed = app(\Modules\Hrm\Services\HrmDocumentSignatureService::class)->sign($document, $request, true);

        return response()->json(['data' => $signed, 'message' => 'Signed']);
    }
}
