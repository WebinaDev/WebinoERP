<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Entities\CronRun;

class WebinoSessionsCleanupCommand extends Command
{
    protected $signature = 'webino:sessions:cleanup {--days=30}';

    protected $description = 'Delete stale personal access tokens by inactivity';

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 365));
        $cutoff = now()->subDays($days);
        $started = now();

        $deleted = PersonalAccessToken::query()
            ->where(function ($q) use ($cutoff) {
                $q->whereNotNull('last_activity_at')->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($qq) use ($cutoff) {
                        $qq->whereNull('last_activity_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->delete();

        CronRun::query()->create([
            'job' => 'webino:sessions:cleanup',
            'status' => 'ok',
            'duration_ms' => max(0, now()->diffInMilliseconds($started)),
            'summary' => ['days' => $days, 'deleted' => $deleted],
            'started_at' => $started,
            'finished_at' => now(),
        ]);

        $this->info("Deleted {$deleted} stale sessions");

        return self::SUCCESS;
    }
}
