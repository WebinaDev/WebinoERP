<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CustomerActivity;

/** Single write path into crm_customer_activities (listeners, backfill, controllers). Idempotent on dedupe_key. */
class CustomerActivityRecorder
{
    /** module => hub section used for RBAC filtering of the timeline (null = everyone with timeline access). */
    public const SECTIONS = [
        'sales' => 'billing', 'accounting' => 'billing', 'billing' => 'billing',
        'tickets' => 'support', 'crm_deals' => 'sales', 'projects' => 'projects', 'contracts' => 'contracts',
        'docs' => 'documents', 'platform' => 'assets', 'sitebuilder' => 'assets', 'hosting' => 'assets',
        'licenses' => 'licenses', 'marketplace' => 'billing', 'basalam' => 'billing', 'sms' => 'support',
    ];

    public static function key(?string $subjectType, $subjectId, string $type): ?string
    {
        return $subjectType && $subjectId !== null && $subjectId !== '' ? mb_substr($subjectType.':'.$subjectId.':'.$type, 0, 191) : null;
    }

    /**
     * @param  array{account_id:int, module:string, type:string, title:string, occurred_at?:mixed, subject_type?:?string, subject_id?:mixed,
     *               meta?:array, actor_id?:?int, visibility_section?:?string, dedupe_key?:?string}  $a
     */
    public function record(array $a): ?CustomerActivity
    {
        if (empty($a['account_id']) || ! DB::table('crm_accounts')->where('id', $a['account_id'])->exists()) {
            return null;
        }
        $at = isset($a['occurred_at']) && $a['occurred_at'] ? Carbon::parse($a['occurred_at']) : now();
        $subjectId = isset($a['subject_id']) && is_numeric($a['subject_id']) ? (int) $a['subject_id'] : null;
        $key = array_key_exists('dedupe_key', $a) ? $a['dedupe_key'] : self::key($a['subject_type'] ?? null, $subjectId, $a['type']);
        if ($key && ($existing = CustomerActivity::query()->where('dedupe_key', $key)->first())) {
            return $existing;
        }
        $row = [
            'account_id' => (int) $a['account_id'], 'occurred_at' => $at, 'module' => $a['module'], 'type' => mb_substr($a['type'], 0, 60),
            'subject_type' => $a['subject_type'] ?? null, 'subject_id' => $subjectId, 'title' => mb_substr((string) ($a['title'] ?: $a['type']), 0, 255),
            'meta' => isset($a['meta']) && $a['meta'] ? json_encode($a['meta'], JSON_UNESCAPED_UNICODE) : null,
            'actor_id' => $a['actor_id'] ?? null,
            'visibility_section' => array_key_exists('visibility_section', $a) ? $a['visibility_section'] : (self::SECTIONS[$a['module']] ?? null),
            'dedupe_key' => $key, 'created_at' => now(), 'updated_at' => now(),
        ];
        if ($key) {
            DB::table('crm_customer_activities')->insertOrIgnore($row); // race-safe with the unique index
            $model = CustomerActivity::query()->where('dedupe_key', $key)->first();
        } else {
            $model = CustomerActivity::query()->find(DB::table('crm_customer_activities')->insertGetId($row));
        }
        DB::table('crm_accounts')->where('id', $row['account_id'])
            ->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $at))
            ->update(['last_activity_at' => $at]);

        return $model;
    }
}
