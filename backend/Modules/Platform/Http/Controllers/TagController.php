<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformTag;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformTag::query()->orderBy('name')->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:64', 'color' => 'nullable|string|max:32']);
        $row = PlatformTag::query()->firstOrCreate(['name' => $data['name']], ['color' => $data['color'] ?? null]);
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null], 201);
    }
}
