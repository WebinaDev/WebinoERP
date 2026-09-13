<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\OrgGit\OrgGitProviderFactory;
use Modules\Marketplace\Entities\MarketplaceGiteaSetting;
use Modules\Marketplace\Http\Requests\UpdateGiteaSettingsRequest;
use Modules\Marketplace\Services\MarketplaceGiteaClient;

class GiteaController extends Controller
{
    public function settings(): JsonResponse
    {
        $row = MarketplaceGiteaSetting::query()->first();

        return response()->json([
            'data' => $row ? $this->formatSettings($row) : [
                'gitea_base_url' => '',
                'gitea_org' => '',
                'gitea_ip_override' => '',
                'gitea_ip_scheme' => 'auto',
                'gitea_configured' => false,
                'has_token' => false,
                'provider' => 'gitea',
                'host' => null,
                'base_url' => null,
                'org' => null,
                'platform_source_id' => null,
            ],
            'settings' => $row ? $this->formatSettings($row) : null,
        ]);
    }

    public function updateSettings(UpdateGiteaSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Accept CRM-style aliases.
        if ($request->filled('gitea_base_url') && empty($data['base_url'])) {
            $data['base_url'] = (string) $request->input('gitea_base_url');
        }
        if ($request->filled('gitea_org') && empty($data['org'])) {
            $data['org'] = (string) $request->input('gitea_org');
        }
        if ($request->filled('gitea_api_token') && empty($data['token'])) {
            $data['token'] = (string) $request->input('gitea_api_token');
        }
        if ($request->filled('gitea_ip_override') && empty($data['ip_override'])) {
            $data['ip_override'] = (string) $request->input('gitea_ip_override');
        }
        if ($request->filled('gitea_ip_scheme') && empty($data['ip_scheme'])) {
            $data['ip_scheme'] = (string) $request->input('gitea_ip_scheme');
        }

        if (! empty($data['base_url']) && empty($data['host'])) {
            $data['host'] = parse_url($data['base_url'], PHP_URL_HOST) ?: $data['base_url'];
        }
        if (array_key_exists('token', $data) && ($data['token'] === null || $data['token'] === '')) {
            unset($data['token']);
        }

        $settings = MarketplaceGiteaSetting::query()->first();
        if ($settings) {
            $settings->update($data);
        } else {
            $settings = MarketplaceGiteaSetting::create($data);
        }

        $fresh = $settings->fresh();

        return response()->json([
            'data' => $this->formatSettings($fresh),
            'settings' => $this->formatSettings($fresh),
            'message' => 'Settings saved',
        ]);
    }

    public function testConnection(Request $request, MarketplaceGiteaClient $client, OrgGitProviderFactory $factory): JsonResponse
    {
        $overrides = array_filter([
            'gitea_base_url' => $request->input('gitea_base_url', $request->input('base_url')),
            'gitea_org' => $request->input('gitea_org', $request->input('org')),
            'gitea_ip_override' => $request->input('gitea_ip_override', $request->input('ip_override')),
            'gitea_ip_scheme' => $request->input('gitea_ip_scheme', $request->input('ip_scheme')),
            'gitea_api_token' => $request->input('gitea_api_token', $request->input('token')),
        ], fn ($v) => $v !== null && $v !== '');

        $diagnostics = $client->runConnectionDiagnostics($overrides);

        // Keep provider smoke check available for non-gitea when diagnostics incomplete.
        if (! ($diagnostics['ok'] ?? false) && empty($diagnostics['steps'])) {
            try {
                $settings = MarketplaceGiteaSetting::query()->first();
                if (! $settings && ! $request->filled('provider')) {
                    return response()->json(['message' => 'Org git not configured', 'data' => $diagnostics], 422);
                }
                $provider = $factory->make($settings?->provider);
                $result = $provider->testConnection();
                if ($result['ok'] ?? false) {
                    $diagnostics['ok'] = true;
                    $diagnostics['user'] = $result['user'] ?? $diagnostics['user'] ?? null;
                    $diagnostics['message'] = $diagnostics['message'] ?: 'Connected.';
                }
            } catch (\Throwable $e) {
                $diagnostics['message'] = $diagnostics['message'] ?: $e->getMessage();
            }
        }

        $status = ($diagnostics['ok'] ?? false) ? 200 : 422;

        return response()->json([
            'data' => $diagnostics,
            'ok' => $diagnostics['ok'] ?? false,
            'message' => $diagnostics['message'] ?? null,
            'user' => $diagnostics['user'] ?? null,
            'diag' => $diagnostics['diag'] ?? null,
            'steps' => $diagnostics['steps'] ?? [],
            'hints' => $diagnostics['hints'] ?? [],
        ], $status === 200 ? 200 : 422);
    }

    public function listRepos(OrgGitProviderFactory $factory): JsonResponse
    {
        try {
            $provider = $factory->make();
            $org = request('org');

            return response()->json([
                'data' => $provider->listOrgRepos(is_string($org) ? $org : null),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** @return array<string, mixed> */
    protected function formatSettings(MarketplaceGiteaSetting $row): array
    {
        $baseUrl = $row->base_url ?: $row->host;

        return [
            'id' => $row->id,
            'provider' => $row->provider ?? 'gitea',
            'host' => $row->host,
            'base_url' => $baseUrl,
            'org' => $row->org,
            'platform_source_id' => $row->platform_source_id,
            'gitea_base_url' => $baseUrl,
            'gitea_org' => $row->org,
            'gitea_ip_override' => $row->ip_override ?? '',
            'gitea_ip_scheme' => $row->ip_scheme ?? 'auto',
            'gitea_configured' => filled($row->token) && filled($baseUrl) && filled($row->org),
            'has_token' => filled($row->token),
            'ip_override' => $row->ip_override,
            'ip_scheme' => $row->ip_scheme ?? 'auto',
        ];
    }
}
