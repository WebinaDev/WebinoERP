<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmOnboarding;
use Modules\Hrm\Entities\HrmOnboardingTask;
use Modules\Hrm\Entities\HrmOnboardingTemplate;

class HrmOnboardingService
{
    public function start(HrmEmployee $employee, int $templateId, ?int $applicantId = null): HrmOnboarding
    {
        $template = HrmOnboardingTemplate::query()->with('items')->findOrFail($templateId);

        return DB::transaction(function () use ($employee, $template, $applicantId) {
            $onboarding = HrmOnboarding::query()->create([
                'employee_id' => $employee->id,
                'template_id' => $template->id,
                'applicant_id' => $applicantId,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            foreach ($template->items as $item) {
                HrmOnboardingTask::query()->create([
                    'onboarding_id' => $onboarding->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'document_category' => $item->document_category,
                    'status' => 'pending',
                    'owner' => 'employee',
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $onboarding->load('tasks');
        });
    }

    public function refreshProgress(HrmOnboarding $onboarding): HrmOnboarding
    {
        $onboarding->load('tasks');
        $open = $onboarding->tasks->contains(fn (HrmOnboardingTask $t) => $t->status !== 'done');
        $onboarding->status = $open ? 'in_progress' : 'completed';
        $onboarding->completed_at = $open ? null : ($onboarding->completed_at ?? now());
        $onboarding->save();

        return $onboarding;
    }

    public function attachDocument(int $employeeId, ?string $category, int $documentId): void
    {
        if (! $category) {
            return;
        }
        $tasks = HrmOnboardingTask::query()
            ->where('status', 'pending')
            ->where('document_category', $category)
            ->whereHas('onboarding', fn ($q) => $q->where('employee_id', $employeeId)->where('status', '!=', 'completed'))
            ->get();
        foreach ($tasks as $task) {
            $task->update([
                'status' => 'done',
                'document_id' => $documentId,
                'completed_at' => now(),
            ]);
            $this->refreshProgress($task->onboarding);
        }
    }
}
