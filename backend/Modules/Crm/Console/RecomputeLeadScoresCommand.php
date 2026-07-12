<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Services\LeadScoringService;

class RecomputeLeadScoresCommand extends Command
{
    protected $signature = 'crm:recompute-lead-scores';

    protected $description = 'Recompute lead_score for all CRM leads';

    public function handle(LeadScoringService $scoring): int
    {
        $count = $scoring->recomputeAll();
        $this->info("Updated {$count} leads.");

        return self::SUCCESS;
    }
}
