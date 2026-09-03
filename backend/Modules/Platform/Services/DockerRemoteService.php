<?php

namespace Modules\Platform\Services;

use Modules\Platform\Entities\PlatformServer;

class DockerRemoteService
{
    public function __construct(private readonly SshExecutor $ssh) {}

    public function ps(PlatformServer $server): array
    {
        $r = $this->ssh->run($server, 'docker ps -a --format "{{.ID}}|{{.Names}}|{{.Status}}|{{.Image}}|{{.Ports}}"');
        $rows = [];
        foreach (preg_split('/\r?\n/', trim($r['stdout'])) as $line) {
            if ($line === '') continue;
            $p = explode('|', $line, 5);
            $rows[] = [
                'id' => $p[0] ?? '',
                'name' => $p[1] ?? '',
                'status' => $p[2] ?? '',
                'image' => $p[3] ?? '',
                'ports' => $p[4] ?? '',
            ];
        }
        return $rows;
    }

    public function images(PlatformServer $server): array
    {
        $r = $this->ssh->run($server, 'docker images --format "{{.Repository}}:{{.Tag}}|{{.ID}}|{{.Size}}"');
        $rows = [];
        foreach (preg_split('/\r?\n/', trim($r['stdout'])) as $line) {
            if ($line === '') continue;
            $p = explode('|', $line, 3);
            $rows[] = ['ref' => $p[0] ?? '', 'id' => $p[1] ?? '', 'size' => $p[2] ?? ''];
        }
        return $rows;
    }

    public function pullImage(PlatformServer $server, string $ref): array
    {
        return $this->ssh->run($server, 'docker pull '.escapeshellarg($ref), 900);
    }

    public function deleteImage(PlatformServer $server, string $ref): array
    {
        return $this->ssh->run($server, 'docker rmi -f '.escapeshellarg($ref), 120);
    }

    public function containerAction(PlatformServer $server, string $id, string $action): array
    {
        $action = in_array($action, ['start', 'stop', 'restart'], true) ? $action : 'restart';
        return $this->ssh->run($server, sprintf('docker %s %s', $action, escapeshellarg($id)));
    }

    public function logs(PlatformServer $server, string $id, int $tail = 200): string
    {
        $r = $this->ssh->run($server, sprintf('docker logs --tail %d %s 2>&1', $tail, escapeshellarg($id)));
        return $r['stdout'].$r['stderr'];
    }

    public function networks(PlatformServer $server): array
    {
        $r = $this->ssh->run($server, 'docker network ls --format "{{.ID}}|{{.Name}}|{{.Driver}}"');
        $rows = [];
        foreach (preg_split('/\r?\n/', trim($r['stdout'])) as $line) {
            if ($line === '') continue;
            $p = explode('|', $line, 3);
            $rows[] = ['id' => $p[0] ?? '', 'name' => $p[1] ?? '', 'driver' => $p[2] ?? ''];
        }
        return $rows;
    }

    public function createNetwork(PlatformServer $server, string $name): array
    {
        return $this->ssh->run($server, 'docker network create '.escapeshellarg($name));
    }

    public function cleanup(PlatformServer $server): array
    {
        return $this->ssh->run($server, 'docker system prune -af --volumes', 300);
    }

    public function metrics(PlatformServer $server): array
    {
        $r = $this->ssh->run($server, 'echo CPU:$(grep -c ^processor /proc/cpuinfo); echo MEM:$(free -m | awk \'/Mem:/{print $3"/"$2}\'); echo DISK:$(df -h / | awk \'NR==2{print $3"/"$2" "$5}\')');
        return [
            'raw' => $r['stdout'],
            'exit_code' => $r['exit_code'],
        ];
    }

    public function composeUp(PlatformServer $server, string $dir): array
    {
        return $this->ssh->run($server, 'cd '.escapeshellarg($dir).' && docker compose up -d --build', 900);
    }

    public function composeDown(PlatformServer $server, string $dir): array
    {
        return $this->ssh->run($server, 'cd '.escapeshellarg($dir).' && docker compose down', 300);
    }

    public function sshRun(PlatformServer $server, string $command, int $timeout = 120): array
    {
        return $this->ssh->run($server, $command, $timeout);
    }

    public function writeFile(PlatformServer $server, string $path, string $contents): array
    {
        $b64 = base64_encode($contents);
        $cmd = sprintf(
            'mkdir -p $(dirname %s) && echo %s | base64 -d > %s',
            escapeshellarg($path),
            escapeshellarg($b64),
            escapeshellarg($path)
        );
        return $this->ssh->run($server, $cmd);
    }
}
