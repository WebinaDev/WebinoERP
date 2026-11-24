<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Entities\PlatformAlertRule;

class PlatformAlertRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name' => 'High CPU Usage',
                'scope_type' => 'global',
                'scope_id' => null,
                'metric_key' => 'cpu.usage',
                'operator' => 'gt',
                'threshold' => 90.0,
                'duration_seconds' => 600, // 10 minutes
                'severity' => 'warning',
                'is_enabled' => true,
            ],
            [
                'name' => 'High Memory Usage',
                'scope_type' => 'global',
                'scope_id' => null,
                'metric_key' => 'mem.usage_percent',
                'operator' => 'gt',
                'threshold' => 90.0,
                'duration_seconds' => 300, // 5 minutes
                'severity' => 'warning',
                'is_enabled' => true,
            ],
            [
                'name' => 'High Disk Usage Warning',
                'scope_type' => 'global',
                'scope_id' => null,
                'metric_key' => 'disk.usage_percent',
                'operator' => 'gt',
                'threshold' => 85.0,
                'duration_seconds' => 300, // 5 minutes
                'severity' => 'warning',
                'is_enabled' => true,
            ],
            [
                'name' => 'Critical Disk Space Depletion',
                'scope_type' => 'global',
                'scope_id' => null,
                'metric_key' => 'disk.usage_percent',
                'operator' => 'gt',
                'threshold' => 95.0,
                'duration_seconds' => 60, // 1 minute
                'severity' => 'critical',
                'is_enabled' => true,
            ],
            [
                'name' => 'High Inode Usage',
                'scope_type' => 'global',
                'scope_id' => null,
                'metric_key' => 'inode.usage_percent',
                'operator' => 'gt',
                'threshold' => 85.0,
                'duration_seconds' => 300,
                'severity' => 'warning',
                'is_enabled' => true,
            ],
        ];

        foreach ($rules as $rule) {
            PlatformAlertRule::query()->firstOrCreate(
                [
                    'name' => $rule['name'],
                    'metric_key' => $rule['metric_key'],
                    'operator' => $rule['operator'],
                    'threshold' => $rule['threshold'],
                ],
                $rule
            );
        }
    }
}
