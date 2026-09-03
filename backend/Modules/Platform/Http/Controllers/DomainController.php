<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\SshExecutor;
use Throwable;

class DomainController extends Controller
{
    public function __construct(
        private readonly DockerRemoteService $docker,
        private readonly SshExecutor $ssh,
    ) {}

    public function store(Request $request, PlatformResource $resource): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'force_https' => 'nullable|boolean',
            'hsts' => 'nullable|boolean',
            'redirect_to' => 'nullable|string|max:255',
        ]);

        $forceHttps = (bool) ($data['force_https'] ?? true);
        $hsts = (bool) ($data['hsts'] ?? false);
        $redirect = $data['redirect_to'] ?? null;

        $row = PlatformDomain::query()->updateOrCreate(
            ['resource_id' => $resource->id, 'domain' => $data['domain']],
            [
                'force_https' => $forceHttps,
                'hsts' => $hsts,
                'redirect_to' => $redirect,
                'ssl_status' => 'pending',
            ]
        );

        try {
            $this->applyCaddy($resource, $row);
            $row->ssl_status = 'provisioning';
            $row->save();
            if ($this->probeHttps($row->domain)) {
                $row->ssl_status = 'active';
                $row->save();
            }
        } catch (Throwable $e) {
            $row->ssl_status = 'error';
            $row->save();

            return response()->json([
                'success' => false,
                'data' => $row->fresh(),
                'message' => $e->getMessage(),
                'meta' => null,
                'errors' => null,
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $row->fresh(), 'message' => null, 'meta' => null, 'errors' => null], 201);
    }

    public function destroy(PlatformDomain $domain): JsonResponse
    {
        $resource = PlatformResource::query()->find($domain->resource_id);
        if ($resource?->server_id) {
            $server = $resource->server()->first();
            if ($server) {
                $slug = Str::slug($domain->domain);
                $this->docker->sshRun($server, 'rm -f /etc/caddy/webino.d/'.$slug.'.caddy; caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || systemctl reload caddy 2>/dev/null || true');
            }
        }
        $domain->delete();

        return response()->json(['success' => true, 'data' => ['deleted' => true], 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function refreshSsl(PlatformDomain $domain): JsonResponse
    {
        $resource = PlatformResource::query()->findOrFail($domain->resource_id);
        try {
            $this->applyCaddy($resource, $domain);
            $domain->ssl_status = $this->probeHttps($domain->domain) ? 'active' : 'provisioning';
            $domain->save();
        } catch (Throwable $e) {
            $domain->ssl_status = 'error';
            $domain->save();

            return response()->json(['success' => false, 'data' => $domain, 'message' => $e->getMessage(), 'meta' => null, 'errors' => null], 422);
        }

        return response()->json(['success' => true, 'data' => $domain->fresh(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    protected function applyCaddy(PlatformResource $resource, PlatformDomain $domain): void
    {
        $server = $resource->server()->first();
        if (! $server) {
            throw new \RuntimeException('platform.domain_no_server');
        }

        $port = (int) ($resource->ports_exposes ?: 80);
        $upstream = $resource->settings['caddy_upstream']
            ?? (($resource->settings['site_dir'] ?? null)
                ? Str::slug($resource->name).'-frontend-1:3000'
                : '127.0.0.1:'.$port);

        $hstsLine = $domain->hsts ? "\n  header Strict-Transport-Security \"max-age=31536000; includeSubDomains\"" : '';
        $redirectBlock = '';
        if ($domain->redirect_to) {
            $redirectBlock = "\n  redir https://{$domain->redirect_to}{uri} permanent";
        }

        $force = $domain->force_https ? '' : '# http allowed';
        $snippet = <<<CADDY
{$domain->domain} {
  encode gzip{$hstsLine}{$redirectBlock}
  {$force}
  reverse_proxy {$upstream}
}
CADDY;

        $slug = Str::slug($domain->domain);
        $this->docker->writeFile($server, '/etc/caddy/webino.d/'.$slug.'.caddy', $snippet);
        $this->ssh->run($server, 'caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || systemctl reload caddy 2>/dev/null || true');
    }

    protected function probeHttps(string $domain): bool
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 5], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $headers = @get_headers('https://'.$domain.'/', true, $ctx);

            return is_array($headers) && isset($headers[0]) && str_contains((string) $headers[0], '20');
        } catch (Throwable) {
            return false;
        }
    }
}
