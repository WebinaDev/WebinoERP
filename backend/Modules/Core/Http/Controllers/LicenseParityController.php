<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;

class LicenseParityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CoreLicense::query()->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string|max:191|unique:core_licenses,license_key',
            'domain' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'meta' => 'nullable|array',
        ]);
        $data['created_by'] = $request->user()?->id;

        if (isset($data['meta'])) {
            try {
                $sanitized = CoreLicenseMetaNormalizer::validateForStorage($data['meta']);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            $data['meta'] = is_array($sanitized) && count($sanitized) > 0 ? $sanitized : null;
        }

        $license = CoreLicense::query()->create($data);

        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        return response()->json(['data' => $license], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $data = $request->validate([
            'domain' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:50',
            'expires_at' => 'nullable|date',
            'max_users' => 'nullable|integer|min:0',
            'meta' => 'sometimes|nullable|array',
            'replace_meta' => 'sometimes|boolean',
        ]);

        $replaceMeta = (bool) ($data['replace_meta'] ?? false);
        unset($data['replace_meta']);

        if (array_key_exists('meta', $data)) {
            $incoming = $data['meta'];
            unset($data['meta']);
            try {
                $sanitized = CoreLicenseMetaNormalizer::validateForStorage($incoming);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            if ($replaceMeta) {
                $data['meta'] = is_array($sanitized) && count($sanitized) > 0 ? $sanitized : null;
            } else {
                $merged = array_merge(is_array($license->meta) ? $license->meta : [], is_array($sanitized) ? $sanitized : []);
                $data['meta'] = count($merged) > 0 ? $merged : null;
            }
        }

        $license->update($data);

        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        return response()->json(['data' => $license->fresh()]);
    }

    public function renew(int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $license->update([
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        return response()->json(['data' => $license]);
    }

    public function cancel(int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $license->update(['status' => 'cancelled']);

        CoreLicenseMetaNormalizer::forgetCheckCache($license->domain, $license->license_key);

        return response()->json(['data' => $license]);
    }

    public function destroy(int $id): JsonResponse
    {
        $license = CoreLicense::query()->findOrFail($id);
        $domain = $license->domain;
        $key = $license->license_key;
        $license->delete();

        CoreLicenseMetaNormalizer::forgetCheckCache($domain, $key);

        return response()->json([], 204);
    }
}
