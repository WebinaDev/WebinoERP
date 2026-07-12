<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = PersonalAccessToken::query()
            ->where('tokenable_type', get_class($request->user()))
            ->where('tokenable_id', $request->user()->id)
            ->orderByDesc('last_activity_at')
            ->get([
                'id', 'name', 'device_name', 'ip', 'user_agent', 'last_used_at',
                'last_activity_at', 'created_at', 'expires_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', get_class($request->user()))
            ->where('tokenable_id', $request->user()->id)
            ->whereKey($id)
            ->delete();

        return response()->json([], 204);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken()?->id;
        PersonalAccessToken::query()
            ->where('tokenable_type', get_class($request->user()))
            ->where('tokenable_id', $request->user()->id)
            ->when($current, fn ($q) => $q->where('id', '!=', $current))
            ->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
