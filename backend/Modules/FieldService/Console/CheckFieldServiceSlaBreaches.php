<?php

namespace Modules\FieldService\Console;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Modules\FieldService\Entities\FsServiceOrder;

class CheckFieldServiceSlaBreaches extends Command
{
    protected $signature = 'field-service:check-sla-breaches';

    protected $description = 'Identify overdue field service orders and notify managers';

    public function handle(): int
    {
        $now = now();
        $breached = 0;

        // Check response SLA
        $pendingResponse = FsServiceOrder::query()
            ->whereIn('status', ['requested'])
            ->where('sla_response_breached', false)
            ->whereNotNull('sla_response_due')
            ->where('sla_response_due', '<', $now)
            ->get();

        foreach ($pendingResponse as $order) {
            $order->update(['sla_response_breached' => true]);
            $breached++;
        }

        // Check resolution SLA
        $pendingResolution = FsServiceOrder::query()
            ->whereIn('status', ['requested', 'dispatched', 'in_progress'])
            ->where('sla_resolution_breached', false)
            ->whereNotNull('sla_resolution_due')
            ->where('sla_resolution_due', '<', $now)
            ->get();

        foreach ($pendingResolution as $order) {
            $order->update(['sla_resolution_breached' => true]);
            $breached++;
        }

        if ($breached > 0) {
            $managers = User::role(['system_manager', 'project_manager'])->get();
            foreach ($managers as $mgr) {
                StaffNotifier::notify($mgr, "نقض SLA خدمات پس از فروش", "تعداد {$breached} سفارش خدمات از مهلت زمانی مقرر (SLA) عبور کرده است.");
            }
        }

        $this->info("Field Service SLA breaches flagged: {$breached}");

        return self::SUCCESS;
    }
}
