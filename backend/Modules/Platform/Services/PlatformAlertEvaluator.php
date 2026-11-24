<?php

namespace Modules\Platform\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformServer;

class PlatformAlertEvaluator
{
    /**
     * Evaluate alert rules for a specific server (or globally).
     */
    public function evaluateServer(PlatformServer $server): void
    {
        $rules = PlatformAlertRule::query()
            ->where('is_enabled', true)
            ->where(function ($q) use ($server) {
                $q->where('scope_type', 'global')
                    ->orWhere(function ($q2) use ($server) {
                        $q2->where('scope_type', 'server')
                            ->where(function ($q3) use ($server) {
                                $q3->whereNull('scope_id')
                                    ->orWhere('scope_id', $server->id);
                            });
                    });
            })
            ->get();

        foreach ($rules as $rule) {
            $this->evaluateRuleForServer($rule, $server);
        }
    }

    protected function evaluateRuleForServer(PlatformAlertRule $rule, PlatformServer $server): void
    {
        $duration = max(1, (int) $rule->duration_seconds);
        $since = Carbon::now()->subSeconds($duration);

        // Fetch snapshots in the duration window
        $snapshots = PlatformMetricSnapshot::query()
            ->where('server_id', $server->id)
            ->where('metric_key', $rule->metric_key)
            ->where('collected_at', '>=', $since)
            ->orderBy('collected_at', 'asc')
            ->get();

        if ($snapshots->isEmpty()) {
            return;
        }

        // Check if all snapshots in window breach the threshold
        $breached = true;
        $lastValue = 0.0;
        foreach ($snapshots as $snap) {
            $lastValue = (float) $snap->value;
            if (! $this->isBreached($lastValue, $rule->operator, (float) $rule->threshold)) {
                $breached = false;
                break;
            }
        }

        // Check if there is already an open or acknowledged event
        $activeEvent = PlatformAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('server_id', $server->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if ($breached) {
            if (! $activeEvent) {
                // Open new alert event (deduped)
                PlatformAlertEvent::query()->create([
                    'rule_id' => $rule->id,
                    'server_id' => $server->id,
                    'status' => 'open',
                    'severity' => $rule->severity,
                    'message' => sprintf(
                        'هشدار: متریک %s روی سرور %s با مقدار %s از آستانه %s %s عبور کرد (مدت: %d ثانیه).',
                        $rule->metric_key,
                        $server->name,
                        round($lastValue, 2),
                        $rule->operator,
                        round((float) $rule->threshold, 2),
                        $rule->duration_seconds
                    ),
                    'metric_value' => $lastValue,
                    'opened_at' => now(),
                    'meta' => [
                        'operator' => $rule->operator,
                        'threshold' => (float) $rule->threshold,
                        'duration_seconds' => $rule->duration_seconds,
                    ],
                ]);
            } else {
                // Update current metric value on existing event
                $activeEvent->update([
                    'metric_value' => $lastValue,
                ]);
            }
        } else {
            // Auto-resolve if condition is no longer breached
            if ($activeEvent) {
                $activeEvent->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
            }
        }
    }

    protected function isBreached(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            default => false,
        };
    }
}
