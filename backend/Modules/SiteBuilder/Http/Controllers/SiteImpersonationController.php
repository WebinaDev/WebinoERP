<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Exceptions\SiteImpersonationException;
use Modules\SiteBuilder\Services\SiteImpersonationService;

class SiteImpersonationController extends Controller
{
    public function exchange(Request $request, SiteImpersonationService $impersonation): JsonResponse
    {
        $data = $request->validate([
            'passport' => ['required', 'string', 'max:1500'],
            'provision_id' => ['required', 'integer', 'min:1'],
            'next' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $result = $impersonation->exchange(
                $data['passport'],
                (int) $data['provision_id'],
                $data['next'] ?? null,
                $request->ip(),
            );
        } catch (SiteImpersonationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => $result]);
    }

    public function exit(Request $request, SiteImpersonationService $impersonation): JsonResponse
    {
        $data = $request->validate([
            'passport' => ['required', 'string', 'max:1500'],
            'provision_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $impersonation->exit(
                $data['passport'],
                isset($data['provision_id']) ? (int) $data['provision_id'] : null,
                $request->ip(),
            );
        } catch (SiteImpersonationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => $result]);
    }
}
