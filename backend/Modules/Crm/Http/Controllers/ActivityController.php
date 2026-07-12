<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;

class ActivityController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = CrmActivity::query();
        if ($request->filled('related_model')) {
            $query->where('related_model', $request->input('related_model'));
        }
        if ($request->filled('related_id')) {
            $query->where('related_id', $request->integer('related_id'));
        }
        if ($request->filled('account_id')) {
            $query->where('related_model', CrmAccount::class)
                ->where('related_id', $request->integer('account_id'));
        }
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['type' => 'type', 'assigned_to' => 'assigned_to'],
            ['subject', 'description'],
            ['created_at', 'scheduled_at', 'due_date'],
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:20',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'related_model' => 'required|string|max:255',
            'related_id' => 'required|integer|min:1',
            'outcome' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|max:10',
            'assigned_to' => 'nullable|exists:users,id',
        ]);
        $data['created_by'] = $request->user()->id;
        $activity = CrmActivity::create($data);

        return response()->json(['data' => $activity, 'message' => 'Activity created'], 201);
    }

    public function destroy(CrmActivity $activity): JsonResponse
    {
        $activity->delete();

        return response()->noContent();
    }
}
