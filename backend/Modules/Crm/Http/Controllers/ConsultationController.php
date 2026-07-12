<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\AppliesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmConsultation;

class ConsultationController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $query = CrmConsultation::query()->with('account')->orderByDesc('id');
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['status' => 'status', 'account_id' => 'account_id'],
            ['title', 'notes'],
            ['created_at', 'id'],
        );

        return $this->paginatedJsonResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'nullable|exists:crm_accounts,id',
            'title' => 'required|string|max:255',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);
        $row = CrmConsultation::query()->create($data + ['created_by' => $request->user()?->id]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = CrmConsultation::query()->findOrFail($id);
        $data = $request->validate([
            'account_id' => 'nullable|exists:crm_accounts,id',
            'title' => 'sometimes|string|max:255',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);
        $row->update($data);

        return response()->json(['data' => $row->fresh('account')]);
    }
}
