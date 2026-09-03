<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformDestination;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\SshExecutor;
use Throwable;

class ServerController extends Controller
{
    public function __construct(
        private readonly SshExecutor $ssh,
        private readonly DockerRemoteService $docker,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(PlatformServer::query()->with('sshKey:id,name')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'ip' => 'required|string|max:64',
            'port' => 'nullable|integer|min:1|max:65535',
            'user' => 'nullable|string|max:64',
            'ssh_key_id' => 'nullable|exists:platform_ssh_keys,id',
            'is_localhost' => 'nullable|boolean',
        ]);
        $server = PlatformServer::query()->create([
            ...$data,
            'port' => $data['port'] ?? 22,
            'user' => $data['user'] ?? 'root',
            'status' => 'pending',
            'is_localhost' => (bool) ($data['is_localhost'] ?? false),
        ]);
        return $this->ok($server, status: 201);
    }

    public function show(PlatformServer $server): JsonResponse
    {
        return $this->ok($server->load('sshKey:id,name,fingerprint'));
    }

    public function update(Request $request, PlatformServer $server): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'ip' => 'sometimes|string|max:64',
            'port' => 'sometimes|integer|min:1|max:65535',
            'user' => 'sometimes|string|max:64',
            'ssh_key_id' => 'nullable|exists:platform_ssh_keys,id',
            'proxy_type' => 'sometimes|string|max:32',
            'meta' => 'nullable|array',
        ]);
        $server->update($data);
        return $this->ok($server->fresh());
    }

    public function destroy(PlatformServer $server): JsonResponse
    {
        $server->delete();
        return $this->ok(['deleted' => true]);
    }

    public function validateServer(PlatformServer $server): JsonResponse
    {
        try {
            $result = $this->ssh->validate($server);
            return $this->ok(['server' => $server->fresh(), 'result' => $result]);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function bootstrap(PlatformServer $server): JsonResponse
    {
        try {
            $result = $this->ssh->bootstrap($server);
            PlatformDestination::query()->firstOrCreate(
                ['server_id' => $server->id, 'network_name' => 'webino'],
                ['name' => 'webino', 'driver' => 'bridge']
            );
            return $this->ok(['server' => $server->fresh(), 'result' => $result]);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function resources(PlatformServer $server): JsonResponse
    {
        return $this->ok($this->docker->ps($server));
    }

    public function images(PlatformServer $server): JsonResponse
    {
        return $this->ok($this->docker->images($server));
    }

    public function pullImage(Request $request, PlatformServer $server): JsonResponse
    {
        $ref = $request->validate(['ref' => 'required|string|max:255'])['ref'];

        return $this->ok($this->docker->pullImage($server, $ref));
    }

    public function deleteImage(Request $request, PlatformServer $server): JsonResponse
    {
        $ref = $request->validate(['ref' => 'required|string|max:255'])['ref'];

        return $this->ok($this->docker->deleteImage($server, $ref));
    }

    public function containerAction(Request $request, PlatformServer $server, string $container): JsonResponse
    {
        $action = $request->validate(['action' => 'required|in:start,stop,restart'])['action'];
        return $this->ok($this->docker->containerAction($server, $container, $action));
    }

    public function containerLogs(PlatformServer $server, string $container): JsonResponse
    {
        return $this->ok(['logs' => $this->docker->logs($server, $container)]);
    }

    public function networks(PlatformServer $server): JsonResponse
    {
        return $this->ok($this->docker->networks($server));
    }

    public function createNetwork(Request $request, PlatformServer $server): JsonResponse
    {
        $name = $request->validate(['name' => 'required|string|max:120'])['name'];
        $r = $this->docker->createNetwork($server, $name);
        PlatformDestination::query()->firstOrCreate(
            ['server_id' => $server->id, 'network_name' => $name],
            ['name' => $name, 'driver' => 'bridge']
        );
        return $this->ok($r);
    }

    public function metrics(PlatformServer $server): JsonResponse
    {
        return $this->ok($this->docker->metrics($server));
    }

    public function cleanup(PlatformServer $server): JsonResponse
    {
        return $this->ok($this->docker->cleanup($server));
    }

    public function proxy(PlatformServer $server): JsonResponse
    {
        $r = $this->ssh->run($server, 'ls /etc/caddy/webino.d 2>/dev/null; echo ---; cat /etc/caddy/Caddyfile 2>/dev/null | head -c 8000');
        return $this->ok(['raw' => $r['stdout'], 'exit_code' => $r['exit_code']]);
    }

    public function proxyReload(PlatformServer $server): JsonResponse
    {
        $r = $this->ssh->run($server, 'caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || systemctl reload caddy 2>/dev/null || true');
        return $this->ok($r);
    }

    public function destinations(PlatformServer $server): JsonResponse
    {
        return $this->ok(
            PlatformDestination::query()->where('server_id', $server->id)->orderBy('name')->get()
        );
    }

    public function destroyDestination(PlatformServer $server, PlatformDestination $destination): JsonResponse
    {
        if ((int) $destination->server_id !== (int) $server->id) {
            return $this->fail('platform.destination_mismatch', 404);
        }
        $this->docker->sshRun($server, 'docker network rm '.escapeshellarg($destination->network_name).' 2>/dev/null || true');
        $destination->delete();
        return $this->ok(['deleted' => true]);
    }

    public function terminalExec(Request $request, PlatformServer $server): JsonResponse
    {
        $cmd = $request->validate(['command' => 'required|string|max:2000'])['command'];
        return $this->ok($this->ssh->run($server, $cmd, 60));
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => null,
            'meta' => null,
            'errors' => null,
        ], $status);
    }

    protected function fail(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'meta' => null,
            'errors' => null,
        ], $status);
    }
}
