<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Controllers\Concerns\VerifiesWebinocrmLicenseSignature;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreLicense;
use Modules\Core\Services\CoreLicenseMetaNormalizer;
use Modules\Core\Services\GitHttpUrlAuthInjector;

/**
 * Authenticated clone URL for dashboard git installs (HMAC same as license/check).
 * POST /api/webinocrm/v1/license/module-clone-url
 */
class WebinocrmModuleCloneUrlController extends Controller
{
    use VerifiesWebinocrmLicenseSignature;

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifyLicenseRequest($request)) {
            return response()->json(['error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Invalid signature']], 403);
        }

        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'license_key' => 'nullable|string|max:255',
            'module_slug' => 'required|string|max:64',
            'ts' => 'required|integer',
            'signature' => 'nullable|string',
        ]);

        $slug = $data['module_slug'];

        $row = CoreLicense::query()
            ->where('domain', $data['domain'])
            ->when($request->filled('license_key'), fn ($q) => $q->where('license_key', $request->input('license_key')))
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'License not found']], 404);
        }

        $norm = CoreLicenseMetaNormalizer::normalize($row->meta);
        if (! in_array($slug, $norm['licensed_modules'], true)) {
            return response()->json(['error' => ['code' => 'NOT_LICENSED', 'message' => 'Module not entitled']], 403);
        }

        $urls = CoreLicenseMetaNormalizer::mergeModuleGitReposWithRegistry($norm['module_git_repos']);
        $baseUrl = $urls[$slug] ?? null;
        if (! is_string($baseUrl) || $baseUrl === '') {
            return response()->json(['error' => ['code' => 'NO_REPO', 'message' => 'No clone URL for module']], 404);
        }

        $hosting = CoreHostingSetting::current();
        $pat = $hosting->git_pat;
        $cloneUrl = $baseUrl;
        if (is_string($pat) && $pat !== '' && str_starts_with(strtolower($baseUrl), 'http')) {
            $cloneUrl = GitHttpUrlAuthInjector::inject($baseUrl, 'oauth2', $pat);
        }

        return response()->json([
            'data' => [
                'clone_url' => $cloneUrl,
                'module_slug' => $slug,
            ],
        ]);
    }
}
