<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Platform\Entities\PlatformResource;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class CrmSitesController extends Controller
{
    public function show(int $accountId): JsonResponse
    {
        $resources = PlatformResource::query()
            ->with(['domains'])
            ->where('crm_account_id', $accountId)
            ->latest()
            ->get();
        $provisions = WebinoSiteProvision::query()
            ->with(['license', 'package'])
            ->where('crm_account_id', $accountId)
            ->latest()
            ->get();
        return response()->json([
            'success' => true,
            'data' => [
                'resources' => $resources,
                'provisions' => $provisions,
            ],
            'message' => null,
            'meta' => null,
            'errors' => null,
        ]);
    }
}
