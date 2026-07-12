<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Entities\CatalogProduct;
use Modules\Projects\Entities\Project;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->orderByDesc('created_at');
        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => $project]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'is_template' => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'active';
        $p = Project::query()->create($data);

        return response()->json(['data' => $p], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = Project::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'is_template' => 'nullable|boolean',
        ]);
        $p->update($data);

        return response()->json(['data' => $p->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        Project::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => Project::query()->where('is_template', true)->orderBy('name')->get(),
        ]);
    }

    public function assignees(int $id): JsonResponse
    {
        Project::query()->findOrFail($id);

        return response()->json(['data' => DB::table('users')->select('id', 'name', 'email')->limit(50)->get()]);
    }

    public function productProjectsPreview(int $id): JsonResponse
    {
        $product = CatalogProduct::query()->findOrFail($id);
        $projects = Project::query()
            ->whereHas('tasks', function ($q) use ($product) {
                $q->where('title', 'like', '%'.$product->name.'%');
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'status', 'customer_account_id']);

        return response()->json(['data' => ['product_id' => $id, 'projects' => $projects]]);
    }

    public function assignableUsers(): JsonResponse
    {
        $users = DB::table('users')->select('id', 'name', 'email')->orderBy('name')->limit(200)->get();

        return response()->json(['data' => $users]);
    }

    public function details(int $id): JsonResponse
    {
        $p = Project::query()->with(['tasks', 'contracts', 'tickets'])->findOrFail($id);

        return response()->json(['data' => $p]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'projects-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'status', 'customer_account_id', 'created_at']);

            Project::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->id,
                        $p->name,
                        $p->status,
                        $p->customer_account_id,
                        optional($p->created_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
