<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCampaign;

class CampaignController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = SalesCampaign::query()->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $data['created_by'] = $request->user()->id;
        $campaign = SalesCampaign::create($data);

        return response()->json(['data' => $campaign, 'message' => 'Created'], 201);
    }

    public function show(SalesCampaign $campaign): JsonResponse
    {
        return response()->json(['data' => $campaign]);
    }

    public function update(Request $request, SalesCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:20',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);
        $campaign->update($data);

        return response()->json(['data' => $campaign->fresh(), 'message' => 'Updated']);
    }

    public function destroy(SalesCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->noContent();
    }
}
