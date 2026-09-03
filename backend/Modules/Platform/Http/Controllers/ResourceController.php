<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Entities\PlatformEnvVar;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformVolume;
use Modules\Platform\Jobs\DeployResourceJob;
use Modules\Platform\Services\DockerRemoteService;

class ResourceController extends Controller
{
    public function __construct(private readonly DockerRemoteService $docker) {}

    public function index(Request $request): JsonResponse
    {
        $q = PlatformResource::query()->with(['domains'])->latest();
        if ($request->filled('environment_id')) {
            $q->where('environment_id', $request->integer('environment_id'));
        }
        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }
        return $this->ok($q->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'required|exists:platform_environments,id',
            'server_id' => 'required|exists:platform_servers,id',
            'destination_id' => 'nullable|exists:platform_destinations,id',
            'type' => 'required|in:application,database,service,webino_dashboard',
            'name' => 'required|string|max:160',
            'fqdn' => 'nullable|string|max:255',
            'build_pack' => 'nullable|in:nixpacks,dockerfile,compose,image',
            'git_repository' => 'nullable|string|max:512',
            'git_branch' => 'nullable|string|max:120',
            'dockerfile_location' => 'nullable|string|max:255',
            'docker_compose_location' => 'nullable|string|max:255',
            'docker_compose_raw' => 'nullable|string',
            'docker_image' => 'nullable|string|max:255',
            'database_type' => 'nullable|in:postgresql,mysql,mariadb,mongodb,redis,keydb,dragonfly,clickhouse',
            'service_template' => 'nullable|string|max:120',
            'site_type_slug' => 'nullable|string|max:32',
            'ports_exposes' => 'nullable|integer',
            'settings' => 'nullable|array',
            'crm_account_id' => 'nullable|integer',
        ]);
        $settings = $data['settings'] ?? [];
        if (($data['type'] ?? '') === 'database') {
            $settings['db_password'] = $settings['db_password'] ?? Str::random(24);
            $settings['db_user'] = $settings['db_user'] ?? 'webino';
            $settings['db_name'] = $settings['db_name'] ?? 'webino';
        }
        $settings['deploy_webhook_token'] = $settings['deploy_webhook_token'] ?? Str::random(40);
        $data['settings'] = $settings;

        $resource = PlatformResource::query()->create([
            ...$data,
            'status' => 'draft',
        ]);
        if (! empty($data['fqdn'])) {
            PlatformDomain::query()->create([
                'resource_id' => $resource->id,
                'domain' => $data['fqdn'],
            ]);
        }
        return $this->ok($resource->load('domains'), 201);
    }

    public function show(PlatformResource $resource): JsonResponse
    {
        return $this->ok($resource->load(['domains', 'envVars', 'volumes']));
    }

    public function update(Request $request, PlatformResource $resource): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:160',
            'fqdn' => 'nullable|string|max:255',
            'build_pack' => 'nullable|string|max:32',
            'git_repository' => 'nullable|string|max:512',
            'git_branch' => 'nullable|string|max:120',
            'dockerfile_location' => 'nullable|string|max:255',
            'docker_compose_location' => 'nullable|string|max:255',
            'docker_compose_raw' => 'nullable|string',
            'docker_image' => 'nullable|string|max:255',
            'ports_exposes' => 'nullable|integer',
            'settings' => 'nullable|array',
            'status' => 'nullable|string|max:32',
            'site_type_slug' => 'nullable|string|max:32',
        ]);
        $resource->update($data);
        return $this->ok($resource->fresh()->load(['domains', 'envVars', 'volumes']));
    }

    public function destroy(PlatformResource $resource): JsonResponse
    {
        $dir = $resource->settings['site_dir'] ?? null;
        if ($dir && $resource->server_id) {
            $server = $resource->server()->first();
            if ($server) {
                $this->docker->composeDown($server, $dir);
            }
        }
        $resource->delete();
        return $this->ok(['deleted' => true]);
    }

    public function deploy(Request $request, PlatformResource $resource): JsonResponse
    {
        $deployment = PlatformDeployment::query()->create([
            'resource_id' => $resource->id,
            'status' => 'queued',
            'triggered_by' => $request->user()?->id,
            'started_at' => now(),
        ]);
        DeployResourceJob::dispatch($deployment->id);
        return $this->ok($deployment, 202);
    }

    public function deployments(PlatformResource $resource): JsonResponse
    {
        return $this->ok(PlatformDeployment::query()->where('resource_id', $resource->id)->latest()->limit(50)->get());
    }

    public function syncEnv(Request $request, PlatformResource $resource): JsonResponse
    {
        $vars = $request->validate([
            'vars' => 'required|array',
            'vars.*.key' => 'required|string|max:120',
            'vars.*.value' => 'nullable|string',
            'vars.*.is_secret' => 'nullable|boolean',
            'vars.*.is_buildtime' => 'nullable|boolean',
            'vars.*.is_runtime' => 'nullable|boolean',
            'vars.*.is_preview' => 'nullable|boolean',
        ])['vars'];
        foreach ($vars as $v) {
            PlatformEnvVar::query()->updateOrCreate(
                ['resource_id' => $resource->id, 'key' => $v['key']],
                [
                    'value' => $v['value'] ?? null,
                    'is_secret' => (bool) ($v['is_secret'] ?? false),
                    'is_buildtime' => (bool) ($v['is_buildtime'] ?? false),
                    'is_runtime' => (bool) ($v['is_runtime'] ?? true),
                    'is_preview' => (bool) ($v['is_preview'] ?? false),
                ]
            );
        }
        return $this->ok($resource->envVars()->get());
    }

    public function syncVolumes(Request $request, PlatformResource $resource): JsonResponse
    {
        $vols = $request->validate([
            'volumes' => 'required|array',
            'volumes.*.name' => 'required|string|max:120',
            'volumes.*.mount_path' => 'required|string|max:255',
            'volumes.*.host_path' => 'nullable|string|max:255',
            'volumes.*.is_file' => 'nullable|boolean',
        ])['volumes'];
        PlatformVolume::query()->where('resource_id', $resource->id)->delete();
        foreach ($vols as $v) {
            PlatformVolume::query()->create(['resource_id' => $resource->id, ...$v]);
        }
        return $this->ok($resource->volumes()->get());
    }

    public function start(PlatformResource $resource): JsonResponse
    {
        return $this->lifecycle($resource, 'up');
    }

    public function stop(PlatformResource $resource): JsonResponse
    {
        return $this->lifecycle($resource, 'down');
    }

    public function cloneResource(Request $request, PlatformResource $resource): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'environment_id' => 'nullable|exists:platform_environments,id',
            'server_id' => 'nullable|exists:platform_servers,id',
        ]);
        $copy = $resource->replicate(['uuid']);
        $copy->uuid = (string) Str::uuid();
        $copy->name = $data['name'];
        $copy->environment_id = $data['environment_id'] ?? $resource->environment_id;
        $copy->server_id = $data['server_id'] ?? $resource->server_id;
        $copy->status = 'draft';
        $copy->fqdn = null;
        $settings = $resource->settings ?? [];
        unset($settings['site_dir'], $settings['deploy_webhook_token']);
        $copy->settings = $settings;
        $copy->save();

        foreach ($resource->envVars()->get() as $env) {
            PlatformEnvVar::query()->create([
                'resource_id' => $copy->id,
                'key' => $env->key,
                'value' => $env->value,
                'is_secret' => $env->is_secret,
                'is_buildtime' => $env->is_buildtime,
                'is_runtime' => $env->is_runtime,
                'is_preview' => $env->is_preview,
            ]);
        }
        foreach ($resource->volumes()->get() as $vol) {
            PlatformVolume::query()->create([
                'resource_id' => $copy->id,
                'name' => $vol->name,
                'mount_path' => $vol->mount_path,
                'host_path' => $vol->host_path,
                'is_file' => $vol->is_file,
            ]);
        }

        return $this->ok($copy->load(['domains', 'envVars', 'volumes']), 201);
    }

    public function move(Request $request, PlatformResource $resource): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|exists:platform_servers,id',
            'environment_id' => 'nullable|exists:platform_environments,id',
            'destination_id' => 'nullable|exists:platform_destinations,id',
        ]);
        $resource->update(array_filter($data, fn ($v) => $v !== null));

        return $this->ok($resource->fresh()->load(['domains', 'envVars', 'volumes']));
    }

    public function ensureWebhook(PlatformResource $resource): JsonResponse
    {
        $settings = $resource->settings ?? [];
        if (empty($settings['deploy_webhook_token'])) {
            $settings['deploy_webhook_token'] = Str::random(40);
            $resource->update(['settings' => $settings]);
        }
        $token = $settings['deploy_webhook_token'];
        $url = url('/api/v1/platform/webhooks/deploy/'.$token);

        return $this->ok([
            'token' => $token,
            'url' => $url,
        ]);
    }

    protected function lifecycle(PlatformResource $resource, string $action): JsonResponse
    {
        $server = $resource->server()->first();
        $dir = $resource->settings['site_dir'] ?? null;
        if (! $server || ! $dir) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'platform.resource_not_deployed', 'meta' => null, 'errors' => null], 422);
        }
        $r = $action === 'up' ? $this->docker->composeUp($server, $dir) : $this->docker->composeDown($server, $dir);
        $resource->update(['status' => $action === 'up' ? 'running' : 'stopped']);
        return $this->ok(['resource' => $resource->fresh(), 'result' => $r]);
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => null, 'meta' => null, 'errors' => null], $status);
    }
}
