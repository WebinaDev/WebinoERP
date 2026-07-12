<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class WebinocrmWebinoServerWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $settings = CoreHostingSetting::current();
        $secret = (string) ($settings->provision_webhook_secret ?? '');
        if ($secret !== '') {
            $sig = (string) $request->header('X-Webhook-Signature', '');
            $body = $request->getContent();
            $expected = hash_hmac('sha256', $body, $secret);
            if (! hash_equals($expected, $sig)) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }
        }

        $event = (string) $request->input('event', '');
        $slug = (string) $request->input('slug', '');
        $provision = WebinoSiteProvision::query()->where('slug', $slug)->first();

        if (! $provision) {
            return response()->json(['message' => 'Provision not found'], 404);
        }

        $status = match ($event) {
            'site.created' => WebinoSiteProvision::STATUS_PROVISIONING,
            'site.ready', 'ssl.active' => WebinoSiteProvision::STATUS_READY,
            'ssl.pending' => WebinoSiteProvision::STATUS_SSL_PENDING,
            'site.failed' => WebinoSiteProvision::STATUS_FAILED,
            default => $provision->status,
        };

        $updates = ['status' => $status];
        if ($status === WebinoSiteProvision::STATUS_READY) {
            $updates['ready_at'] = now();
        }
        if ($event === 'site.failed') {
            $updates['error_log'] = (string) $request->input('error', 'Site provisioning failed');
        }

        $provision->update($updates);

        return response()->json(['data' => $provision->fresh()]);
    }
}
