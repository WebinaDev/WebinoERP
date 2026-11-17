<?php

namespace Modules\Workflow\Console;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Console\Command;
use Modules\Workflow\Entities\WfStepApproval;

class CheckWorkflowSlaReminders extends Command
{
    protected $signature = 'workflow:check-sla-reminders';

    protected $description = 'Check pending workflow approvals nearing or past SLA and send notifications';

    public function handle(): int
    {
        $overdue = WfStepApproval::query()
            ->with(['instance'])
            ->where('status', 'pending')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addHours(2))
            ->get();

        $count = 0;
        foreach ($overdue as $approval) {
            $instance = $approval->instance;
            if (! $instance || $instance->status !== 'pending') {
                continue;
            }

            $title = "یادآوری مهلت زمانی تایید: {$instance->title}";
            $msg = "مهلت تاییدیه گام {$approval->step_order} رو به اتمام است.";

            if ($approval->target_user_id) {
                $u = User::find($approval->target_user_id);
                if ($u) {
                    StaffNotifier::notify($u, $title, $msg);
                    $count++;
                }
            } elseif ($approval->target_role) {
                $users = User::role($approval->target_role)->get();
                foreach ($users as $u) {
                    StaffNotifier::notify($u, $title, $msg);
                    $count++;
                }
            }
        }

        $this->info("Workflow SLA reminders sent: {$count}");

        return self::SUCCESS;
    }
}
