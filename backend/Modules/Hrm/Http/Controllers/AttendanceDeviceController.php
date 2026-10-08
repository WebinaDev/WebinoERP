<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendanceDeviceUser;
use Modules\Hrm\Entities\HrmAttendancePunch;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Services\HrmAttendanceIngestService;
use Modules\Hrm\Support\HrmAccess;

class AttendanceDeviceController extends Controller
{
    use PaginatesApi;

    public const VENDORS = ['zkteco', 'suprema', 'hikvision', 'virdi', 'anviz', 'generic'];

    public function index(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $q = HrmAttendanceDevice::query()->withCount('punches')->orderBy('name');

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'device_code' => 'required|string|max:64|alpha_dash|unique:hrm_attendance_devices,device_code',
            'vendor' => ['nullable', Rule::in(self::VENDORS)],
            'location' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
        ]);
        $plain = Str::random(40);
        $device = HrmAttendanceDevice::query()->create([
            'name' => $data['name'],
            'device_code' => $data['device_code'],
            'vendor' => $data['vendor'] ?? 'zkteco',
            'location' => $data['location'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'api_key_hash' => hash('sha256', $plain),
        ]);

        return response()->json([
            'data' => $device->toArray() + [
                'api_key' => $plain,
                'ingest_url' => url('/api/v1/hrm/attendance/ingest'),
            ],
            'message' => 'Device registered. Store the API key; it is not shown again.',
        ], 201);
    }

    public function update(Request $request, HrmAttendanceDevice $device): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'vendor' => ['sometimes', 'nullable', Rule::in(self::VENDORS)],
            'location' => 'sometimes|nullable|string|max:150',
            'is_active' => 'sometimes|boolean',
        ]);
        $device->update($data);

        return response()->json(['data' => $device->fresh()]);
    }

    public function rotateKey(HrmAttendanceDevice $device): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $plain = Str::random(40);
        $device->update(['api_key_hash' => hash('sha256', $plain)]);

        return response()->json([
            'data' => $device->fresh()->toArray() + [
                'api_key' => $plain,
                'ingest_url' => url('/api/v1/hrm/attendance/ingest'),
            ],
        ]);
    }

    public function destroy(HrmAttendanceDevice $device): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $device->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function mappings(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $q = HrmAttendanceDeviceUser::query()->with(['device', 'employee'])->orderBy('device_user_id');
        if ($request->filled('device_id')) {
            $q->where('device_id', $request->integer('device_id'));
        }
        if ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function mappingStore(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'device_id' => 'nullable|exists:hrm_attendance_devices,id',
            'device_user_id' => 'required|string|max:64',
            'employee_id' => 'required|exists:hrm_employees,id',
        ]);
        $row = HrmAttendanceDeviceUser::query()->updateOrCreate(
            ['device_id' => $data['device_id'] ?? null, 'device_user_id' => trim($data['device_user_id'])],
            ['employee_id' => $data['employee_id']]
        );

        return response()->json(['data' => $row->load(['device', 'employee'])], 201);
    }

    public function mappingDestroy(HrmAttendanceDeviceUser $mapping): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $mapping->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function punches(Request $request): JsonResponse
    {
        abort_unless(HrmAccess::seesAllStaff(), 403);
        $q = HrmAttendancePunch::query()->with(['device', 'employee'])->orderByDesc('punched_at')->orderByDesc('id');
        if ($request->filled('device_id')) {
            $q->where('device_id', $request->integer('device_id'));
        }
        if ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }
        if ($request->filled('from')) {
            $q->where('punched_at', '>=', $request->date('from')?->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('punched_at', '<=', $request->date('to')?->endOfDay());
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function assignPunch(Request $request, HrmAttendancePunch $punch, HrmAttendanceIngestService $ingest): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'remember' => 'nullable|boolean',
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);

        return response()->json(['data' => $ingest->assign($punch, $employee, (bool) ($data['remember'] ?? true))]);
    }

    public function importCsv(Request $request, HrmAttendanceIngestService $ingest): JsonResponse
    {
        abort_unless(HrmAccess::canManageStaff(), 403);
        $data = $request->validate([
            'file' => 'required_without:csv|file|max:5120',
            'csv' => 'required_without:file|string',
            'device_id' => 'nullable|exists:hrm_attendance_devices,id',
        ]);
        $csv = isset($data['csv']) ? (string) $data['csv'] : (string) file_get_contents($request->file('file')->getRealPath());
        $device = isset($data['device_id']) ? HrmAttendanceDevice::query()->find($data['device_id']) : null;
        $rows = $ingest->parseCsv($csv);
        $result = $ingest->ingest($device, $rows, 'csv');
        $issues = [];
        foreach ($result['punches'] as $i => $p) {
            if ($p->status === 'applied') {
                continue;
            }
            $issues[] = [
                'line' => $rows[$i]['line'] ?? null,
                'code' => $p->raw_code ?? $p->national_id,
                'punched_at' => optional($p->punched_at)->toIso8601String(),
                'status' => $p->status,
                'note' => $p->conflict_note,
            ];
            if (count($issues) >= 200) {
                break;
            }
        }
        unset($result['punches']);

        return response()->json(['data' => $result + ['parsed' => count($rows), 'issues' => $issues]]);
    }

    /** Device API. Authenticate with X-Device-Id + X-Device-Key. No user session. */
    public function ingest(Request $request, HrmAttendanceIngestService $ingest): JsonResponse
    {
        $deviceCode = (string) ($request->header('X-Device-Id') ?: $request->input('device_code'));
        $key = (string) ($request->header('X-Device-Key') ?: $request->input('api_key'));
        $device = $deviceCode !== ''
            ? HrmAttendanceDevice::query()->where('device_code', $deviceCode)->where('is_active', true)->first()
            : null;
        if (! $device || $key === '' || ! hash_equals((string) $device->api_key_hash, hash('sha256', $key))) {
            return response()->json(['message' => 'Invalid device credentials'], 401);
        }
        $punches = $request->input('punches');
        if (! is_array($punches)) {
            $punches = [$request->except(['api_key', 'device_code'])];
        }
        if (count($punches) > 2000) {
            return response()->json(['message' => 'Too many punches in one request (max 2000)'], 422);
        }
        $result = $ingest->ingest($device, array_values($punches), 'device');
        $rows = collect($result['punches'])->map(fn ($p) => [
            'id' => $p->id,
            'employee_id' => $p->employee_id,
            'status' => $p->status,
            'note' => $p->conflict_note,
            'punched_at' => optional($p->punched_at)->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => [
                'applied' => $result['applied'],
                'unmatched' => $result['unmatched'],
                'conflict' => $result['conflict'],
                'duplicate' => $result['duplicate'],
                'invalid' => $result['invalid'],
                'punches' => $rows,
            ],
        ]);
    }
}
