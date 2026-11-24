<?php

namespace Modules\Platform\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Entities\PlatformMetricRollupHourly;
use Modules\Platform\Entities\PlatformMetricSnapshot;

class RollupMetricsHourlyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?Carbon $targetHour = null) {}

    public function handle(): void
    {
        $target = $this->targetHour ? $this->targetHour->copy()->startOfHour() : Carbon::now()->subHour()->startOfHour();
        $start = $target->toDateTimeString();
        $end = $target->copy()->endOfHour()->toDateTimeString();

        $rows = PlatformMetricSnapshot::query()
            ->whereBetween('collected_at', [$start, $end])
            ->select([
                'server_id',
                'project_id',
                'resource_id',
                'scope',
                'metric_key',
                'unit',
                DB::raw('AVG(value) as val_avg'),
                DB::raw('MAX(value) as val_max'),
                DB::raw('MIN(value) as val_min'),
                DB::raw('COUNT(*) as s_count'),
            ])
            ->groupBy('server_id', 'project_id', 'resource_id', 'scope', 'metric_key', 'unit')
            ->get();

        foreach ($rows as $row) {
            PlatformMetricRollupHourly::query()->updateOrCreate(
                [
                    'server_id' => $row->server_id,
                    'project_id' => $row->project_id,
                    'resource_id' => $row->resource_id,
                    'scope' => $row->scope,
                    'metric_key' => $row->metric_key,
                    'bucket_start' => $start,
                ],
                [
                    'value_avg' => (float) $row->val_avg,
                    'value_max' => (float) $row->val_max,
                    'value_min' => (float) $row->val_min,
                    'sample_count' => (int) $row->s_count,
                    'unit' => $row->unit,
                ]
            );
        }
    }
}
