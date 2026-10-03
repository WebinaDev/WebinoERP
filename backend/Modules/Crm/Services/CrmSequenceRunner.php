<?php

namespace Modules\Crm\Services;

use Modules\Crm\Entities\CrmSequenceEnrollment;
use Modules\Crm\Entities\CrmSequenceStep;

class CrmSequenceRunner
{
    public function __construct(private readonly CrmOutboundMessenger $messenger) {}

    public function runDue(): int
    {
        $count = 0;
        $due = CrmSequenceEnrollment::query()
            ->with('sequence.steps')
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($due as $enrollment) {
            $steps = $enrollment->sequence?->steps ?? collect();
            /** @var CrmSequenceStep|null $step */
            $step = $steps->values()->get($enrollment->step_index);
            if (! $step || ! $step->template_id || ! $enrollment->sequence?->is_active) {
                $enrollment->update(['status' => 'completed', 'next_run_at' => null]);
                continue;
            }

            $template = $step->template()->first();
            if ($template && $template->is_active) {
                try {
                    $this->messenger->sendTemplate(
                        $template,
                        $enrollment->related_type,
                        (int) $enrollment->related_id,
                        $enrollment->created_by,
                    );
                    $count++;
                } catch (\Throwable) {
                    $enrollment->update(['status' => 'paused']);
                    continue;
                }
            }

            $next = $steps->values()->get($enrollment->step_index + 1);
            if (! $next) {
                $enrollment->update(['status' => 'completed', 'next_run_at' => null, 'step_index' => $enrollment->step_index + 1]);
                continue;
            }

            $enrollment->update([
                'step_index' => $enrollment->step_index + 1,
                'next_run_at' => now()->addDays((int) $next->delay_days),
            ]);
        }

        return $count;
    }
}
