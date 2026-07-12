<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\PrjAppointment;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PrjAppointment::query()->orderByDesc('starts_at')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function manage(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'id' => 'nullable|exists:prj_appointments,id',
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'customer_user_id' => 'nullable|exists:users,id',
        ]);
        if (! empty($payload['id'])) {
            $a = PrjAppointment::query()->findOrFail($payload['id']);
            $a->update(collect($payload)->except('id')->all());
        } else {
            $payload['created_by'] = $request->user()->id;
            $a = PrjAppointment::query()->create($payload);
        }

        return response()->json(['data' => $a], empty($payload['id']) ? 201 : 200);
    }

    public function destroy(int $id): JsonResponse
    {
        PrjAppointment::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function clientRequest(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function calendar(): JsonResponse
    {
        $rows = PrjAppointment::query()->orderBy('starts_at')->get();

        return response()->json(['data' => $rows]);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        return $this->manage($request);
    }

    public function updateDate(Request $request, int $id): JsonResponse
    {
        $a = PrjAppointment::query()->findOrFail($id);
        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date',
        ]);
        $a->update($data);

        return response()->json(['data' => $a]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => PrjAppointment::query()->findOrFail($id)]);
    }
}
