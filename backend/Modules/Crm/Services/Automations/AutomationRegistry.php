<?php

namespace Modules\Crm\Services\Automations;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\WorkflowDefinition;

/**
 * Customer 360 automations (phase 4). One row per rule in core_workflows (trigger_key "customer360.{key}"),
 * toggleable per install. Rules that send SMS or suspend customers are OFF by default.
 */
class AutomationRegistry
{
    public const PREFIX = 'customer360.';

    public const DEFS = [
        'renewal_reminder' => ['name' => 'یادآوری تمدید دامنه/سرویس', 'sends_sms' => true, 'suspends' => false,
            'config' => ['days' => [30, 7, 1], 'sms' => true, 'task' => true,
                'template' => '{{name}} عزیز، {{item}} شما در تاریخ {{date}} منقضی می‌شود. لطفاً برای تمدید اقدام کنید.']],
        'installment_reminder' => ['name' => 'یادآوری اقساط قرارداد', 'sends_sms' => true, 'suspends' => false,
            'config' => ['days_before' => [3, 0], 'overdue_every_days' => 7, 'template' => '{{name}} عزیز، قسط {{amount}} ریال قرارداد شما سررسید {{date}} است.']],
        'debtor_status' => ['name' => 'وضعیت بدهکار خودکار', 'sends_sms' => false, 'suspends' => false,
            'config' => ['overdue_days' => 0, 'min_amount' => 0, 'restore' => true]],
        'auto_suspend' => ['name' => 'تعلیق خودکار بدهکاران', 'sends_sms' => false, 'suspends' => true,
            'config' => ['overdue_days' => 60, 'min_amount' => 0]],
        'onboarding' => ['name' => 'آنبوردینگ پس از امضای قرارداد', 'sends_sms' => false, 'suspends' => false,
            'config' => ['items' => ['جلسه آغاز پروژه', 'دریافت محتوا و دسترسی‌ها', 'راه‌اندازی سایت', 'آموزش پنل', 'تحویل نهایی'], 'task_days' => 2]],
        'churn_risk' => ['name' => 'هشدار ریسک ریزش', 'sends_sms' => false, 'suspends' => false,
            'config' => ['health_below' => 40, 'task' => true]],
        'document_expiry' => ['name' => 'هشدار انقضای مدارک (اینماد و ...)', 'sends_sms' => false, 'suspends' => false,
            'config' => ['days' => 30]],
        'welcome_sms' => ['name' => 'پیامک خوش‌آمد مشتری جدید', 'sends_sms' => true, 'suspends' => false,
            'config' => ['template' => '{{name}} عزیز، به وبینو خوش آمدید.']],
    ];

    /** @var array<string, WorkflowDefinition>|null */
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return array<string, WorkflowDefinition> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        if (! Schema::hasTable('core_workflows')) {
            return [];
        }
        $rows = WorkflowDefinition::query()->where('trigger_key', 'like', self::PREFIX.'%')->get()->keyBy('trigger_key');
        $out = [];
        foreach (self::DEFS as $key => $d) {
            $w = $rows->get(self::PREFIX.$key);
            if (! $w) {
                $on = ! $d['sends_sms'] && ! $d['suspends'];
                $w = WorkflowDefinition::query()->create(['name' => $d['name'], 'trigger_key' => self::PREFIX.$key,
                    'status' => $on ? 'active' : 'inactive', 'graph' => ['customer360' => true, 'config' => $d['config'], 'last_run_at' => null]]);
            }
            $out[$key] = $w;
        }

        return self::$cache = $out;
    }

    public static function enabled(string $key): bool
    {
        return (self::all()[$key] ?? null)?->status === 'active';
    }

    public static function config(string $key): array
    {
        $w = self::all()[$key] ?? null;

        return array_replace(self::DEFS[$key]['config'] ?? [], (array) ($w?->graph['config'] ?? []));
    }

    public static function touch(string $key, array $stats): void
    {
        if ($w = self::all()[$key] ?? null) {
            $g = (array) $w->graph;
            $g['last_run_at'] = now()->toIso8601String();
            $g['last_stats'] = $stats;
            $w->update(['graph' => $g]);
        }
    }

    public static function row(string $key): array
    {
        $w = self::all()[$key];
        $d = self::DEFS[$key];

        return ['id' => $w->id, 'key' => $key, 'name' => $w->name, 'trigger_key' => $w->trigger_key, 'enabled' => $w->status === 'active',
            'sends_sms' => $d['sends_sms'], 'suspends' => $d['suspends'], 'config' => self::config($key),
            'last_run_at' => $w->graph['last_run_at'] ?? null, 'last_stats' => $w->graph['last_stats'] ?? null];
    }
}
