<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformStorage;

class StorageController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformStorage::query()->latest()->get(['id','uuid','name','driver','endpoint','bucket','region','path_style','created_at']), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'driver' => 'required|in:s3',
            'endpoint' => 'nullable|string|max:255',
            'bucket' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:64',
            'access_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'path_style' => 'nullable|boolean',
        ]);
        $row = PlatformStorage::query()->create($data);
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(PlatformStorage $storage): JsonResponse
    {
        $storage->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
