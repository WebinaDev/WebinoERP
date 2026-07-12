<?php

namespace Modules\Crm\Services;

use Modules\Core\Services\AutomationEngine;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;

class CrmAutomationDispatcher
{
    public function __construct(private readonly AutomationEngine $engine) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    public function leadStatusChanged(CrmLead $lead, int $previousStatusId, array $extra = []): void
    {
        $this->engine->dispatch('crm.lead.status_changed', [
            'lead_id' => $lead->id,
            'status_id' => $lead->status_id,
            'previous_status_id' => $previousStatusId,
            'assigned_to' => $lead->assigned_to,
            'user_id' => $lead->assigned_to ?? auth()->id(),
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function dealStageChanged(CrmDeal $deal, int $previousStageId, array $extra = []): void
    {
        $this->engine->dispatch('crm.deal.stage_changed', [
            'deal_id' => $deal->id,
            'stage_id' => $deal->stage_id,
            'previous_stage_id' => $previousStageId,
            'pipeline_id' => $deal->pipeline_id,
            'account_id' => $deal->account_id,
            'user_id' => $deal->created_by ?? auth()->id(),
            ...$extra,
        ]);
    }
}
