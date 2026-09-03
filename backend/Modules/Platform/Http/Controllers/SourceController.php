<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformSource;

class SourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformSource::query()->latest()->get(['id','uuid','name','provider','base_url','created_at']), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'provider' => 'required|in:github,gitlab,gitea,bitbucket',
            'base_url' => 'nullable|string|max:255',
            'token' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);
        $row = PlatformSource::query()->create($data);
        return response()->json(['success' => true, 'data' => $row->only(['id','uuid','name','provider','base_url','created_at']), 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(PlatformSource $source): JsonResponse
    {
        $source->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
