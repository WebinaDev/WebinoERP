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
            'lead_id' => 'nullable|integer|min:1',
            'project_id' => 'nullable|integer|min:1',
            'product_note' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'installments' => 'nullable|array',
            'installments.*.amount' => 'nullable|numeric|min:0',
            'installments.*.due_date' => 'nullable|date',
            'installments.*.status' => 'nullable|string|max:20',
            'meta' => 'nullable|array',
        ]);
        $data['created_by'] = $request->user()->id;

        $meta = $data['meta'] ?? [];
        if (isset($data['lead_id'])) {
            $meta['lead_id'] = $data['lead_id'];
        }
        if (isset($data['project_id'])) {
            $meta['project_ids'] = array_values(array_unique([...($meta['project_ids'] ?? []), $data['project_id']]));
        }
        if (isset($data['product_note'])) {
            $meta['product_note'] = $data['product_note'];
        }
        if (isset($data['amount'])) {
            $meta['amount'] = $data['amount'];
        }
        if (isset($data['installments'])) {
            $meta['installments'] = $data['installments'];
        }
        unset($data['lead_id'], $data['project_id'], $data['product_note'], $data['amount'], $data['installments'], $data['meta']);
        $data['meta'] = $meta ?: null;

        $contract = DocsContract::create($data);

        return response()->json(['data' => $contract, 'message' => 'Contract created'], 201);
    }

    public function show(DocsContract $contract): JsonResponse
    {
        $data = $contract->toArray();
        $meta = $contract->meta ?? [];
        $data['amount'] = $meta['amount'] ?? null;
        $data['installments'] = $meta['installments'] ?? [];
        if (! empty($meta['lead_id'])) {
            $data['lead'] = ['id' => $meta['lead_id']];
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, DocsContract $contract): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'party_name' => 'nullable|string|max:255',
            'status' => 'sometimes|string|max:20',
            'body' => 'nullable|string',
            'signed_at' => 'nullable|date',
            'project_id' => 'nullable|integer|min:1',
            'product_note' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'installments' => 'nullable|array',
            'installments.*.amount' => 'nullable|numeric|min:0',
            'installments.*.due_date' => 'nullable|date',
            'installments.*.status' => 'nullable|string|max:20',
            'meta' => 'nullable|array',
        ]);

        $meta = array_merge($contract->meta ?? [], $data['meta'] ?? []);
        if (array_key_exists('project_id', $data) && $data['project_id']) {
            $meta['project_ids'] = array_values(array_unique([...($meta['project_ids'] ?? []), $data['project_id']]));
        }
        if (array_key_exists('product_note', $data)) {
            $meta['product_note'] = $data['product_note'];
        }
        if (array_key_exists('amount', $data)) {
            $meta['amount'] = $data['amount'];
        }
        if (array_key_exists('installments', $data)) {
            $meta['installments'] = $data['installments'];
        }
        unset($data['project_id'], $data['product_note'], $data['amount'], $data['installments'], $data['meta']);
        $data['meta'] = $meta;

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
