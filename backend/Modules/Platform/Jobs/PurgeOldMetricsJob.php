<?php

namespace Modules\Platform\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformMetricRollupDaily;
use Modules\Platform\Entities\PlatformMetricRollupHourly;
use Modules\Platform\Entities\PlatformMetricSnapshot;

class PurgeOldMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // 1. Purge raw snapshots older than 3 days
        $rawCutoff = Carbon::now()->subDays(3);
        $deletedRaw = PlatformMetricSnapshot::query()
            ->where('collected_at', '<', $rawCutoff)
            ->delete();

        // 2. Purge hourly rollups older than 90 days
        $hourlyCutoff = Carbon::now()->subDays(90);
        $deletedHourly = PlatformMetricRollupHourly::query()
            ->where('bucket_start', '<', $hourlyCutoff)
            ->delete();

        // 3. Purge daily rollups older than 365 days
        $dailyCutoff = Carbon::now()->subDays(365);
        $deletedDaily = PlatformMetricRollupDaily::query()
            ->where('bucket_date', '<', $dailyCutoff)
            ->delete();

        Log::info("PurgeOldMetrics: deleted {$deletedRaw} raw snapshots, {$deletedHourly} hourly rollups, {$deletedDaily} daily rollups.");
    }
}
