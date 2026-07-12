<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\CoreCacheService;

class MaintenanceParityController extends Controller
{
    public function optimize(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('system_manager')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        App::terminating(function () {
            try {
                Artisan::call('webino:db-optimize');
            } catch (\Throwable $e) {
                Log::channel('single')->error('maintenance.optimize.failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        });

        return response()->json(['data' => ['scheduled' => true]]);
    }

    public function cacheClear(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('system_manager')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate(['type' => 'required|string|in:all,entity,key']);
        if ($data['type'] === 'all') {
            Artisan::call('cache:clear');
        } elseif ($data['type'] === 'entity' && $request->filled('entity')) {
            app(CoreCacheService::class)->flushTag($request->string('entity'));
        } elseif ($data['type'] === 'key' && $request->filled('key')) {
            Cache::forget($request->string('key'));
        }

        return response()->json(['data' => ['cleared' => true]]);
    }

    public function cacheStats(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('system_manager')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => array_merge(
                ['driver' => config('cache.default')],
                app(CoreCacheService::class)->stats()
            ),
        ]);
    }
}
