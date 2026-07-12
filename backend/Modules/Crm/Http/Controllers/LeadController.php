<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\AppliesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Http\Requests\StoreLeadRequest;
use Modules\Crm\Http\Requests\UpdateLeadRequest;
use Modules\Crm\Services\LeadScoringService;

class LeadController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmLead::class);

        $query = CrmLead::query()->with(['status', 'source', 'assignedTo']);
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            [
                'status_id' => 'status_id',
                'source_id' => 'source_id',
                'assigned_to' => 'assigned_to',
            ],
            ['topic', 'first_name', 'last_name', 'email', 'mobile'],
            ['created_at', 'lead_score', 'topic'],
        );

        return $this->paginatedJsonResponse($paginator);
    }

    public function store(StoreLeadRequest $request, LeadScoringService $scoring): JsonResponse
    {
        $this->authorize('create', CrmLead::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $lead = CrmLead::create($data);
        $scoring->applyAndSave($lead);
        $lead->load(['status', 'source']);

        return response()->json(['data' => $lead->fresh(['status', 'source']), 'message' => 'Lead created'], 201);
    }

    public function show(CrmLead $lead): JsonResponse
    {
        $this->authorize('view', $lead);
        $lead->load(['status', 'source', 'assignedTo']);

        return response()->json(['data' => $lead]);
    }

    public function update(UpdateLeadRequest $request, CrmLead $lead, LeadScoringService $scoring): JsonResponse
    {
        $this->authorize('update', $lead);
        $lead->update($request->validated());
        $scoring->applyAndSave($lead);
        $lead->load(['status', 'source']);

        return response()->json(['data' => $lead->fresh(['status', 'source']), 'message' => 'Lead updated']);
    }

    public function destroy(CrmLead $lead)
    {
        $this->authorize('delete', $lead);
        $lead->delete();

        return response()->noContent();
    }
}
