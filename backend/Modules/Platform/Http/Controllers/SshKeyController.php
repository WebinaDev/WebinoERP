<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformSshKey;

class SshKeyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => PlatformSshKey::query()->latest()->get(['id','name','fingerprint','created_at']), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'private_key' => 'required|string',
            'public_key' => 'nullable|string',
        ]);
        $fp = null;
        if (! empty($data['public_key'])) {
            $fp = substr(hash('sha256', $data['public_key']), 0, 32);
        }
        $key = PlatformSshKey::query()->create([
            'name' => $data['name'],
            'private_key' => $data['private_key'],
            'public_key' => $data['public_key'] ?? null,
            'fingerprint' => $fp,
            'created_by' => $request->user()?->id,
        ]);
        return response()->json(['success' => true, 'data' => $key->only(['id','name','fingerprint','created_at']), 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(Request $request, PlatformSshKey $sshKey): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user && (int) $sshKey->created_by === (int) $user->id;
        $isAdmin = $user && $user->hasRole('system_manager');
        if (! $isOwner && ! $isAdmin) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden',
                'meta' => null,
                'errors' => ['code' => 'FORBIDDEN'],
            ], 403);
        }

        $sshKey->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
