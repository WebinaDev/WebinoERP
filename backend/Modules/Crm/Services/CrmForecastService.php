<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmQuota;

class CrmForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function report(?string $from = null, ?string $to = null): array
    {
        $open = CrmDeal::query()->whereNull('won_at')->whereNull('lost_at');
        $deals = (clone $open)->with('stage')->get();

        $weighted = 0.0;
        $pipelineAmount = 0.0;
        $byStage = [];
        $byOwner = [];

        foreach ($deals as $deal) {
            $amount = (float) $deal->amount;
            $probability = (int) ($deal->probability ?: ($deal->stage->probability ?? 0));
            $weight = round($amount * ($probability / 100), 2);
            $weighted += $weight;
            $pipelineAmount += $amount;
            $stageName = $deal->stage->name ?? 'stage';
            $stageId = (int) $deal->stage_id;
            if (! isset($byStage[$stageId])) {
                $byStage[$stageId] = [
                    'stage_id' => $stageId,
                    'name' => $stageName,
                    'count' => 0,
                    'amount' => 0,
                    'weighted' => 0,
                ];
            }
            $byStage[$stageId]['count']++;
            $byStage[$stageId]['amount'] += $amount;
            $byStage[$stageId]['weighted'] += $weight;

            $owner = (int) ($deal->assigned_to ?? 0);
            if (! isset($byOwner[$owner])) {
                $byOwner[$owner] = ['user_id' => $owner ?: null, 'amount' => 0, 'weighted' => 0, 'count' => 0];
            }
            $byOwner[$owner]['amount'] += $amount;
            $byOwner[$owner]['weighted'] += $weight;
            $byOwner[$owner]['count']++;
        }

        $won = CrmDeal::query()->whereNotNull('won_at');
        $lost = CrmDeal::query()->whereNotNull('lost_at');
        if ($from) {
            $won->where('won_at', '>=', $from);
            $lost->where('lost_at', '>=', $from);
        }
        if ($to) {
            $won->where('won_at', '<=', $to.' 23:59:59');
            $lost->where('lost_at', '<=', $to.' 23:59:59');
        }

        $lossReasons = (clone $lost)
            ->select('loss_reason', DB::raw('count(*) as total'))
            ->groupBy('loss_reason')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'reason' => $row->loss_reason ?: '',
                'count' => (int) $row->total,
            ])
            ->values();

        $quotas = CrmQuota::query()->orderByDesc('period_start')->limit(50)->get();
        $attainment = $quotas->map(function (CrmQuota $quota) {
            $wonAmount = (float) CrmDeal::query()
                ->where('assigned_to', $quota->user_id)
                ->whereNotNull('won_at')
                ->when($quota->pipeline_id, fn ($q) => $q->where('pipeline_id', $quota->pipeline_id))
                ->whereBetween('won_at', [$quota->period_start->startOfDay(), $quota->period_end->endOfDay()])
                ->sum('amount');
            $target = (float) $quota->amount;

            return [
                'id' => $quota->id,
                'user_id' => $quota->user_id,
                'pipeline_id' => $quota->pipeline_id,
                'period_start' => $quota->period_start->toDateString(),
                'period_end' => $quota->period_end->toDateString(),
                'amount' => $target,
                'won_amount' => $wonAmount,
                'attainment' => $target > 0 ? round(($wonAmount / $target) * 100, 1) : 0,
            ];
        })->values();

        return [
            'pipeline_amount' => round($pipelineAmount, 2),
            'weighted_amount' => round($weighted, 2),
            'open_count' => $deals->count(),
            'won_amount' => round((float) (clone $won)->sum('amount'), 2),
            'won_count' => (clone $won)->count(),
            'lost_amount' => round((float) (clone $lost)->sum('amount'), 2),
            'lost_count' => (clone $lost)->count(),
            'funnel' => array_values($byStage),
            'by_owner' => array_values($byOwner),
            'loss_reasons' => $lossReasons,
            'quotas' => $attainment,
        ];
    }
}
