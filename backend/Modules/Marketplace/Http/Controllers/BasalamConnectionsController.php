<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Services\BasalamOAuthService;

class BasalamConnectionsController extends Controller
{
    public function __construct(protected BasalamOAuthService $oauth) {}

    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'connections' => $this->oauth->listConnections(),
            ]);
        } catch (\Illuminate\Database\QueryException|\Throwable) {
            return response()->json([
                'connections' => [],
            ]);
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_url' => 'required|url|max:500',
        ]);
        $this->oauth->markDisconnected($data['site_url']);

        return response()->json([
            'ok' => true,
            'connections' => $this->oauth->listConnections(),
        ]);
    }
}
