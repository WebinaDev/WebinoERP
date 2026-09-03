<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Platform\Entities\PlatformBackup;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Services\DockerRemoteService;
use Throwable;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $backupId) {}

    public function handle(DockerRemoteService $docker): void
    {
        $backup = PlatformBackup::query()->find($this->backupId);
        if (! $backup) return;
        $resource = PlatformResource::query()->find($backup->resource_id);
        if (! $resource) return;
        $backup->update(['status' => 'running']);
        try {
            $server = $resource->server()->first();
            $path = '/var/lib/webino/backups/'.$resource->name.'-'.now()->format('YmdHis').'.sql.gz';
            $cmd = sprintf(
                'mkdir -p /var/lib/webino/backups && docker exec $(docker ps -qf name=%s) sh -c "pg_dump -U webino webino 2>/dev/null || mysqldump -u root webino 2>/dev/null" | gzip > %s || echo fail > %s',
                escapeshellarg($resource->name),
                escapeshellarg($path),
                escapeshellarg($path)
            );
            $r = $docker->sshRun($server, $cmd, 300);
            $ok = $r['exit_code'] === 0;
            $backup->update([
                'status' => $ok ? 'success' : 'failed',
                'path' => $path,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $backup->update(['status' => 'failed', 'finished_at' => now()]);
        }
    }
}
