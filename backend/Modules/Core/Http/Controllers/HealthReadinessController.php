<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthReadinessController extends Controller
{
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];
        $ok = collect($checks)->every(fn (array $c) => $c['ok']);

        return response()->json([
            'data' => [
                'status' => $ok ? 'ready' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $ok ? 200 : 503);
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'data' => [
                'app' => config('app.name'),
                'env' => config('app.env'),
                'php' => PHP_VERSION,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();

            return ['ok' => true, 'message' => 'reachable', 'pending' => $size];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
