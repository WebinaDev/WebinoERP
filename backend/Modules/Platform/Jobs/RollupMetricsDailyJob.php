<?php

namespace Modules\Platform\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Entities\PlatformMetricRollupDaily;
use Modules\Platform\Entities\PlatformMetricRollupHourly;

class RollupMetricsDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?Carbon $targetDate = null) {}

    public function handle(): void
    {
        $target = $this->targetDate ? $this->targetDate->copy()->startOfDay() : Carbon::now()->subDay()->startOfDay();
        $dateStr = $target->toDateString();
        $start = $target->toDateTimeString();
        $end = $target->copy()->endOfDay()->toDateTimeString();

        $rows = PlatformMetricRollupHourly::query()
            ->whereBetween('bucket_start', [$start, $end])
            ->select([
                'server_id',
                'project_id',
                'resource_id',
                'scope',
                'metric_key',
                'unit',
                DB::raw('AVG(value_avg) as val_avg'),
                DB::raw('MAX(value_max) as val_max'),
                DB::raw('MIN(value_min) as val_min'),
                DB::raw('SUM(sample_count) as s_count'),
            ])
            ->groupBy('server_id', 'project_id', 'resource_id', 'scope', 'metric_key', 'unit')
            ->get();

        foreach ($rows as $row) {
            PlatformMetricRollupDaily::query()->updateOrCreate(
                [
                    'server_id' => $row->server_id,
                    'project_id' => $row->project_id,
                    'resource_id' => $row->resource_id,
                    'scope' => $row->scope,
                    'metric_key' => $row->metric_key,
                    'bucket_date' => $dateStr,
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
