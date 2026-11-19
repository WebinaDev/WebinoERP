<?php

namespace Modules\Assets\Console;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Modules\Assets\Entities\AstMaintenancePlan;

class CheckPreventiveMaintenanceDue extends Command
{
    protected $signature = 'assets:check-pm-due';

    protected $description = 'Flag fixed assets with upcoming or overdue preventive maintenance';

    public function handle(): int
    {
        $duePlans = AstMaintenancePlan::query()
            ->with('asset')
            ->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays(7)->toDateString())
            ->get();

        $count = count($duePlans);
        if ($count > 0) {
            $managers = User::role(['system_manager', 'team_member'])->get();
            foreach ($managers as $mgr) {
                StaffNotifier::notify((int) $mgr->id, 'assets.pm_due', "سررسید نگهداری و تعمیرات پیشگیرانه (PM)", "تعداد {$count} برنامه نت پیشگیرانه اموال و تجهیزات طی ۷ روز آینده سررسید می‌شوند.");
            }
        }

        $this->info("Assets PM due checked: {$count}");

        return self::SUCCESS;
    }
}
