<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\BulkActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Services\DuplicateDetectionService;
use Modules\Crm\Services\LeadConversionService;
use Modules\Crm\Services\LeadScoringService;

class LeadAdvancedController extends Controller
{
    public function score(CrmLead $lead, LeadScoringService $scoring): JsonResponse
    {
        $this->authorize('view', $lead);
        $scoring->applyAndSave($lead);

        return response()->json(['data' => $lead->fresh()]);
    }

    public function convert(Request $request, int $id, LeadConversionService $conversion): JsonResponse
    {
        $lead = CrmLead::query()->findOrFail($id);
        $this->authorize('update', $lead);

        $options = $request->validate([
            'existing_account_id' => 'nullable|exists:crm_accounts,id',
            'create_contact' => 'nullable|boolean',
            'create_deal' => 'nullable|boolean',
        ]);

        try {
            $result = $conversion->convert($lead, $options);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function duplicates(CrmLead $lead, DuplicateDetectionService $detector): JsonResponse
    {
        $this->authorize('view', $lead);
        $matches = $detector->findDuplicates($lead)->map(fn ($row) => [
            'id' => $row['lead']->id,
            'topic' => $row['lead']->topic,
            'email' => $row['lead']->email,
            'confidence' => $row['confidence'],
            'reasons' => $row['reasons'],
        ]);

        return response()->json(['data' => $matches]);
    }

    public function merge(Request $request, DuplicateDetectionService $detector): JsonResponse
    {
        $data = $request->validate([
            'primary_id' => 'required|exists:crm_leads,id',
            'duplicate_id' => 'required|exists:crm_leads,id|different:primary_id',
        ]);
        $primary = CrmLead::query()->findOrFail($data['primary_id']);
        $duplicate = CrmLead::query()->findOrFail($data['duplicate_id']);
        $this->authorize('update', $primary);
        $this->authorize('delete', $duplicate);

        $merged = $detector->merge($primary, $duplicate);

        return response()->json(['data' => $merged]);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|min:1',
            'assigned_to' => 'required|exists:users,id',
        ]);
        CrmLead::query()->whereIn('id', $data['ids'])->update(['assigned_to' => $data['assigned_to']]);

        return response()->json(['data' => ['updated' => count($data['ids'])]]);
    }

    public function bulkDelete(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');
        CrmLead::query()->whereIn('id', $ids)->delete();

        return response()->json(['data' => ['deleted' => count($ids)]]);
    }

    public function recomputeScores(LeadScoringService $scoring): JsonResponse
    {
        $count = $scoring->recomputeAll();

        return response()->json(['data' => ['updated' => $count]]);
    }
}
