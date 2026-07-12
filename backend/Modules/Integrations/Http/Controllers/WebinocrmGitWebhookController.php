<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\Core\Entities\ModuleGitSource;
use Modules\Integrations\Jobs\PropagateModuleGitRepositoryJob;

class WebinocrmGitWebhookController extends Controller
{
    public function handle(Request $request): Response|JsonResponse
    {
        $settings = CoreHostingSetting::current();
        $secret = $settings->git_webhook_secret;
        if (! is_string($secret) || $secret === '') {
            return response('Webhook secret not configured', 503);
        }

        $plainHeader = (string) $request->header('X-Webhook-Secret', '');
        if ($plainHeader !== '' && hash_equals($secret, $plainHeader)) {
            return $this->handlePlainSecretModuleSync($request);
        }

        $raw = $request->getContent();
        $sigHeader = (string) $request->header('X-Webino-Signature', '');
        if (str_starts_with($sigHeader, 'sha256=')) {
            $sigHeader = substr($sigHeader, 7);
        }
        $expected = hash_hmac('sha256', $raw, $secret);
        $valid = $sigHeader !== '' && hash_equals($expected, $sigHeader);

        if (! $valid) {
            return response('Invalid signature', 401);
        }

        PropagateModuleGitRepositoryJob::dispatch('hmac_payload');

        CoreInfraAuditLog::query()->create([
            'user_id' => null,
            'channel' => 'git_webhook',
            'action' => 'push_or_tag',
            'subject_type' => 'http',
            'subject_id' => null,
            'payload' => ['content_length' => strlen($raw)],
        ]);

        return response('ok', 200);
    }

    /**
     * JSON body: { "module_slug": "accounting", "repo_url": "https://..." } — updates module_git_sources (INTEGRATION.md).
     */
    protected function handlePlainSecretModuleSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'repo_url' => ['required', 'string', 'max:2048'],
        ]);

        ModuleGitSource::query()->updateOrCreate(
            ['slug' => $data['module_slug']],
            [
                'clone_url' => $data['repo_url'],
                'auth_type' => 'none',
                'credential_ref' => null,
            ]
        );

        PropagateModuleGitRepositoryJob::dispatch($data['module_slug']);

        CoreInfraAuditLog::query()->create([
            'user_id' => null,
            'channel' => 'git_webhook',
            'action' => 'module_source_sync',
            'subject_type' => ModuleGitSource::class,
            'subject_id' => null,
            'payload' => [
                'module_slug' => $data['module_slug'],
                'repo_url' => $data['repo_url'],
            ],
        ]);

        return response()->json(['ok' => true, 'slug' => $data['module_slug']]);
    }
}
