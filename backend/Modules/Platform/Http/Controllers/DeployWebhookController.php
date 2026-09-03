<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Jobs\DeployResourceJob;

class DeployWebhookController extends Controller
{
    public function handle(Request $request, string $token): JsonResponse
    {
        $resource = PlatformResource::query()
            ->where('settings->deploy_webhook_token', $token)
            ->first();

        if (! $resource) {
            // Fallback scan for SQLite/json drivers that may not support JSON where
            $resource = PlatformResource::query()->get()->first(function (PlatformResource $r) use ($token) {
                return ($r->settings['deploy_webhook_token'] ?? null) === $token;
            });
        }

        if (! $resource) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'platform.webhook_not_found', 'meta' => null, 'errors' => null], 404);
        }

        $deployment = PlatformDeployment::query()->create([
            'resource_id' => $resource->id,
            'status' => 'queued',
            'triggered_by' => null,
            'started_at' => now(),
            'logs' => 'Triggered via deploy webhook',
        ]);
        DeployResourceJob::dispatch($deployment->id);

        return response()->json(['success' => true, 'data' => $deployment, 'message' => null, 'meta' => null, 'errors' => null], 202);
    }
}
