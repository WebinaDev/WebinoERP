<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\ProvisionWebinoSiteJob;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

/**
 * Legacy Platform launch endpoint — queues the same async Site Builder job.
 */
class WebinoProvisionController extends Controller
{
    public function launch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provision_id' => 'required|exists:webino_site_provisions,id',
            'server_id' => 'required|exists:platform_servers,id',
            'site_type_slug' => 'nullable|string|max:32',
        ]);

        try {
            $provision = WebinoSiteProvision::query()->findOrFail($data['provision_id']);
            $wizard = $provision->wizard_payload ?? [];
            $wizard['server_id'] = (int) $data['server_id'];
            if (! empty($data['site_type_slug'])) {
                $wizard['site_type_slug'] = $data['site_type_slug'];
            }

            $provision->update([
                'wizard_payload' => $wizard,
                'status' => WebinoSiteProvision::STATUS_PENDING,
                'error_log' => null,
                'progress' => ProvisionProgress::make(ProvisionProgress::PHASE_QUEUED),
            ]);

            ProvisionWebinoSiteJob::dispatch($provision->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'provision' => $provision->fresh(['license', 'package', 'crmAccount']),
                    'queued' => true,
                ],
                'message' => 'Launch queued.',
                'meta' => null,
                'errors' => null,
            ], 202);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'meta' => null,
                'errors' => null,
            ], 422);
        }
    }
}
