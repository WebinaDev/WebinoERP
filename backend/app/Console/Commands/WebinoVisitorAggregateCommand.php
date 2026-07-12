<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\CronRun;

class WebinoVisitorAggregateCommand extends Command
{
    protected $signature = 'webino:visitor:aggregate {--days=1}';

    protected $description = 'Aggregate visitor stats into core_visitor_daily';

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 30));
        $started = now();
        $processed = 0;
        $status = 'ok';
        $error = null;

        try {
            for ($i = $days; $i >= 1; $i--) {
                $day = now()->subDays($i)->toDateString();
                $from = $day.' 00:00:00';
                $to = $day.' 23:59:59';

                $visits = DB::table('core_visits')->whereBetween('created_at', [$from, $to]);
                $visitCount = (clone $visits)->count();
                $unique = (clone $visits)->whereNotNull('ip')->distinct('ip')->count('ip');
                $pageviews = DB::table('core_visitor_pages')->whereBetween('created_at', [$from, $to])->count();
                $avgMs = (int) DB::table('core_visitor_pages')->whereBetween('created_at', [$from, $to])->avg('ms_on_page');

                DB::table('core_visitor_daily')->updateOrInsert(
                    ['date' => $day],
                    [
                        'uniques' => (int) $unique,
                        'visits' => (int) $visitCount,
                        'pageviews' => (int) $pageviews,
                        'avg_session_ms' => max(0, $avgMs),
                        'updated_at' => now(),
                    ]
                );

                $processed++;
            }
        } catch (\Throwable $e) {
            $status = 'error';
            $error = $e->getMessage();
            $this->error($error);
        }

        CronRun::query()->create([
            'job' => 'webino:visitor:aggregate',
            'status' => $status,
            'duration_ms' => max(0, now()->diffInMilliseconds($started)),
            'summary' => ['days' => $days, 'processed' => $processed],
            'error' => $error,
            'started_at' => $started,
            'finished_at' => now(),
        ]);

        return $status === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
