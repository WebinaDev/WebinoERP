<?php

namespace Modules\Crm\Services;

use Modules\Crm\Entities\CrmCampaignSpend;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmTouchpoint;

class AttributionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): CrmTouchpoint
    {
        $data['occurred_at'] = $data['occurred_at'] ?? now();

        return CrmTouchpoint::query()->create($data);
    }

    /**
     * @return list<float>
     */
    public function weights(int $count, string $model): array
    {
        if ($count <= 0) {
            return [];
        }
        if ($count === 1 || $model === 'last') {
            $weights = array_fill(0, $count, 0.0);
            $weights[$model === 'first' ? 0 : $count - 1] = 100.0;
            if ($model !== 'first' && $model !== 'last' && $count === 1) {
                $weights[0] = 100.0;
            }

            return $weights;
        }
        if ($model === 'first') {
            $weights = array_fill(0, $count, 0.0);
            $weights[0] = 100.0;

            return $weights;
        }
        if ($model === 'position') {
            if ($count === 2) {
                return [50.0, 50.0];
            }
            $middle = round(20 / ($count - 2), 4);
            $weights = array_fill(0, $count, $middle);
            $weights[0] = 40.0;
            $weights[$count - 1] = 40.0;

            return $weights;
        }
        $share = round(100 / $count, 4);
        $weights = array_fill(0, $count, $share);
        $weights[$count - 1] = round(100 - ($share * ($count - 1)), 4);

        return $weights;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roi(string $model = 'linear'): array
    {
        $model = in_array($model, ['linear', 'first', 'last', 'position'], true) ? $model : 'linear';
        $credit = [];
        $deals = CrmDeal::query()->whereNotNull('won_at')->get();
        foreach ($deals as $deal) {
            $touches = CrmTouchpoint::query()
                ->where('deal_id', $deal->id)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get();
            if ($touches->isEmpty() && $deal->campaign_source) {
                $key = (string) $deal->campaign_source;
                $credit[$key] = ($credit[$key] ?? 0) + (float) $deal->amount;
                continue;
            }
            $weights = $this->weights($touches->count(), $model);
            foreach ($touches->values() as $index => $touch) {
                $key = (string) ($touch->utm_campaign ?: 'direct');
                $credit[$key] = ($credit[$key] ?? 0) + ((float) $deal->amount * (($weights[$index] ?? 0) / 100));
            }
        }
        $spends = CrmCampaignSpend::query()->get()->groupBy('campaign_key');
        $keys = collect(array_keys($credit))->merge($spends->keys())->unique();
        $rows = [];
        foreach ($keys as $key) {
            $revenue = round((float) ($credit[$key] ?? 0), 2);
            $spent = round((float) ($spends->get($key)?->sum('spent') ?? 0), 2);
            $rows[] = [
                'campaign' => (string) $key,
                'model' => $model,
                'revenue' => $revenue,
                'spent' => $spent,
                'roi' => $spent > 0 ? round(($revenue - $spent) / $spent, 4) : null,
            ];
        }

        return $rows;
    }
}
