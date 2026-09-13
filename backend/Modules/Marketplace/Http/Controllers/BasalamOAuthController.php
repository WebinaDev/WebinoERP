<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Marketplace\Services\BasalamOAuthService;

class BasalamOAuthController extends Controller
{
    public function __construct(protected BasalamOAuthService $oauth) {}

    public function status(): JsonResponse
    {
        return response()->json($this->oauth->status());
    }

    public function getConfig(): JsonResponse
    {
        return response()->json($this->oauth->status());
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => 'nullable|string|max:100',
            'client_secret' => 'nullable|string|max:500',
            'redirect_uri' => 'nullable|url|max:500',
            'scopes' => 'nullable|string|max:2000',
        ]);
        $this->oauth->saveConfig($data);

        return response()->json([
            'ok' => true,
            'status' => $this->oauth->status(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! $this->oauth->isReady()) {
            return response()->json([
                'message' => 'Basalam OAuth is not configured on Marketplace (missing client secret).',
            ], 503);
        }

        $data = $request->validate([
            'site_url' => 'required|url|max:500',
            'return_url' => 'nullable|url|max:500',
        ]);

        try {
            $payload = $this->oauth->start($data['site_url'], $data['return_url'] ?? '');
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json($payload);
    }

    public function refresh(Request $request): JsonResponse
    {
        if (! $this->oauth->isReady()) {
            return response()->json(['message' => 'Basalam OAuth is not configured.'], 503);
        }

        $data = $request->validate([
            'refresh_token' => 'required|string',
            'site_url' => 'nullable|url|max:500',
            'vendor_id' => 'nullable|integer|min:1',
        ]);

        try {
            $payload = $this->oauth->refresh(
                $data['refresh_token'],
                $data['site_url'] ?? null,
                isset($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json($payload);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_url' => 'required|url|max:500',
        ]);
        $this->oauth->markDisconnected($data['site_url']);

        return response()->json(['ok' => true]);
    }

    public function callback(Request $request): RedirectResponse|Response
    {
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        if ($code === '' || $state === '') {
            return response('Missing code or state', 400);
        }

        try {
            $url = $this->oauth->completeAuthorization($code, $state);
        } catch (\Throwable $e) {
            return response($e->getMessage(), 400);
        }

        return redirect()->away($url);
    }
}
