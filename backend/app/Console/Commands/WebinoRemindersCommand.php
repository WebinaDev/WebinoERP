<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Projects\Entities\ContractInstallment;
use Modules\Projects\Entities\ProjectTask;

/**
 * Daily reminders: per-installment / per-task notifications (parity with webinocrm cron).
 */
class WebinoRemindersCommand extends Command
{
    protected $signature = 'webino:reminders {--dry-run : Log only, no notifications}';

    protected $description = 'Remind installment payments, task due dates; optional SMS; recurring task copies';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $smsOn = $this->smsConfigured();

        $installments = ContractInstallment::query()
            ->with(['contract.customer', 'contract.lead'])
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->orderBy('due_date')
            ->get();

        $tasks = ProjectTask::query()
            ->with('assignee')
            ->whereDate('due_at', '<=', now()->addDay())
            ->where('status', '!=', 'done')
            ->orderBy('due_at')
            ->get();

        Log::info('webino:reminders', [
            'installments' => $installments->count(),
            'tasks' => $tasks->count(),
            'dry_run' => $dry,
            'sms_configured' => $smsOn,
        ]);

        if ($dry) {
            $this->info('Dry run — no messages sent.');

            return self::SUCCESS;
        }

        foreach ($installments as $inst) {
            $contract = $inst->contract;
            if (! $contract) {
                continue;
            }
            $email = $contract->customer?->email ?? $contract->lead?->email ?? null;
            $phone = $contract->customer?->phone ?? null;
            $subject = 'یادآوری قسط قرارداد: '.($contract->title ?? '');
            $body = 'سررسید قسط در تاریخ '.$inst->due_date?->toDateString().' — مبلغ: '.($inst->amount ?? '');
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::raw($body, function ($m) use ($email, $subject) {
                        $m->to($email)->subject($subject);
                    });
                } catch (\Throwable $e) {
                    Log::warning('webino:reminders installment mail failed', ['error' => $e->getMessage()]);
                }
            }
            if ($smsOn && $phone) {
                $this->queueSms($phone, $body);
            }
        }

        foreach ($tasks as $task) {
            $u = $task->assignee;
            if (! $u || ! filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $subject = 'یادآوری وظیفه: '.($task->title ?? '');
            $body = 'موعد وظیفه نزدیک است. سررسید: '.optional($task->due_at)->toDateTimeString();
            try {
                Mail::raw($body, function ($m) use ($u, $subject) {
                    $m->to($u->email)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning('webino:reminders task mail failed', ['error' => $e->getMessage()]);
            }
            if ($smsOn && $u->phone) {
                $this->queueSms($u->phone, $body);
            }
        }

        $this->spawnRecurringTasks();

        $summaryInstallments = $installments->count();
        $summaryTasks = $tasks->count();

        $this->info("Reminders processed: {$summaryInstallments} installments / {$summaryTasks} tasks.");

        return self::SUCCESS;
    }

    private function smsConfigured(): bool
    {
        $settings = IntegrationSetting::getJson('sms', 'settings', []);
        $provider = $settings['provider'] ?? config('integrations.sms.default', 'log');

        return $provider !== 'disabled' && $provider !== '';
    }

    private function queueSms(string $to, string $message): void
    {
        Log::channel('single')->info('sms.reminder', [
            'to' => $to,
            'message' => mb_substr($message, 0, 200),
        ]);
    }

    private function spawnRecurringTasks(): void
    {
        $templates = ProjectTask::query()
            ->whereNotNull('recurrence')
            ->where('status', 'done')
            ->get();

        foreach ($templates as $t) {
            $r = $t->recurrence;
            if (! is_array($r) || ($r['repeat'] ?? '') !== 'daily') {
                continue;
            }
            $exists = ProjectTask::query()
                ->where('project_id', $t->project_id)
                ->where('title', $t->title.' (تکرار روزانه)')
                ->whereDate('created_at', today())
                ->exists();
            if ($exists) {
                continue;
            }
            ProjectTask::query()->create([
                'project_id' => $t->project_id,
                'title' => $t->title.' (تکرار روزانه)',
                'status' => 'todo',
                'priority' => $t->priority ?? 'normal',
                'assignee_id' => $t->assignee_id,
                'due_at' => now()->addDay(),
                'created_by' => $t->created_by,
                'recurrence' => $t->recurrence,
            ]);
        }
    }
}
