<?php

namespace Modules\Bi\Console;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Modules\Bi\Entities\BiSubscription;
use Modules\Bi\Services\BiDataMartService;

class DeliverScheduledBiReports extends Command
{
    protected $signature = 'bi:deliver-scheduled-reports';

    protected $description = 'Generate and deliver scheduled BI reports to subscribers';

    public function handle(BiDataMartService $dataMart): int
    {
        $subs = BiSubscription::query()->where('is_active', true)->get();
        $count = 0;

        foreach ($subs as $sub) {
            if (! $this->isDue($sub)) {
                continue;
            }
            $kpis = $dataMart->getKpiSummary($sub->filters ?? []);
            $recipients = $sub->recipients ?? [];

            foreach ($recipients as $recipient) {
                $user = is_numeric($recipient) ? User::find((int) $recipient) : User::where('email', $recipient)->first();
                if ($user) {
                    StaffNotifier::notify((int) $user->id, 'bi.report', "گزارش دوره‌ای هوش تجاری: {$sub->name}", "گزارش دوره‌ای {$sub->report_type} تولید و ارسال گردید.", [
                        'subscription_id' => $sub->id,
                        'report_type' => $sub->report_type,
                        'kpis' => $kpis,
                    ]);
                    $count++;
                }
            }

            $sub->update(['last_sent_at' => now()]);
        }

        $this->info("BI scheduled report deliveries: {$count}");

        return self::SUCCESS;
    }

    /** The command runs hourly; honour each subscription's own frequency. */
    private function isDue(BiSubscription $sub): bool
    {
        if (! $sub->last_sent_at) {
            return true;
        }

        $next = match ($sub->frequency) {
            'daily' => $sub->last_sent_at->copy()->addDay(),
            'monthly' => $sub->last_sent_at->copy()->addMonth(),
            default => $sub->last_sent_at->copy()->addWeek(),
        };

        return now()->gte($next->subMinutes(5));
    }
}
