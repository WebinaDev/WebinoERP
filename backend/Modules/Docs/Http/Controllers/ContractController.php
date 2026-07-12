<?php

namespace Modules\Docs\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Docs\Entities\DocsContract;

class ContractController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = DocsContract::query()->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'party_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
            'body' => 'nullable|string',
            'signed_at' => 'nullable|date',
        ]);
        $data['created_by'] = $request->user()->id;
        $contract = DocsContract::create($data);

        return response()->json(['data' => $contract, 'message' => 'Contract created'], 201);
    }

    public function show(DocsContract $contract): JsonResponse
    {
        return response()->json(['data' => $contract]);
    }

    public function update(Request $request, DocsContract $contract): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'party_name' => 'nullable|string|max:255',
            'status' => 'sometimes|string|max:20',
            'body' => 'nullable|string',
            'signed_at' => 'nullable|date',
        ]);
        $contract->update($data);

        return response()->json(['data' => $contract->fresh(), 'message' => 'Contract updated']);
    }

    public function destroy(DocsContract $contract): JsonResponse
    {
        $contract->delete();

        return response()->noContent();
    }

    public function cancel(DocsContract $contract): JsonResponse
    {
        $contract->update(['status' => 'cancelled']);

        return response()->json(['data' => $contract->fresh(), 'message' => 'Contract cancelled']);
    }

    public function linkProject(Request $request, DocsContract $contract): JsonResponse
    {
        $data = $request->validate(['project_id' => 'required|integer|min:1']);
        $meta = $contract->meta ?? [];
        $projects = $meta['project_ids'] ?? [];
        $projects[] = $data['project_id'];
        $meta['project_ids'] = array_values(array_unique($projects));
        $contract->update(['meta' => $meta]);

        return response()->json(['data' => $contract->fresh(), 'message' => 'Project linked']);
    }
}
