<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjAppointment;
use Modules\Projects\Support\StatusMachine;

class AppointmentController extends Controller
{
    public function index(Request $request, CustomerAccess $access): JsonResponse
    {
        $q = PrjAppointment::query()->orderByDesc('starts_at');
        $access->scopeAppointments($q, $request->user());
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $q->where('starts_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->where('starts_at', '<=', $request->date('to'));
        }
        if ($request->filled('customer_account_id')) {
            $q->where('customer_account_id', $request->integer('customer_account_id'));
        }
        $perPage = min(max((int) $request->input('per_page', 100), 1), 500);
        $paginator = $q->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, CustomerAccess $access): JsonResponse
    {
        return $this->manage($request, $access);
    }

    public function update(Request $request, CustomerAccess $access): JsonResponse
    {
        return $this->manage($request, $access);
    }

    public function manage(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $payload = $request->validate([
            'id' => 'nullable|exists:prj_appointments,id',
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'customer_user_id' => 'nullable|exists:users,id',
        ]);
        $creating = empty($payload['id']);
        $attributes = collect($payload)->except('id')->all();
        if ($creating) {
            $attributes['status'] = $attributes['status'] ?? 'scheduled';
            StatusMachine::assert(null, $attributes['status'], 'appointment');
            $attributes['created_by'] = $request->user()->id;
            $a = PrjAppointment::query()->create($attributes);
        } else {
            $a = PrjAppointment::query()->findOrFail($payload['id']);
            if (! empty($attributes['status'])) {
                StatusMachine::assert($a->status, $attributes['status'], 'appointment');
            }
            $a->update($attributes);
        }

        return response()->json(['data' => $a->fresh()], $creating ? 201 : 200);
    }

    public function destroy(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $this->findVisible($request, $id, $access)->delete();

        return response()->json([], 204);
    }

    public function clientRequest(Request $request, CustomerAccess $access): JsonResponse
    {
        $user = $request->user();
        abort_unless($access->isPortalCustomer($user), 403);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'notes' => 'nullable|string',
        ]);
        $ids = $access->accountIds($user);
        $a = PrjAppointment::query()->create([
            'title' => $data['title'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'requested',
            'customer_user_id' => $user->id,
            'customer_account_id' => $ids[0] ?? null,
            'created_by' => $user->id,
        ]);

        return response()->json(['data' => $a], 201);
    }

    public function calendar(Request $request, CustomerAccess $access): JsonResponse
    {
        $rows = PrjAppointment::query()->orderBy('starts_at');
        $access->scopeAppointments($rows, $request->user());
        if ($request->filled('from')) {
            $rows->where('starts_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $rows->where('starts_at', '<=', $request->date('to'));
        }

        return response()->json(['data' => $rows->limit(500)->get()]);
    }

    public function quickCreate(Request $request, CustomerAccess $access): JsonResponse
    {
        return $this->manage($request, $access);
    }

    public function updateDate(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $a = $this->findVisible($request, $id, $access);
        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $a->update($data);

        return response()->json(['data' => $a->fresh()]);
    }

    public function show(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        return response()->json(['data' => $this->findVisible($request, $id, $access)]);
    }

    private function findVisible(Request $request, int $id, CustomerAccess $access): PrjAppointment
    {
        $q = PrjAppointment::query()->whereKey($id);
        $access->scopeAppointments($q, $request->user());

        return $q->firstOrFail();
    }
}
