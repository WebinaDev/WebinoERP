<?php

namespace Modules\Crm\Services\Hub;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Crm\Entities\CrmAccount;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteStatusService;

/**
 * Compact Customer 360 summary of a site's owner, for the site control panel.
 * Every block is gated by the same CustomerHubAccess sections as the full hub.
 */
class SiteCustomerSummary
{
    public function __construct(private CustomerHubService $hub, private CustomerHubAccess $access) {}

    public function build(WebinoSiteProvision $p, User $u): array
    {
        $sections = $this->access->sections($u);
        $base = [
            'provision' => ['id' => $p->id, 'domain' => $p->domain, 'status' => $p->status, 'license_id' => $p->license_id],
            'site_status' => app(SiteStatusService::class)->snapshot($p),
            'sections' => $sections,
            'actions' => $this->access->actions($u),
            'can_link' => $this->access->canManage($u, 'profile'),
        ];
        $a = $p->crm_account_id ? CrmAccount::query()->find($p->crm_account_id) : null;
        if (! $a) {
            return $base + ['linked' => false];
        }
        if ($sections === []) {
            return $base + ['linked' => true, 'customer' => ['id' => $a->id, 'name' => $a->name], 'restricted' => true];
        }
        $can = fn (string $s) => in_array($s, $sections, true);
        $finance = $can('billing');
        $support = $can('support');

        $out = $base + [
            'linked' => true,
            'restricted' => false,
            'customer' => ['id' => $a->id, 'name' => $a->name],
            'header' => $this->hub->header($a),
            'kpis' => $this->hub->kpis($a, $finance),
            'health' => $can('analytics') || $finance ? $this->hub->health($a) : null,
            'alerts' => array_values(array_filter($this->hub->alerts($a), fn ($x) => $finance || ($x['code'] ?? '') !== 'overdue_debt')),
        ];
        if ($can('identity')) {
            $id = $this->hub->identity($a);
            $out['identity'] = ['profile' => $id['profile'], 'kyc' => $id['kyc'], 'contacts' => collect($id['contacts'])->take(3)->values()];
        }
        if ($can('licenses')) {
            $out['licenses'] = $this->hub->licenses($a)->take(10)->map(fn ($l) => [
                'id' => $l['id'], 'product' => $l['product'], 'domain' => $l['domain'], 'type' => $l['type'], 'expires_at' => $l['expires_at'],
                'days_left' => $l['days_left'], 'effective_status' => $l['effective_status'], 'key_masked' => $l['key_masked'],
                'near_limit' => $l['near_limit'], 'this_site' => $l['id'] === (int) $p->license_id || ($p->domain && $l['domain'] === $p->domain),
            ])->values();
        }
        if ($can('assets')) {
            $as = $this->hub->assets($a);
            $out['assets'] = [
                'sites' => collect($as['sites'])->map(fn ($s) => [
                    'provision_id' => $s['provision_id'] ?? null, 'domain' => $s['domain'], 'status' => $s['status'], 'panel_url' => $s['panel_url'] ?? null,
                    'ssl' => $s['ssl'] ?? null, 'server' => $s['server'] ?? null, 'this_site' => ($s['provision_id'] ?? null) === $p->id,
                ])->values(),
                'domains' => collect($as['domains'])->take(10)->map(fn ($d) => [
                    'domain' => $d['domain'] ?? null, 'expires_at' => $d['expires_at'] ?? null, 'auto_renew' => (bool) ($d['auto_renew'] ?? false), 'registrar' => $d['registrar'] ?? null,
                ])->values(),
                'hosting' => collect($as['hosting'])->map(fn ($h) => ['kind' => $h->kind ?? null, 'name' => $h->name ?? $h->title ?? null, 'status' => $h->status ?? null, 'expires_at' => $h->expires_at ?? null])->values(),
                'integrations_count' => count($as['integrations']),
            ];
        }
        if ($finance) {
            $b = $this->hub->billing($a);
            $s = $b['summary'];
            $out['billing'] = [
                'outstanding' => $s['outstanding'], 'overdue' => $s['overdue'], 'overdue_count' => $s['overdue_count'], 'wallet_credit' => $s['wallet_credit'],
                'sms_credit' => $s['sms_credit'], 'balance' => $s['balance'], 'lifetime_revenue' => $s['lifetime_revenue'], 'revenue_12m' => $s['revenue_12m'],
                'next_installment' => $b['installments']['next_due'],
                'recent_invoices' => $this->hub->invoices($a)->take(5)->map(fn ($i) => [
                    'id' => $i->id, 'number' => $i->invoice_number ?? $i->number ?? null, 'total' => (float) ($i->total ?? 0),
                    'status' => $i->status, 'issue_date' => $i->issue_date ?? null, 'due_date' => $i->due_date ?? null,
                ])->values(),
                'recent_payments' => $this->hub->payments($a)->take(5)->map(fn ($x) => [
                    'id' => $x['id'], 'date' => $x['date'], 'amount' => $x['amount'], 'gateway' => $x['gateway'], 'status' => $x['status'], 'paid' => $x['paid'],
                ])->values(),
            ];
        }
        if ($support) {
            $tk = $this->hub->tickets($a);
            $out['support'] = [
                'open' => $tk->where('is_open', true)->count(),
                'breached' => $tk->where('sla_breached', true)->count(),
                'total' => $tk->count(),
                'recent' => $tk->sortByDesc(fn ($t) => (int) $t->is_open)->take(5)->map(fn ($t) => [
                    'id' => $t->id, 'number' => $t->number ?? null, 'subject' => $t->subject, 'status' => $t->status,
                    'priority' => $t->priority ?? null, 'created_at' => $t->created_at, 'is_open' => $t->is_open, 'sla_breached' => $t->sla_breached,
                ])->values(),
            ];
        }
        if ($can('usage')) {
            $us = $this->hub->usage($a);
            unset($us['modules'], $us['sync']);
            $out['usage'] = $us;
        }
        if ($can('timeline')) {
            $out['timeline'] = $this->timeline($a, $finance, $support)->take(8)->values();
        }

        return $out;
    }

    private function timeline(CrmAccount $a, bool $finance, bool $support): Collection
    {
        $deny = array_merge($finance ? [] : ['payment', 'invoice'], $support ? [] : ['ticket']);

        return $this->hub->timeline($a)->reject(fn ($e) => in_array($e['kind'], $deny, true));
    }
}
