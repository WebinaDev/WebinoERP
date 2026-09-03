<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServiceTemplate;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\PlatformNotifier;
use Throwable;

class DeployResourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deploymentId) {}

    public function handle(DockerRemoteService $docker, PlatformNotifier $notifier): void
    {
        $deployment = PlatformDeployment::query()->find($this->deploymentId);
        if (! $deployment) {
            return;
        }
        $resource = PlatformResource::query()->find($deployment->resource_id);
        if (! $resource) {
            return;
        }
        $deployment->update(['status' => 'running']);
        $logs = '';
        try {
            $server = $resource->server()->first();
            if (! $server) {
                throw new \RuntimeException('platform.no_server');
            }
            $dir = $resource->settings['site_dir'] ?? ('/var/lib/webino/sites/'.$resource->name);
            $docker->sshRun($server, 'mkdir -p '.escapeshellarg($dir));

            if ($resource->type === 'database') {
                $compose = $this->databaseCompose($resource);
                $docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
                $resource->docker_compose_raw = $compose;
            } elseif ($resource->type === 'service' && $resource->service_template) {
                $tpl = PlatformServiceTemplate::query()->where('slug', $resource->service_template)->first();
                $compose = $resource->docker_compose_raw ?: ($tpl?->compose ?? '');
                if ($compose === '') {
                    throw new \RuntimeException('platform.service_template_empty');
                }
                $docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
                $resource->docker_compose_raw = $compose;
            } elseif (in_array($resource->build_pack, ['dockerfile', 'compose', 'nixpacks', null], true) && $resource->git_repository) {
                $branch = $resource->git_branch ?: 'main';
                $clone = $docker->sshRun(
                    $server,
                    'if [ -d '.escapeshellarg($dir.'/.git').' ]; then cd '.escapeshellarg($dir).' && git fetch --all && git checkout '.escapeshellarg($branch).' && git pull; else rm -rf '.escapeshellarg($dir).' && git clone --branch '.escapeshellarg($branch).' --depth 1 '.escapeshellarg($resource->git_repository).' '.escapeshellarg($dir).'; fi',
                    600
                );
                $logs .= $clone['stdout']."\n".$clone['stderr']."\n";
                if ($resource->build_pack === 'dockerfile' || ($resource->dockerfile_location && ! $resource->docker_compose_raw)) {
                    $df = $resource->dockerfile_location ?: 'Dockerfile';
                    $compose = "services:\n  app:\n    build:\n      context: .\n      dockerfile: {$df}\n    networks: [webino]\nnetworks:\n  webino:\n    external: true\n";
                    $docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
                } elseif ($resource->docker_compose_raw) {
                    $docker->writeFile($server, $dir.'/docker-compose.yml', $resource->docker_compose_raw);
                } elseif ($resource->docker_compose_location) {
                    $docker->sshRun($server, 'cp '.escapeshellarg($dir.'/'.$resource->docker_compose_location).' '.escapeshellarg($dir.'/docker-compose.yml'));
                }
            } elseif ($resource->build_pack === 'image' || $resource->docker_image) {
                $image = $resource->docker_image;
                if (! $image) {
                    throw new \RuntimeException('platform.image_required');
                }
                $pull = $docker->pullImage($server, $image);
                $logs .= $pull['stdout']."\n".$pull['stderr']."\n";
                $port = (int) ($resource->ports_exposes ?: 80);
                $compose = "services:\n  app:\n    image: {$image}\n    ports: [\"{$port}:{$port}\"]\n    networks: [webino]\nnetworks:\n  webino:\n    external: true\n";
                $docker->writeFile($server, $dir.'/docker-compose.yml', $compose);
                $resource->docker_compose_raw = $compose;
            } elseif ($resource->docker_compose_raw) {
                $docker->writeFile($server, $dir.'/docker-compose.yml', $resource->docker_compose_raw);
            }

            $result = $docker->composeUp($server, $dir);
            $logs .= $result['stdout']."\n".$result['stderr'];
            $ok = $result['exit_code'] === 0;
            $deployment->update([
                'status' => $ok ? 'success' : 'failed',
                'logs' => $logs,
                'finished_at' => now(),
            ]);
            $settings = array_merge($resource->settings ?? [], ['site_dir' => $dir]);
            if ($resource->type === 'database' && empty($settings['connection_url'])) {
                $settings['connection_url'] = $this->connectionUrl($resource);
            }
            if (empty($settings['deploy_webhook_token'])) {
                $settings['deploy_webhook_token'] = Str::random(40);
            }
            $resource->update([
                'status' => $ok ? 'running' : 'failed',
                'settings' => $settings,
                'docker_compose_raw' => $resource->docker_compose_raw,
            ]);
            $notifier->notify(
                $ok ? 'deploy.ok' : 'deploy.fail',
                ($ok ? 'Deploy succeeded' : 'Deploy failed').': '.$resource->name,
                ['resource_id' => $resource->id, 'deployment_id' => $deployment->id]
            );
        } catch (Throwable $e) {
            $deployment->update(['status' => 'failed', 'logs' => trim($logs."\n".$e->getMessage()), 'finished_at' => now()]);
            $resource->update(['status' => 'failed']);
            $notifier->notify('deploy.fail', $e->getMessage(), ['resource_id' => $resource->id]);
        }
    }

    protected function databaseCompose(PlatformResource $resource): string
    {
        $type = $resource->database_type ?: 'postgresql';
        $name = Str::slug($resource->name);
        $pass = $resource->settings['db_password'] ?? Str::random(24);
        $user = $resource->settings['db_user'] ?? 'webino';
        $db = $resource->settings['db_name'] ?? 'webino';

        return match ($type) {
            'mysql', 'mariadb' => <<<YAML
services:
  db:
    image: {$type}:11
    environment:
      MYSQL_ROOT_PASSWORD: {$pass}
      MYSQL_DATABASE: {$db}
      MYSQL_USER: {$user}
      MYSQL_PASSWORD: {$pass}
    volumes: [{$name}_data:/var/lib/mysql]
    networks: [webino]
volumes:
  {$name}_data:
networks:
  webino:
    external: true
YAML,
            'mongodb' => <<<YAML
services:
  db:
    image: mongo:7
    environment:
      MONGO_INITDB_ROOT_USERNAME: {$user}
      MONGO_INITDB_ROOT_PASSWORD: {$pass}
    volumes: [{$name}_data:/data/db]
    networks: [webino]
volumes:
  {$name}_data:
networks:
  webino:
    external: true
YAML,
            'redis', 'keydb', 'dragonfly' => (function () use ($type, $pass, $name) {
                $image = match ($type) {
                    'keydb' => 'eqalpha/keydb:latest',
                    'dragonfly' => 'docker.dragonflydb.io/dragonflydb/dragonfly:latest',
                    default => 'redis:7-alpine',
                };

                return <<<YAML
services:
  db:
    image: {$image}
    command: ["--requirepass", "{$pass}"]
    volumes: [{$name}_data:/data]
    networks: [webino]
volumes:
  {$name}_data:
networks:
  webino:
    external: true
YAML;
            })(),
            'clickhouse' => <<<YAML
services:
  db:
    image: clickhouse/clickhouse-server:24
    environment:
      CLICKHOUSE_USER: {$user}
      CLICKHOUSE_PASSWORD: {$pass}
      CLICKHOUSE_DB: {$db}
    volumes: [{$name}_data:/var/lib/clickhouse]
    networks: [webino]
volumes:
  {$name}_data:
networks:
  webino:
    external: true
YAML,
            default => <<<YAML
services:
  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: {$db}
      POSTGRES_USER: {$user}
      POSTGRES_PASSWORD: {$pass}
    volumes: [{$name}_data:/var/lib/postgresql/data]
    networks: [webino]
volumes:
  {$name}_data:
networks:
  webino:
    external: true
YAML,
        };
    }

    protected function connectionUrl(PlatformResource $resource): string
    {
        $type = $resource->database_type ?: 'postgresql';
        $pass = $resource->settings['db_password'] ?? 'secret';
        $user = $resource->settings['db_user'] ?? 'webino';
        $db = $resource->settings['db_name'] ?? 'webino';
        $host = Str::slug($resource->name).'-db-1';

        return match ($type) {
            'mysql', 'mariadb' => "mysql://{$user}:{$pass}@{$host}:3306/{$db}",
            'mongodb' => "mongodb://{$user}:{$pass}@{$host}:27017",
            'redis', 'keydb', 'dragonfly' => "redis://:{$pass}@{$host}:6379",
            'clickhouse' => "clickhouse://{$user}:{$pass}@{$host}:8123/{$db}",
            default => "postgresql://{$user}:{$pass}@{$host}:5432/{$db}",
        };
    }
}
