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

        if ($request->filled('search')) {
            $q = '%'.$request->string('search').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', $q)
                    ->orWhere('description', 'like', $q);
            });
        }
        if ($request->filled('status') || $request->filled('status_filter')) {
            $query->where('status', $request->input('status', $request->input('status_filter')));
        }
        if ($request->filled('channel') || $request->filled('channel_filter')) {
            $query->where('channel', $request->input('channel', $request->input('channel_filter')));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'channel' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['budget'] = $data['budget'] ?? 0;
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
            'channel' => 'nullable|string|max:50',
            'budget' => 'nullable|numeric|min:0',
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
