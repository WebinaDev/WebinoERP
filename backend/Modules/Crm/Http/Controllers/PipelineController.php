<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;

class PipelineController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = CrmPipeline::query()->with('stages')->orderBy('name');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()->id;
        $pipeline = CrmPipeline::create($data);

        return response()->json(['data' => $pipeline, 'message' => 'Pipeline created'], 201);
    }

    public function show(CrmPipeline $pipeline): JsonResponse
    {
        $pipeline->load('stages');

        return response()->json(['data' => $pipeline]);
    }

    public function update(Request $request, CrmPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $pipeline->update($data);

        return response()->json(['data' => $pipeline->fresh('stages'), 'message' => 'Pipeline updated']);
    }

    public function destroy(CrmPipeline $pipeline): JsonResponse
    {
        $pipeline->delete();

        return response()->noContent();
    }

    public function storeStage(Request $request, CrmPipeline $pipeline): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'required|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'color' => 'required|string|max:7',
            'is_closed' => 'nullable|boolean',
            'is_won' => 'nullable|boolean',
        ]);
        $stage = $pipeline->stages()->create($data);

        return response()->json(['data' => $stage, 'message' => 'Stage created'], 201);
    }

    public function updateStage(Request $request, CrmPipeline $pipeline, CrmStage $stage): JsonResponse
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'color' => 'sometimes|string|max:7',
        ]);
        $stage->update($data);

        return response()->json(['data' => $stage->fresh(), 'message' => 'Stage updated']);
    }

    public function destroyStage(CrmPipeline $pipeline, CrmStage $stage): JsonResponse
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);
        $stage->delete();

        return response()->noContent();
    }

    public function kanban(CrmPipeline $pipeline): JsonResponse
    {
        $pipeline->load('stages');
        $deals = CrmDeal::query()
            ->where('pipeline_id', $pipeline->id)
            ->with(['account', 'contact'])
            ->get()
            ->groupBy('stage_id');

        $columns = $pipeline->stages->map(fn ($stage) => [
            'stage' => $stage,
            'deals' => $deals->get($stage->id, collect())->values(),
        ]);

        return response()->json(['data' => ['pipeline' => $pipeline, 'columns' => $columns]]);
    }
}
