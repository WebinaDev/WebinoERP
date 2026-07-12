<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\Integrations\Jobs\PortainerStackActionJob;
use Modules\Integrations\Services\PortainerApiClient;

class WebinocrmPortainerController extends Controller
{
    public function stacks(Request $request): JsonResponse
    {
        $endpointId = $request->query('endpoint_id');
        $eid = $endpointId !== null && $endpointId !== '' ? (int) $endpointId : null;

        $client = PortainerApiClient::fromCurrentSettings();
        if ($client === null) {
            return response()->json(['message' => 'Portainer URL or API token not configured.'], 503);
        }

        try {
            $list = $client->stacks($eid);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        CoreInfraAuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'channel' => 'portainer',
            'action' => 'list_stacks',
            'subject_type' => null,
            'subject_id' => null,
            'payload' => ['count' => count($list)],
        ]);

        return response()->json(['data' => $list]);
    }

    public function endpoints(Request $request): JsonResponse
    {
        $client = PortainerApiClient::fromCurrentSettings();
        if ($client === null) {
            return response()->json(['message' => 'Portainer URL or API token not configured.'], 503);
        }

        try {
            $list = $client->endpoints();
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        CoreInfraAuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'channel' => 'portainer',
            'action' => 'list_endpoints',
            'subject_type' => null,
            'subject_id' => null,
            'payload' => ['count' => count($list)],
        ]);

        return response()->json(['data' => $list]);
    }

    public function stackAction(Request $request, int $stackId, string $action): JsonResponse
    {
        if (! in_array($action, ['start', 'stop'], true)) {
            return response()->json(['message' => 'Invalid action'], 422);
        }

        $data = $request->validate([
            'endpoint_id' => 'required|integer|min:1',
        ]);

        $settings = CoreHostingSetting::current();
        if (! filled($settings->portainer_url) || ! filled($settings->portainer_api_token)) {
            return response()->json(['message' => 'Portainer not configured.'], 503);
        }

        PortainerStackActionJob::dispatch($request->user()?->id, $stackId, (int) $data['endpoint_id'], $action);

        return response()->json([
            'data' => [
                'queued' => true,
                'stack_id' => $stackId,
                'action' => $action,
            ],
        ], 202);
    }
}
