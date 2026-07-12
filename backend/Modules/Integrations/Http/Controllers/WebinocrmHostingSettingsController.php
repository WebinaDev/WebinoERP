<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreHostingSetting;

class WebinocrmHostingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $s = CoreHostingSetting::current();

        return response()->json([
            'data' => [
                'public_crm_url' => $s->public_crm_url,
                'git_provider' => $s->git_provider,
                'git_base_url' => $s->git_base_url,
                'git_pat_configured' => filled($s->git_pat),
                'portainer_url' => $s->portainer_url,
                'portainer_api_token_configured' => filled($s->portainer_api_token),
                'portainer_tls_fingerprint' => $s->portainer_tls_fingerprint,
                'portainer_endpoint_id' => $s->portainer_endpoint_id,
                'git_webhook_secret_configured' => filled($s->git_webhook_secret),
                'license_hmac_configured' => ((string) config('app.webinocrm_license_hmac_secret')) !== '',
                'webinoserver_panel_url' => $s->webinoserver_panel_url,
                'webinoserver_api_token_configured' => filled($s->webinoserver_api_token),
                'platform_base_domain' => $s->platform_base_domain,
                'default_product_channel' => $s->default_product_channel ?? 'LTS',
                'provision_webhook_secret_configured' => filled($s->provision_webhook_secret),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_crm_url' => 'nullable|string|max:512',
            'git_provider' => 'nullable|string|max:32',
            'git_base_url' => 'nullable|string|max:512',
            'git_pat' => 'nullable|string|max:4000',
            'portainer_url' => 'nullable|string|max:512',
            'portainer_api_token' => 'nullable|string|max:4000',
            'portainer_tls_fingerprint' => 'nullable|string|max:128',
            'portainer_endpoint_id' => 'nullable|integer|min:0',
            'git_webhook_secret' => 'nullable|string|max:4000',
            'webinoserver_panel_url' => 'nullable|string|max:512',
            'webinoserver_api_token' => 'nullable|string|max:4000',
            'platform_base_domain' => 'nullable|string|max:255',
            'default_product_channel' => 'nullable|string|max:16|in:Dev,LTS,Beta',
            'provision_webhook_secret' => 'nullable|string|max:4000',
        ]);

        $s = CoreHostingSetting::current();

        foreach ([
            'public_crm_url', 'git_provider', 'git_base_url',
            'portainer_url', 'portainer_tls_fingerprint',
            'webinoserver_panel_url', 'platform_base_domain', 'default_product_channel',
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $s->{$k} = $data[$k] !== '' ? $data[$k] : null;
            }
        }

        if (array_key_exists('portainer_endpoint_id', $data)) {
            $s->portainer_endpoint_id = $data['portainer_endpoint_id'];
        }

        foreach (['git_pat', 'portainer_api_token', 'git_webhook_secret', 'webinoserver_api_token', 'provision_webhook_secret'] as $secretKey) {
            if (! array_key_exists($secretKey, $data)) {
                continue;
            }
            $v = $data[$secretKey];
            if ($v === null || $v === '') {
                $s->{$secretKey} = null;
            } else {
                $s->{$secretKey} = $v;
            }
        }

        $s->save();

        return $this->show();
    }
}
