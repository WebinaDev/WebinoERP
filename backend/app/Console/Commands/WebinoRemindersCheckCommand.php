<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\CronRun;
use Modules\Core\Http\Controllers\ReminderController;

class WebinoRemindersCheckCommand extends Command
{
    protected $signature = 'webino:reminders:check';

    protected $description = 'Check due reminders and dispatch them to notifications';

    public function handle(ReminderController $controller): int
    {
        $started = now();
        $status = 'ok';
        $error = null;
        $count = 0;

        try {
            $count = $controller->runDueReminders();
            $this->info("Processed {$count} reminders.");
        } catch (\Throwable $e) {
            $status = 'error';
            $error = $e->getMessage();
            Log::error('webino:reminders:check.failed', ['error' => $error]);
            $this->error($error);
        }

        CronRun::query()->create([
            'job' => 'webino:reminders:check',
            'status' => $status,
            'duration_ms' => max(0, now()->diffInMilliseconds($started)),
            'summary' => ['processed' => $count],
            'error' => $error,
            'started_at' => $started,
            'finished_at' => now(),
        ]);

        return $status === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
