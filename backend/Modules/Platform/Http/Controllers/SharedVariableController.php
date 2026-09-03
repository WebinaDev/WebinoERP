<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformSharedVariable;

class SharedVariableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PlatformSharedVariable::query()->latest();
        if ($request->filled('project_id')) $q->where('project_id', $request->integer('project_id'));
        return response()->json(['success' => true, 'data' => $q->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:120',
            'value' => 'nullable|string',
            'is_secret' => 'nullable|boolean',
            'project_id' => 'nullable|exists:platform_projects,id',
        ]);
        $row = PlatformSharedVariable::query()->create($data);
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(PlatformSharedVariable $variable): JsonResponse
    {
        $variable->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
