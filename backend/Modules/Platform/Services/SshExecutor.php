<?php

namespace Modules\Platform\Services;

use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Entities\PlatformSshKey;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Coolify-style remote execution over SSH.
 * Localhost servers run commands without SSH.
 */
class SshExecutor
{
    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function run(PlatformServer $server, string $command, int $timeout = 120): array
    {
        if ($server->is_localhost || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true)) {
            return $this->local($command, $timeout);
        }

        $key = $server->ssh_key_id ? PlatformSshKey::query()->find($server->ssh_key_id) : null;
        if (! $key) {
            throw new RuntimeException('platform.ssh_key_required');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'webino_ssh_');
        file_put_contents($tmp, $key->private_key."\n");
        chmod($tmp, 0600);

        try {
            $target = sprintf('%s@%s', $server->user, $server->ip);
            $process = new Process([
                'ssh',
                '-i', $tmp,
                '-p', (string) $server->port,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'BatchMode=yes',
                '-o', 'ConnectTimeout=15',
                $target,
                $command,
            ]);
            $process->setTimeout($timeout);
            $process->run();

            return [
                'exit_code' => $process->getExitCode() ?? 1,
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function validate(PlatformServer $server): array
    {
        $result = $this->run($server, 'echo ok && uname -a && docker --version 2>/dev/null || echo NO_DOCKER', 30);
        if ($result['exit_code'] === 0 && str_contains($result['stdout'], 'ok')) {
            $server->status = str_contains($result['stdout'], 'NO_DOCKER') ? 'reachable' : 'ready';
            $server->last_seen_at = now();
            $server->save();
        } else {
            $server->status = 'unreachable';
            $server->save();
        }

        return $result;
    }

    /**
     * Bootstrap Docker + Caddy + destination network on a remote server.
     *
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function bootstrap(PlatformServer $server): array
    {
        $script = <<<'SH'
set -e
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi
docker network inspect webino >/dev/null 2>&1 || docker network create webino
if ! command -v caddy >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y && apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -y && apt-get install -y caddy || true
  fi
fi
mkdir -p /etc/caddy/webino.d /var/lib/webino/sites
if [ ! -f /etc/caddy/Caddyfile ]; then
  printf '%s\n' '{
  email admin@localhost
}
import /etc/caddy/webino.d/*.caddy' > /etc/caddy/Caddyfile
elif ! grep -q 'webino.d' /etc/caddy/Caddyfile 2>/dev/null; then
  printf '\nimport /etc/caddy/webino.d/*.caddy\n' >> /etc/caddy/Caddyfile
fi
systemctl enable --now caddy 2>/dev/null || true
caddy reload --config /etc/caddy/Caddyfile 2>/dev/null || systemctl reload caddy 2>/dev/null || true
echo ok
SH;
        $result = $this->run($server, $script, 600);
        if ($result['exit_code'] === 0) {
            $server->status = 'ready';
            $server->last_seen_at = now();
            $server->save();
        }

        return $result;
    }

    /**
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    protected function local(string $command, int $timeout): array
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }
}
