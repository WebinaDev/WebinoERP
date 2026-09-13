<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Marketplace\Entities\MarketplaceGiteaSetting;

/**
 * Lightweight Gitea HTTP helpers for Marketplace CRM parity (diagnostics, repo CRUD, archives).
 */
class MarketplaceGiteaClient
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array{ok: bool, message?: string, user?: string, diag?: array, steps?: array, hints?: array}
     */
    public function runConnectionDiagnostics(array $overrides = []): array
    {
        $config = $this->resolveConfig($overrides);
        $hints = [];
        $steps = [];
        $apiBase = rtrim((string) $config['base_url'], '/').'/api/v1';
        $sample = $this->rewriteForIp("$apiBase/version", $config);

        $diag = [
            'base_url' => (string) $config['base_url'],
            'api_base' => $apiBase,
            'org' => (string) $config['org'],
            'ip_override' => (string) $config['ip_override'],
            'ip_scheme' => (string) $config['ip_scheme'],
            'host_header' => (string) ($sample['host_header'] ?? ''),
            'token_configured' => $config['api_token'] !== '',
            'resolved_url_sample' => (string) ($sample['url'] ?? ''),
            'resolved_scheme' => (string) ($sample['scheme'] ?? ''),
            'sslverify' => (bool) ($sample['sslverify'] ?? true),
            'owner' => (string) $config['org'],
            'owner_kind' => '',
        ];

        if ($config['base_url'] === '' || $config['org'] === '') {
            $hints[] = 'config_incomplete';

            return [
                'ok' => false,
                'message' => 'Gitea base URL and owner namespace are required.',
                'user' => '',
                'diag' => $diag,
                'steps' => $steps,
                'hints' => $hints,
            ];
        }

        $steps['version'] = $this->probe($config, 'version', 'API reachability', 'GET', '/version', false);
        if ($this->isSslHint($steps['version'])) {
            $hints[] = 'ssl_wrong_version';
        }

        if ($config['api_token'] === '') {
            $hints[] = 'token_missing';

            return [
                'ok' => false,
                'message' => 'API token is required.',
                'user' => '',
                'diag' => $diag,
                'steps' => $steps,
                'hints' => array_values(array_unique($hints)),
            ];
        }

        $steps['auth'] = $this->probe($config, 'auth', 'Authentication', 'GET', '/user', true);
        if ($this->isSslHint($steps['auth'])) {
            $hints[] = 'ssl_wrong_version';
        }
        if (empty($steps['auth']['ok']) && (int) ($steps['auth']['http'] ?? 0) === 401) {
            $hints[] = 'unauthorized';
        }

        $user = '';
        $owner = (string) $config['org'];
        $ownerKind = '';

        if (! empty($steps['auth']['ok'])) {
            $authData = $this->request($config, 'GET', '/user');
            $user = is_array($authData['data'] ?? null) ? (string) ($authData['data']['login'] ?? '') : '';

            $orgPath = '/orgs/'.rawurlencode($owner);
            $userPath = '/users/'.rawurlencode($owner);
            $orgProbe = $this->probe($config, 'owner', 'Owner access', 'GET', $orgPath, true);

            if (! empty($orgProbe['ok'])) {
                $ownerKind = 'org';
                $steps['owner'] = $orgProbe;
                $reposPath = $orgPath.'/repos';
            } else {
                $userProbe = $this->probe($config, 'owner', 'Owner access', 'GET', $userPath, true);
                $steps['owner'] = $userProbe;
                if (! empty($userProbe['ok'])) {
                    $ownerKind = 'user';
                    $reposPath = $userPath.'/repos';
                    if ((int) ($orgProbe['http'] ?? 0) === 404) {
                        $hints[] = 'owner_is_user';
                    }
                } elseif ((int) ($orgProbe['http'] ?? 0) === 404 && (int) ($userProbe['http'] ?? 0) === 404) {
                    $hints[] = 'owner_not_found';
                    $reposPath = $orgPath.'/repos';
                } else {
                    $reposPath = $userPath.'/repos';
                }
            }

            if ($ownerKind !== '') {
                $diag['owner_kind'] = $ownerKind;
                $steps['owner_repos'] = $this->probe(
                    $config,
                    'owner_repos',
                    'Owner repositories',
                    'GET',
                    $reposPath,
                    true,
                    ['limit' => 1]
                );
            }
        }

        $hints = array_values(array_unique($hints));
        $ok = ! empty($steps['auth']['ok']) && ! empty($steps['owner']['ok']);

        return [
            'ok' => $ok,
            'message' => $ok
                ? ($user !== '' ? "Connected to Gitea as {$user}." : 'Connected to Gitea.')
                : (string) ($steps['auth']['message'] ?? $steps['version']['message'] ?? 'Connection failed.'),
            'user' => $user,
            'diag' => $diag,
            'steps' => $steps,
            'hints' => $hints,
        ];
    }

    /**
     * @return array{ok: bool, data?: array<string, mixed>, message?: string, code?: int}
     */
    public function createRepo(string $name, string $description = '', bool $private = true): array
    {
        $name = Str::slug($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'Invalid repository name.', 'code' => 400];
        }

        $config = $this->resolveConfig();
        $owner = (string) $config['org'];
        if ($owner === '' || $config['api_token'] === '') {
            return ['ok' => false, 'message' => 'Gitea org and token required.', 'code' => 422];
        }

        $body = [
            'name' => $name,
            'description' => $description,
            'private' => $private,
            'auto_init' => true,
        ];

        // Prefer org endpoint; fall back to user.
        $res = $this->request($config, 'POST', '/orgs/'.rawurlencode($owner).'/repos', $body);
        if (! ($res['ok'] ?? false)) {
            $res = $this->request($config, 'POST', '/user/repos', array_merge($body, ['org' => $owner]));
        }
        if (! ($res['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Failed to create repo'),
                'code' => (int) ($res['http'] ?? 502),
            ];
        }

        return ['ok' => true, 'data' => is_array($res['data'] ?? null) ? $res['data'] : []];
    }

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function deleteRepo(string $owner, string $repo): array
    {
        $config = $this->resolveConfig();
        $res = $this->request($config, 'DELETE', '/repos/'.rawurlencode($owner).'/'.rawurlencode($repo));
        if (! ($res['ok'] ?? false) && (int) ($res['http'] ?? 0) !== 404) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Failed to delete repo'),
                'code' => (int) ($res['http'] ?? 502),
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function patchRepoVisibility(string $owner, string $repo, bool $private): array
    {
        $config = $this->resolveConfig();
        $res = $this->request($config, 'PATCH', '/repos/'.rawurlencode($owner).'/'.rawurlencode($repo), [
            'private' => $private,
        ]);
        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Failed to update visibility')];
        }

        return ['ok' => true, 'data' => is_array($res['data'] ?? null) ? $res['data'] : []];
    }

    /**
     * @return array{ok: bool, body?: string, message?: string}
     */
    public function downloadArchive(string $owner, string $repo, string $tag): array
    {
        $config = $this->resolveConfig();
        $candidates = $this->archiveTagCandidates($tag);
        $last = null;

        foreach ($candidates as $candidate) {
            $path = '/repos/'.rawurlencode($owner).'/'.rawurlencode($repo).'/archive/'.rawurlencode($candidate).'.zip';
            $res = $this->requestRaw($config, 'GET', $path, true);
            $last = $res;
            if (! empty($res['ok']) && ! empty($res['body'])) {
                return $res;
            }
        }

        return is_array($last)
            ? $last
            : ['ok' => false, 'message' => 'Could not download source archive from Gitea.'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string}
     */
    public function resolveConfig(array $overrides = []): array
    {
        $row = MarketplaceGiteaSetting::query()->first();
        $base = array_key_exists('gitea_base_url', $overrides)
            ? (string) $overrides['gitea_base_url']
            : (string) ($row?->base_url ?: $row?->host ?: '');
        if ($base !== '' && ! str_starts_with($base, 'http')) {
            $base = 'https://'.$base;
        }
        $org = array_key_exists('gitea_org', $overrides)
            ? (string) $overrides['gitea_org']
            : (string) ($row?->org ?? '');
        $token = array_key_exists('gitea_api_token', $overrides)
            ? (string) $overrides['gitea_api_token']
            : (string) ($row?->token ?? '');
        $ip = array_key_exists('gitea_ip_override', $overrides)
            ? (string) $overrides['gitea_ip_override']
            : (string) ($row?->ip_override ?? '');
        $scheme = array_key_exists('gitea_ip_scheme', $overrides)
            ? (string) $overrides['gitea_ip_scheme']
            : (string) ($row?->ip_scheme ?? 'auto');

        return [
            'base_url' => rtrim($base, '/'),
            'org' => $org,
            'api_token' => $token,
            'ip_override' => trim($ip),
            'ip_scheme' => in_array($scheme, ['auto', 'http', 'https'], true) ? $scheme : 'auto',
        ];
    }

    /**
     * @param  array{base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string}  $config
     * @param  array<string, mixed>  $query
     * @return array{key: string, label: string, url: string, http: int, ok: bool, hint: string, message: string, ms: int, curl_error: string}
     */
    protected function probe(
        array $config,
        string $key,
        string $label,
        string $method,
        string $path,
        bool $auth,
        array $query = []
    ): array {
        $started = microtime(true);
        $res = $this->request($config, $method, $path, null, $auth, $query);
        $ms = (int) round((microtime(true) - $started) * 1000);
        $http = (int) ($res['http'] ?? 0);
        $ok = ! empty($res['ok']);

        return [
            'key' => $key,
            'label' => $label,
            'url' => (string) ($res['url'] ?? ''),
            'http' => $http,
            'ok' => $ok,
            'hint' => $ok ? 'ok' : (string) ($res['message'] ?? 'failed'),
            'message' => (string) ($res['message'] ?? ''),
            'ms' => $ms,
            'curl_error' => (string) ($res['error'] ?? ''),
        ];
    }

    /**
     * @param  array{base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string}  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     * @return array{ok: bool, http?: int, data?: mixed, message?: string, url?: string, error?: string}
     */
    protected function request(
        array $config,
        string $method,
        string $path,
        ?array $body = null,
        bool $auth = true,
        array $query = []
    ): array {
        $apiBase = rtrim($config['base_url'], '/').'/api/v1';
        $url = $apiBase.$path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }
        $rewrite = $this->rewriteForIp($url, $config);
        $headers = ['Accept' => 'application/json'];
        if ($auth && $config['api_token'] !== '') {
            $headers['Authorization'] = 'token '.$config['api_token'];
        }
        if (($rewrite['host_header'] ?? '') !== '') {
            $headers['Host'] = $rewrite['host_header'];
        }

        try {
            $pending = Http::timeout(20)
                ->withHeaders($headers)
                ->withOptions(['verify' => (bool) ($rewrite['sslverify'] ?? true)]);
            $response = match (strtoupper($method)) {
                'POST' => $pending->asJson()->post($rewrite['url'], $body ?? []),
                'PATCH' => $pending->asJson()->patch($rewrite['url'], $body ?? []),
                'DELETE' => $pending->delete($rewrite['url']),
                default => $pending->get($rewrite['url']),
            };
            $http = $response->status();
            $ok = $http >= 200 && $http < 300;
            $json = $response->json();

            return [
                'ok' => $ok,
                'http' => $http,
                'data' => $json,
                'message' => $ok ? '' : ('HTTP '.$http),
                'url' => $rewrite['url'],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'http' => 0,
                'message' => $e->getMessage(),
                'url' => $rewrite['url'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array{base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string}  $config
     * @return array{ok: bool, body?: string, message?: string, http?: int}
     */
    protected function requestRaw(array $config, string $method, string $path, bool $auth = true): array
    {
        $apiBase = rtrim($config['base_url'], '/').'/api/v1';
        $url = $apiBase.$path;
        $rewrite = $this->rewriteForIp($url, $config);
        $headers = ['Accept' => 'application/octet-stream'];
        if ($auth && $config['api_token'] !== '') {
            $headers['Authorization'] = 'token '.$config['api_token'];
        }
        if (($rewrite['host_header'] ?? '') !== '') {
            $headers['Host'] = $rewrite['host_header'];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders($headers)
                ->withOptions(['verify' => (bool) ($rewrite['sslverify'] ?? true)])
                ->get($rewrite['url']);
            $http = $response->status();
            if ($http < 200 || $http >= 300) {
                return ['ok' => false, 'http' => $http, 'message' => 'HTTP '.$http];
            }

            return ['ok' => true, 'body' => $response->body(), 'http' => $http];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array{base_url: string, org: string, api_token: string, ip_override: string, ip_scheme: string}  $config
     * @return array{url: string, host_header: string, scheme: string, sslverify: bool}
     */
    protected function rewriteForIp(string $url, array $config): array
    {
        $ip = trim((string) ($config['ip_override'] ?? ''));
        $parts = parse_url($url);
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        if ($ip === '') {
            return [
                'url' => $url,
                'host_header' => '',
                'scheme' => $scheme,
                'sslverify' => true,
            ];
        }

        $force = (string) ($config['ip_scheme'] ?? 'auto');
        if ($force === 'http' || $force === 'https') {
            $scheme = $force;
        }

        return [
            'url' => $scheme.'://'.$ip.$path.$query,
            'host_header' => $host,
            'scheme' => $scheme,
            'sslverify' => false,
        ];
    }

    /** @return list<string> */
    protected function archiveTagCandidates(string $tag): array
    {
        $tag = trim($tag);
        $out = [];
        if ($tag !== '') {
            $out[] = $tag;
            if (str_starts_with($tag, 'v')) {
                $out[] = substr($tag, 1);
            } else {
                $out[] = 'v'.$tag;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** @param  array{curl_error?: string, message?: string}  $step */
    protected function isSslHint(array $step): bool
    {
        $msg = strtolower((string) ($step['curl_error'] ?? $step['message'] ?? ''));

        return str_contains($msg, 'ssl') || str_contains($msg, 'certificate');
    }
}
