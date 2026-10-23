<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;

/**
 * Reversible customer suspension: licenses + services + tenant sites.
 * Previous statuses are kept in each row's meta.suspension so reactivation restores them exactly.
 */
class CustomerSuspension
{
    public function __construct(private SiteLinkClient $client, private CustomerSiteSync $sync) {}

    /** @return array{licenses:int,services:int,sites:array<int,array{domain:string,ok:bool}>} */
    public function suspend(CrmAccount $a, string $reason, ?int $userId): array
    {
        $at = now()->toIso8601String();
        $res = DB::transaction(function () use ($a, $reason, $userId, $at) {
            $a->update(['suspended_at' => now(), 'hub_status' => 'blocked']);

            return [
                'licenses' => $this->mark('core_licenses', 'crm_account_id', $a->id, $at, $userId, $reason),
                'services' => $this->mark('crm_customer_services', 'account_id', $a->id, $at, $userId, $reason),
            ];
        });
        $res['sites'] = $this->pushSites($a, true, $reason);

        return $res;
    }

    public function activate(CrmAccount $a): array
    {
        $res = DB::transaction(function () use ($a) {
            $a->update(['suspended_at' => null, 'hub_status' => 'active']);

            return [
                'licenses' => $this->restore('core_licenses', 'crm_account_id', $a->id),
                'services' => $this->restore('crm_customer_services', 'account_id', $a->id),
            ];
        });
        $res['sites'] = $this->pushSites($a, false, null);

        return $res;
    }

    private function mark(string $table, string $fk, int $accountId, string $at, ?int $userId, string $reason): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
            return 0;
        }
        $hasMeta = Schema::hasColumn($table, 'meta');
        $n = 0;
        foreach (DB::table($table)->where($fk, $accountId)->where('status', '!=', 'suspended')->get() as $row) {
            $upd = ['status' => 'suspended'];
            if ($hasMeta) {
                $meta = json_decode((string) ($row->meta ?? ''), true) ?: [];
                $meta['suspension'] = ['previous_status' => $row->status, 'at' => $at, 'by' => $userId, 'reason' => $reason];
                $upd['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $upd['updated_at'] = now();
            }
            $n += DB::table($table)->where('id', $row->id)->update($upd);
        }

        return $n;
    }

    private function restore(string $table, string $fk, int $accountId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status') || ! Schema::hasColumn($table, 'meta')) {
            return 0;
        }
        $n = 0;
        foreach (DB::table($table)->where($fk, $accountId)->where('status', 'suspended')->get() as $row) {
            $meta = json_decode((string) ($row->meta ?? ''), true) ?: [];
            if (! isset($meta['suspension']['previous_status'])) {
                continue; // suspended for another reason — not ours to restore
            }
            $prev = $meta['suspension']['previous_status'];
            unset($meta['suspension']);
            $upd = ['status' => $prev, 'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null];
            if (Schema::hasColumn($table, 'updated_at')) {
                $upd['updated_at'] = now();
            }
            $n += DB::table($table)->where('id', $row->id)->update($upd);
        }

        return $n;
    }

    private function pushSites(CrmAccount $a, bool $suspended, ?string $reason): array
    {
        $out = [];
        foreach ($this->client->sites($a) as $site) {
            // Failures stay queued in crm_customer_site_links and are retried with backoff by crm:hub-sync-sites.
            $out[] = ['domain' => $site->domain, 'ok' => $this->sync->pushSuspension($a, $site, $suspended, null, $reason)];
        }

        return $out;
    }
}
