<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Entities\BiReport;
use Modules\Core\Mail\ScheduledReportMail;
use Modules\Core\Http\Controllers\StudioController;

class SendScheduledReportsCommand extends Command
{
    protected $signature = 'webino:reports:send';

    protected $description = 'Email scheduled BI reports';

    public function handle(): int
    {
        $sent = 0;
        $reports = BiReport::query()->whereNotNull('schedule')->orderBy('id')->get();
        foreach ($reports as $report) {
            $schedule = is_array($report->schedule) ? $report->schedule : [];
            $email = (string) ($schedule['email'] ?? '');
            $frequency = (string) ($schedule['frequency'] ?? '');
            if ($email === '' || ! $this->due($report, $frequency, $schedule)) {
                continue;
            }
            $csv = $this->csv($report);
            Mail::to($email)->send(new ScheduledReportMail($report->name, $csv, (int) $report->id));
            $report->update(['last_sent_at' => now()]);
            $sent++;
        }
        $this->info('reports sent '.$sent);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function due(BiReport $report, string $frequency, array $schedule): bool
    {
        if ($frequency === 'weekly') {
            $weekday = (int) ($schedule['weekday'] ?? 0);
            if ((int) now()->dayOfWeek !== $weekday) {
                return false;
            }

            return ! $report->last_sent_at || $report->last_sent_at->lt(now()->startOfWeek());
        }
        if ($frequency !== 'daily') {
            return false;
        }

        return ! $report->last_sent_at || $report->last_sent_at->lt(now()->startOfDay());
    }

    private function csv(BiReport $report): string
    {
        $studio = app(StudioController::class);
        $payload = json_decode($studio->runReport($report->id)->getContent(), true);
        $columns = (array) data_get($payload, 'data.columns', $report->columns);
        $rows = (array) data_get($payload, 'data.rows', []);
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
