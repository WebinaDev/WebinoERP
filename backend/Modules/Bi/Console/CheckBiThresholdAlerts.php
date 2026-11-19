<?php

namespace Modules\Bi\Console;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Modules\Bi\Entities\BiThresholdAlert;
use Modules\Bi\Services\BiDataMartService;

class CheckBiThresholdAlerts extends Command
{
    protected $signature = 'bi:check-threshold-alerts';

    protected $description = 'Evaluate operational KPI thresholds and send warnings if breached';

    public function handle(BiDataMartService $dataMart): int
    {
        $alerts = BiThresholdAlert::query()->where('is_active', true)->get();
        $kpis = $dataMart->getKpiSummary();
        $triggered = 0;

        foreach ($alerts as $alert) {
            $val = match ($alert->metric_key) {
                'low_stock' => (float) ($kpis['inventory_turnover']['total_units'] ?? 0),
                'sla_breaches' => 100 - (float) ($kpis['support_sla']['compliance_rate'] ?? 100),
                'lead_conversion' => (float) ($kpis['lead_conversion']['conversion_rate'] ?? 0),
                default => 0.0,
            };

            $thresh = (float) $alert->threshold_value;
            $breached = match ($alert->comparator) {
                'gt' => $val > $thresh,
                'gte' => $val >= $thresh,
                'lt' => $val < $thresh,
                'lte' => $val <= $thresh,
                'eq' => abs($val - $thresh) < 0.001,
                default => false,
            };

            // Runs hourly: do not repeat the same alert more than once a day.
            if ($breached && $alert->last_triggered_at && $alert->last_triggered_at->gt(now()->subDay())) {
                continue;
            }

            if ($breached) {
                $alert->update(['last_triggered_at' => now()]);
                if ($alert->notify_user_id) {
                    $u = User::find($alert->notify_user_id);
                    if ($u) {
                        StaffNotifier::notify((int) $u->id, 'bi.alert', "هشدار آستانه هوش تجاری: {$alert->title}", "مقدار شاخص {$alert->metric_key} برابر {$val} بوده و حد آستانه نقض گردید.");
                        $triggered++;
                    }
                }
            }
        }

        $this->info("BI threshold alerts triggered: {$triggered}");

        return self::SUCCESS;
    }
}
