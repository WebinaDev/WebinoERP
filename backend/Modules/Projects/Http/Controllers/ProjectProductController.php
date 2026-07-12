<?php

namespace Modules\Projects\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Entities\CatalogProduct;
use Modules\Projects\Entities\PrjTaskTemplate;

class ProjectProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CatalogProduct::query()->orderBy('name')->get()]);
    }

    public function updateTaskTemplate(Request $request, int $id): JsonResponse
    {
        $p = CatalogProduct::query()->findOrFail($id);
        $data = $request->validate([
            'task_template_id' => 'nullable|integer|min:0',
            'service_task_type' => 'nullable|string|max:50',
            'task_template' => 'nullable|array',
        ]);
        $p->update($data);

        return response()->json(['data' => $p->fresh()]);
    }

    public function taskTemplates(): JsonResponse
    {
        return response()->json(['data' => PrjTaskTemplate::query()->orderByDesc('id')->limit(200)->get()]);
    }
}
