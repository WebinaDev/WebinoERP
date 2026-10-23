<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;

/**
 * Per-site background sync with exponential backoff:
 *  - usage metrics → crm_customer_usage_metrics (one row per metric/site/day)
 *  - pending suspension state → erp-link/suspension + provision/license-sync
 */
class CustomerSiteSync
{
    public const USAGE_EVERY_MINUTES = 360;

    public function __construct(private SiteLinkClient $client) {}

    public static function backoffMinutes(int $attempts): int
    {
        return (int) min(1440, 5 * (2 ** max(0, $attempts - 1)));
    }

    public function link(int $accountId, int $provisionId): object
    {
        $now = now();
        DB::table('crm_customer_site_links')->insertOrIgnore([
            'account_id' => $accountId, 'site_provision_id' => $provisionId, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('crm_customer_site_links')->where('account_id', $accountId)->where('site_provision_id', $provisionId)->first();
    }

    /** @return array{usage_ok:int,usage_failed:int,suspension_ok:int,suspension_failed:int,skipped:int} */
    public function run(?int $accountId = null, bool $force = false): array
    {
        $stats = ['usage_ok' => 0, 'usage_failed' => 0, 'suspension_ok' => 0, 'suspension_failed' => 0, 'skipped' => 0];
        if (! Schema::hasTable('webino_site_provisions') || ! Schema::hasTable('crm_customer_site_links')) {
            return $stats;
        }
        $q = CrmAccount::query()->whereIn('id', DB::table('webino_site_provisions')->whereNotNull('crm_account_id')->select('crm_account_id'));
        if ($accountId) {
            $q->whereKey($accountId);
        }
        foreach ($q->cursor() as $account) {
            foreach ($this->client->sites($account) as $site) {
                $link = $this->link($account->id, $site->id);
                if ($link->suspension_desired !== null && ($force || ! $link->suspension_next_at || now()->gte($link->suspension_next_at))) {
                    $this->pushSuspension($account, $site, (bool) $link->suspension_desired, $link) ? $stats['suspension_ok']++ : $stats['suspension_failed']++;
                }
                $due = $force || ((! $link->usage_next_at || now()->gte($link->usage_next_at))
                    && (! $link->usage_synced_at || now()->diffInMinutes($link->usage_synced_at, true) >= self::USAGE_EVERY_MINUTES));
                if (! $due) {
                    $stats['skipped']++;

                    continue;
                }
                $this->collectUsage($account, $site, $link) ? $stats['usage_ok']++ : $stats['usage_failed']++;
            }
        }

        return $stats;
    }

    public function collectUsage(CrmAccount $a, object $site, object $link): bool
    {
        try {
            $data = $this->client->call($site, 'usage', ['erp_customer_id' => $a->id]);
            $period = now()->toDateString();
            $now = now();
            DB::transaction(function () use ($a, $site, $data, $period, $now) {
                foreach ((array) ($data['metrics'] ?? []) as $metric => $value) {
                    if (! is_numeric($value)) {
                        continue;
                    }
                    DB::table('crm_customer_usage_metrics')->where(['account_id' => $a->id, 'site_provision_id' => $site->id, 'metric' => $metric, 'period' => $period])->delete();
                    DB::table('crm_customer_usage_metrics')->insert(['account_id' => $a->id, 'site_provision_id' => $site->id, 'metric' => (string) $metric,
                        'value' => $value, 'period' => $period, 'created_at' => $now, 'updated_at' => $now]);
                }
            });
            DB::table('crm_customer_site_links')->where('id', $link->id)->update(['usage_synced_at' => $now, 'usage_attempts' => 0, 'usage_next_at' => null, 'usage_error' => null, 'updated_at' => $now]);

            return true;
        } catch (\Throwable $e) {
            $n = (int) $link->usage_attempts + 1;
            DB::table('crm_customer_site_links')->where('id', $link->id)->update(['usage_attempts' => $n,
                'usage_next_at' => now()->addMinutes(self::backoffMinutes($n)), 'usage_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);

            return false;
        }
    }

    public function pushSuspension(CrmAccount $a, object $site, bool $suspended, ?object $link = null, ?string $reason = null): bool
    {
        $link ??= $this->link($a->id, $site->id);
        try {
            $this->client->call($site, 'suspension', ['erp_customer_id' => $a->id, 'suspended' => $suspended, 'reason' => $reason]);
            try {
                // keep license entitlement in sync too (best effort)
                $this->client->callRaw($site, 'provision/license-sync', ['crm_account_id' => $a->id]);
            } catch (\Throwable) {
            }
            DB::table('crm_customer_site_links')->where('id', $link->id)->update(['suspension_desired' => null, 'suspension_synced_at' => now(),
                'suspension_attempts' => 0, 'suspension_next_at' => null, 'suspension_error' => null, 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            $n = (int) $link->suspension_attempts + 1;
            DB::table('crm_customer_site_links')->where('id', $link->id)->update(['suspension_desired' => $suspended, 'suspension_attempts' => $n,
                'suspension_next_at' => now()->addMinutes(self::backoffMinutes($n)), 'suspension_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);

            return false;
        }
    }
}
