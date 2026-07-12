<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmOrgPosition;

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
        $data = $request->validate([
            'employee_code' => 'required|string|max:50|unique:hrm_employees,employee_code',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
        ]);
        $data['created_by'] = $request->user()->id;
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
        ]);
        $position = HrmOrgPosition::create($data);

        return response()->json(['data' => $position, 'message' => 'Position saved'], 201);
    }

    public function orgPositionDestroy(HrmOrgPosition $orgPosition): JsonResponse
    {
        $orgPosition->delete();

        return response()->noContent();
    }
}
