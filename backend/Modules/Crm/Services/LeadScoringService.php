<?php

namespace Modules\Crm\Services;

use Modules\Crm\Entities\CrmLead;

class LeadScoringService
{
    /** @var array<int, array{field: string, weight: int, match?: string}> */
    private array $rules = [
        ['field' => 'email', 'weight' => 15],
        ['field' => 'company', 'weight' => 10],
        ['field' => 'mobile', 'weight' => 10],
        ['field' => 'rating', 'weight' => 5, 'match' => 'gte:3'],
    ];

    public function score(CrmLead $lead): int
    {
        $total = 0;
        foreach ($this->rules as $rule) {
            $value = $lead->{$rule['field']} ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if (($rule['match'] ?? null) === 'gte:3' && (int) $value >= 3) {
                $total += $rule['weight'];
            } elseif (! isset($rule['match'])) {
                $total += $rule['weight'];
            }
        }

        if ($lead->source_id) {
            $total += 5;
        }
        if ($lead->assigned_to) {
            $total += 5;
        }

        $daysOld = $lead->created_at ? $lead->created_at->diffInDays(now()) : 0;
        $decay = min(30, (int) floor($daysOld / 7) * 2);
        $total = max(0, $total - $decay);

        return min(100, $total);
    }

    public function applyAndSave(CrmLead $lead): CrmLead
    {
        $lead->lead_score = $this->score($lead);
        $lead->saveQuietly();

        return $lead;
    }

    public function recomputeAll(): int
    {
        $count = 0;
        CrmLead::query()->chunkById(200, function ($leads) use (&$count) {
            foreach ($leads as $lead) {
                $this->applyAndSave($lead);
                $count++;
            }
        });

        return $count;
    }
}
