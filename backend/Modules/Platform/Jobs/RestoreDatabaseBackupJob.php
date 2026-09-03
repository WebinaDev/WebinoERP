<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Modules\Platform\Entities\PlatformBackup;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Services\DockerRemoteService;
use Modules\Platform\Services\PlatformNotifier;
use Throwable;

class RestoreDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $backupId) {}

    public function handle(DockerRemoteService $docker, PlatformNotifier $notifier): void
    {
        $backup = PlatformBackup::query()->find($this->backupId);
        if (! $backup) {
            return;
        }
        $resource = PlatformResource::query()->find($backup->resource_id);
        if (! $resource) {
            return;
        }
        $backup->update(['status' => 'restoring']);
        try {
            $server = $resource->server()->first();
            $path = $backup->path;
            if (! $server || ! $path) {
                throw new \RuntimeException('platform.backup_restore_missing');
            }
            $container = ($resource->settings['db_container'] ?? null) ?: (Str::slug($resource->name).'-db-1');
            $type = $resource->database_type ?: 'postgresql';
            $cmd = match ($type) {
                'mysql', 'mariadb' => 'gunzip -c '.escapeshellarg($path).' | docker exec -i '.escapeshellarg($container).' mysql -u root',
                'mongodb' => 'gunzip -c '.escapeshellarg($path).' | docker exec -i '.escapeshellarg($container).' mongorestore --archive',
                default => 'gunzip -c '.escapeshellarg($path).' | docker exec -i '.escapeshellarg($container).' psql -U webino webino',
            };
            $r = $docker->sshRun($server, $cmd, 600);
            $ok = $r['exit_code'] === 0;
            $backup->update(['status' => $ok ? 'restored' : 'failed']);
            $notifier->notify($ok ? 'backup.restore.ok' : 'backup.restore.fail', $resource->name, ['backup_id' => $backup->id]);
        } catch (Throwable $e) {
            $backup->update(['status' => 'failed']);
            $notifier->notify('backup.restore.fail', $e->getMessage(), ['backup_id' => $backup->id]);
        }
    }
}
