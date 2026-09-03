<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Platform\Entities\PlatformApiToken;

class ApiTokenController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = PlatformApiToken::query()
            ->latest()
            ->get(['id', 'name', 'abilities', 'user_id', 'expires_at', 'last_used_at', 'created_at']);

        return $this->ok($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'in:read,read:sensitive,write,deploy',
            'expires_at' => 'nullable|date',
        ]);

        $plain = 'wpt_'.Str::random(40);
        $row = PlatformApiToken::query()->create([
            'name' => $data['name'],
            'token_hash' => Hash::make($plain),
            'abilities' => $data['abilities'],
            'user_id' => $request->user()?->id,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return $this->ok([
            'token' => $row->only(['id', 'name', 'abilities', 'expires_at', 'created_at']),
            'plain_token' => $plain,
        ], 201);
    }

    public function destroy(PlatformApiToken $token): JsonResponse
    {
        $token->delete();

        return $this->ok(['deleted' => true]);
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => null,
            'meta' => null,
            'errors' => null,
        ], $status);
    }
}
