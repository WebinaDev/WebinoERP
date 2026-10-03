<?php

namespace Modules\Integrations\Services;

use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\SmsRule;

class SmsPriorityGate
{
    public const RANKS = [
        'low' => 1,
        'normal' => 2,
        'high' => 3,
        'urgent' => 4,
    ];

    /**
     * @return array{send: bool, reason: string, mode: string, floor: string}
     */
    public function decide(string $priority, string $eventKey = '*'): array
    {
        $mode = IntegrationSetting::getString('sms_policy', 'mode', 'highest_only');
        $floor = IntegrationSetting::getString('sms_policy', 'floor', 'high');
        if (! isset(self::RANKS[$mode === 'off' ? 'low' : $priority])) {
            $priority = 'normal';
        }
        if ($mode === 'off') {
            return ['send' => false, 'reason' => 'sms_disabled', 'mode' => $mode, 'floor' => $floor];
        }

        $rank = self::RANKS[$priority] ?? self::RANKS['normal'];
        $floorRank = self::RANKS[$floor] ?? self::RANKS['high'];

        $rules = SmsRule::query()->where('enabled', true)->get();
        $matched = $rules->filter(function (SmsRule $rule) use ($eventKey) {
            return $rule->event_key === '*' || $rule->event_key === $eventKey;
        });

        if ($matched->isEmpty()) {
            $allowed = $mode === 'highest_only' ? $rank >= $floorRank : true;

            return [
                'send' => $allowed,
                'reason' => $allowed ? 'default_floor' : 'below_floor',
                'mode' => $mode,
                'floor' => $floor,
            ];
        }

        $rule = $matched->sortByDesc(fn (SmsRule $rule) => self::RANKS[$rule->min_priority] ?? 0)->first();
        $min = self::RANKS[$rule->min_priority] ?? $floorRank;
        if ($mode === 'highest_only') {
            $min = max($min, $floorRank);
        }
        $send = $rank >= $min;

        return [
            'send' => $send,
            'reason' => $send ? 'rule_match' : 'below_rule',
            'mode' => $mode,
            'floor' => $floor,
        ];
    }
}
