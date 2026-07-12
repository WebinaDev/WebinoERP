<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmStage;
use Modules\Crm\Services\CrmAutomationDispatcher;

class DealController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = CrmDeal::query()->with(['account', 'contact', 'stage']);
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['pipeline_id' => 'pipeline_id', 'stage_id' => 'stage_id', 'account_id' => 'account_id'],
            ['name'],
            ['created_at', 'amount', 'name'],
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_id' => 'required|exists:crm_accounts,id',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_stages,id',
            'amount' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        $data['created_by'] = $request->user()->id;
        $deal = CrmDeal::create($data);

        return response()->json(['data' => $deal->load(['account', 'stage']), 'message' => 'Deal created'], 201);
    }

    public function show(CrmDeal $deal): JsonResponse
    {
        $deal->load(['account', 'contact', 'pipeline', 'stage']);

        return response()->json(['data' => $deal]);
    }

    public function update(Request $request, CrmDeal $deal): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'stage_id' => 'sometimes|exists:crm_stages,id',
            'amount' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        $deal->update($data);

        return response()->json(['data' => $deal->fresh(['account', 'stage']), 'message' => 'Deal updated']);
    }

    public function destroy(CrmDeal $deal): JsonResponse
    {
        $deal->delete();

        return response()->noContent();
    }

    public function move(Request $request, CrmDeal $deal, CrmAutomationDispatcher $automation): JsonResponse
    {
        $data = $request->validate(['stage_id' => 'required|exists:crm_stages,id']);
        $stage = CrmStage::query()->findOrFail($data['stage_id']);
        abort_unless($stage->pipeline_id === $deal->pipeline_id, 422, 'Stage must belong to deal pipeline');

        $previousStageId = (int) $deal->stage_id;
        $updates = ['stage_id' => $stage->id, 'probability' => $stage->probability];
        if ($stage->is_won) {
            $updates['won_at'] = now();
            $updates['lost_at'] = null;
        } elseif ($stage->is_closed && ! $stage->is_won) {
            $updates['lost_at'] = now();
            $updates['won_at'] = null;
        }
        $deal->update($updates);
        $fresh = $deal->fresh(['stage', 'account']);
        $automation->dealStageChanged($fresh, $previousStageId);

        return response()->json(['data' => $fresh, 'message' => 'Deal moved']);
    }
}
