<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\SystemLogger;

class LogParityController extends Controller
{
    public function __construct(private SystemLogger $logger) {}

    public function console(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'level' => 'nullable|string|in:emergency,alert,critical,error,warning,notice,info,debug',
            'message' => 'required|string|max:10000',
            'context' => 'nullable|array',
            'error_code' => 'nullable|string|max:32',
        ]);
        $this->logger->log(
            $payload['level'] ?? 'info',
            $payload['message'],
            $payload['context'] ?? [],
            'frontend',
            $payload['error_code'] ?? null,
        );

        return response()->json(['data' => ['logged' => true]]);
    }

    public function userAction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => 'required|string|max:100',
            'meta' => 'nullable|array',
        ]);
        $this->logger->info($payload['action'], $payload['meta'] ?? [], 'user_action');

        return response()->json(['data' => ['logged' => true]]);
    }

    public function deleteSystem(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('system_manager')) {
            return response()->json(['data' => ['message' => 'دسترسی کافی ندارید']], 403);
        }
        DB::table('core_system_logs')->truncate();

        return response()->json([], 204);
    }
}
