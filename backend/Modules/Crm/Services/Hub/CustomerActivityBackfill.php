<?php

namespace Modules\Crm\Services\Hub;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;

/**
 * Builds crm_customer_activities for one account from the existing hub sources
 * (same data the old on-the-fly timeline used) plus deals/projects/contracts.
 * Dedupe keys match the live listeners, so re-running is a no-op.
 */
class CustomerActivityBackfill
{
    /** legacy timeline ref prefix => [subject_type, module] */
    private const REFS = [
        'inv' => ['sales_invoice', 'sales'], 'tk' => ['prj_ticket', 'tickets'], 'prov' => ['site_provision', 'sitebuilder'],
        'lic' => ['core_license', 'licenses'], 'audit' => ['crm_customer_audit', 'crm'], 'act' => ['crm_activity', 'crm'],
        'pi' => ['payment', 'accounting'], 'mo' => ['marketplace_order', 'marketplace'], 'mp' => ['basalam_payment', 'basalam'],
    ];

    /** legacy timeline kind => activity type */
    private const TYPES = [
        'invoice' => 'invoice.issued', 'ticket' => 'ticket.opened', 'site' => 'site.provisioned', 'deploy' => 'site.deployed',
        'license' => 'license.issued', 'license_renewal' => 'license.renewed', 'payment' => 'payment.recorded', 'login' => 'portal.login',
    ];

    public function __construct(private CustomerActivityRecorder $recorder) {}

    public function forAccount(CrmAccount $a): int
    {
        $before = DB::table('crm_customer_activities')->where('account_id', $a->id)->count();
        $hub = app()->make(CustomerHubService::class); // fresh instance: its memo cache is per-account

        foreach ($hub->timeline($a) as $e) {
            [$prefix, $id] = array_pad(explode('-', (string) ($e['ref'] ?? ''), 2), 2, null);
            [$subject, $module] = self::REFS[$prefix] ?? [null, 'crm'];
            $type = match (true) {
                $e['kind'] === 'audit' => 'hub.'.$e['title'],
                $prefix === 'act' => 'comm.'.$e['kind'],
                default => self::TYPES[$e['kind']] ?? $e['kind'],
            };
            if ($type === 'license.renewed') {
                $id = $id.'@'.substr((string) $e['date'], 0, 19);
            }
            $key = match (true) {
                $subject && $id !== null && $id !== '' => CustomerActivityRecorder::key($subject, $id, $type),
                $type === 'portal.login' => 'portal.login:'.$a->id.':'.md5($e['title'].$e['date']),
                default => null,
            };
            $this->recorder->record([
                'account_id' => $a->id, 'module' => $module, 'type' => $type, 'title' => (string) $e['title'], 'occurred_at' => $e['date'],
                'subject_type' => $subject, 'subject_id' => is_numeric($id) ? (int) $id : null, 'dedupe_key' => $key,
                'meta' => array_filter(['status' => $e['status'] ?? null, 'ref' => $e['ref'] ?? null]),
            ]);
            if ($e['kind'] === 'invoice' && ($e['status'] ?? null) === 'paid') {
                $this->recorder->record(['account_id' => $a->id, 'module' => 'sales', 'type' => 'invoice.paid', 'title' => 'پرداخت '.$e['title'],
                    'occurred_at' => $e['date'], 'subject_type' => 'sales_invoice', 'subject_id' => (int) $id]);
            }
        }

        if (Schema::hasTable('crm_deals')) {
            foreach (DB::table('crm_deals')->where('account_id', $a->id)->when(Schema::hasColumn('crm_deals', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->get() as $d) {
                $this->deal($a->id, $d, 'deal.created', 'معامله جدید: '.$d->name, $d->created_at);
                if ($d->won_at ?? null) {
                    $this->deal($a->id, $d, 'deal.won', 'معامله برنده شد: '.$d->name, $d->won_at);
                }
                if ($d->lost_at ?? null) {
                    $this->deal($a->id, $d, 'deal.lost', 'معامله از دست رفت: '.$d->name, $d->lost_at);
                }
            }
        }
        if (Schema::hasTable('prj_projects')) {
            foreach (DB::table('prj_projects')->where('customer_account_id', $a->id)->get() as $p) {
                $this->recorder->record(['account_id' => $a->id, 'module' => 'projects', 'type' => 'project.created', 'title' => 'ایجاد پروژه: '.$p->name,
                    'occurred_at' => $p->created_at, 'subject_type' => 'prj_project', 'subject_id' => $p->id, 'meta' => ['status' => $p->status]]);
            }
        }
        if (Schema::hasTable('prj_contracts') && Schema::hasColumn('prj_contracts', 'customer_account_id')) {
            foreach (DB::table('prj_contracts')->where('customer_account_id', $a->id)->get() as $c) {
                $title = $c->title ?? ('#'.$c->id);
                $this->recorder->record(['account_id' => $a->id, 'module' => 'contracts', 'type' => 'contract.created', 'title' => 'قرارداد: '.$title,
                    'occurred_at' => $c->created_at, 'subject_type' => 'prj_contract', 'subject_id' => $c->id, 'meta' => ['status' => $c->status ?? null]]);
                if ($c->signed_at ?? null) {
                    $this->recorder->record(['account_id' => $a->id, 'module' => 'contracts', 'type' => 'contract.signed', 'title' => 'امضای قرارداد: '.$title,
                        'occurred_at' => $c->signed_at, 'subject_type' => 'prj_contract', 'subject_id' => $c->id]);
                }
            }
        }

        DB::table('crm_accounts')->where('id', $a->id)->update(['activities_backfilled_at' => now()]);

        return DB::table('crm_customer_activities')->where('account_id', $a->id)->count() - $before;
    }

    private function deal(int $accountId, object $d, string $type, string $title, $at): void
    {
        $this->recorder->record(['account_id' => $accountId, 'module' => 'crm_deals', 'type' => $type, 'title' => $title, 'occurred_at' => $at,
            'subject_type' => 'crm_deal', 'subject_id' => $d->id, 'meta' => ['amount' => (float) ($d->amount ?? 0)]]);
    }
}
