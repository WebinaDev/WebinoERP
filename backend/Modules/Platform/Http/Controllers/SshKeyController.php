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
            ...$data,
            'fingerprint' => $fp,
            'created_by' => $request->user()?->id,
        ]);
        return response()->json(['success' => true, 'data' => $key->only(['id','name','fingerprint','created_at']), 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(PlatformSshKey $sshKey): JsonResponse
    {
        $sshKey->delete();
        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }
}
