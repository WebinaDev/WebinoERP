<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\WebinoDashboardProvisioner;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

class WebinoProvisionController extends Controller
{
    public function __construct(private readonly WebinoDashboardProvisioner $provisioner) {}

    public function launch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provision_id' => 'required|exists:webino_site_provisions,id',
            'server_id' => 'required|exists:platform_servers,id',
            'site_type_slug' => 'nullable|string|max:32',
        ]);
        try {
            $provision = WebinoSiteProvision::query()->findOrFail($data['provision_id']);
            $server = PlatformServer::query()->findOrFail($data['server_id']);
            $resource = $this->provisioner->provisionFromSiteBuilder(
                $provision,
                $server,
                $data['site_type_slug'] ?? null,
            );
            return response()->json(['success' => true, 'data' => ['resource' => $resource, 'provision' => $provision->fresh()], 'message' => null, 'meta' => null, 'errors' => null], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => $e->getMessage(), 'meta' => null, 'errors' => null], 422);
        }
    }
}
