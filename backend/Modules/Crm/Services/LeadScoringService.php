<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmScoreRule;
use Modules\Crm\Support\CrmRelation;

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
        $custom = $this->activeRules();
        $total = $custom === null ? $this->legacyScore($lead) : $this->customScore($lead, $custom);
        $hasActivityRule = $custom !== null && $custom->contains(fn (CrmScoreRule $rule) => $rule->kind === 'activity');
        if (! $hasActivityRule) {
            $total += $this->activityBonus($lead);
        }

        $daysOld = $lead->created_at ? $lead->created_at->diffInDays(now()) : 0;
        $decay = min(30, (int) floor($daysOld / 7) * 2);

        return min(100, max(0, $total - $decay));
    }

    private function legacyScore(CrmLead $lead): int
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

        return $total;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CrmScoreRule>  $rules
     */
    private function customScore(CrmLead $lead, $rules): int
    {
        $total = 0;
        foreach ($rules as $rule) {
            if ($rule->kind === 'activity') {
                $count = $this->activityCount($lead, $rule->target !== 'any' ? $rule->target : null);
                if ($this->matches((string) $count, $rule->operator, $rule->match_value)) {
                    $total += (int) $rule->weight;
                }
                continue;
            }
            $value = $lead->{$rule->target} ?? null;
            if ($this->matches($value === null ? '' : (string) $value, $rule->operator, $rule->match_value)) {
                $total += (int) $rule->weight;
            }
        }

        return $total;
    }

    private function matches(string $actual, string $operator, ?string $expected): bool
    {
        return match ($operator) {
            'present' => trim($actual) !== '',
            'eq' => $actual === (string) $expected,
            'gte' => is_numeric($actual) && (float) $actual >= (float) $expected,
            'contains' => $expected !== null && $expected !== '' && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            default => false,
        };
    }

    private function activityBonus(CrmLead $lead): int
    {
        return min(20, $this->activityCount($lead, null) * 5);
    }

    private function activityCount(CrmLead $lead, ?string $type): int
    {
        $query = CrmActivity::query()
            ->where('related_id', $lead->id)
            ->whereIn('related_model', CrmRelation::storedNames('lead'))
            ->whereNotNull('completed_at')
            ->whereIn('type', ['call', 'meeting', 'email']);
        if ($type) {
            $query->where('type', $type);
        }

        return (int) $query->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CrmScoreRule>|null
     */
    private function activeRules()
    {
        if (! Schema::hasTable('crm_score_rules')) {
            return null;
        }
        $rules = CrmScoreRule::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $rules->isEmpty() ? null : $rules;
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
