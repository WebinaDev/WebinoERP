<?php

namespace Modules\Crm\Services\Hub;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;

/**
 * Customer 360 Hub aggregator. Reads (never writes) from the existing modules:
 * CRM, Sales, Projects, Accounting/Integrations (payments, wallets, SMS),
 * Core (licenses), SiteBuilder (provisions), Platform (resources/servers), Marketplace.
 * Every source is optional so a partially-installed ERP still renders the hub.
 */
class CustomerHubService
{
    public const PAID_INVOICE = ['paid'];

    public const CLOSED_INVOICE = ['paid', 'draft', 'cancelled', 'canceled', 'void'];

    public const PAID_INTENT = ['paid', 'verified', 'settled', 'succeeded', 'success'];

    public const OPEN_TICKET = ['open', 'pending', 'in_progress', 'waiting', 'answered', 'new'];

    /** @var array<string,bool> */
    private array $tableCache = [];

    /** @var array<int,array<string,mixed>> */
    private array $memo = [];

    public function has(string $table, ?string $column = null): bool
    {
        $k = $table.'.'.($column ?? '');
        if (! array_key_exists($k, $this->tableCache)) {
            try {
                $this->tableCache[$k] = $column ? Schema::hasColumn($table, $column) : Schema::hasTable($table);
            } catch (\Throwable) {
                $this->tableCache[$k] = false;
            }
        }

        return $this->tableCache[$k];
    }

    private function rows(string $table, callable $scope, int $limit = 500): Collection
    {
        if (! $this->has($table)) {
            return collect();
        }
        $q = DB::table($table);
        $scope($q);

        return $q->limit($limit)->get();
    }

    private function memo(int $id, string $key, callable $fn): mixed
    {
        if (! isset($this->memo[$id]) || ! array_key_exists($key, $this->memo[$id])) {
            $this->memo[$id][$key] = $fn();
        }

        return $this->memo[$id][$key];
    }

    // ───────────────────────── raw sources ─────────────────────────

    public function provisions(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'prov', fn () => $this->rows('webino_site_provisions', fn ($q) => $q->where('crm_account_id', $a->id)->orderByDesc('id')));
    }

    public function resources(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'res', fn () => $this->rows('platform_resources', fn ($q) => $q->where('crm_account_id', $a->id)->orderByDesc('id')));
    }

    public function customerDomains(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'cdom', fn () => $this->rows('crm_customer_domains', fn ($q) => $q->where('account_id', $a->id)->orderBy('domain')));
    }

    /** @return list<string> */
    public function domains(CrmAccount $a): array
    {
        return $this->memo($a->id, 'domains', function () use ($a) {
            $list = [];
            foreach ($this->provisions($a) as $p) {
                $list[] = $p->domain ?? null;
                if (! empty($p->subdomain) && ! str_contains((string) $p->subdomain, '.')) {
                    // bare subdomain label — not a hostname on its own
                } else {
                    $list[] = $p->subdomain ?? null;
                }
            }
            foreach ($this->resources($a) as $r) {
                $list[] = $r->fqdn ? preg_replace('#^https?://#', '', (string) $r->fqdn) : null;
            }
            foreach ($this->customerDomains($a) as $d) {
                $list[] = $d->domain;
            }
            if ($this->has('core_licenses', 'crm_account_id')) {
                foreach (DB::table('core_licenses')->where('crm_account_id', $a->id)->pluck('domain') as $d) {
                    $list[] = $d;
                }
            }

            return array_values(array_unique(array_filter(array_map(fn ($d) => $d ? strtolower(trim((string) $d, " /")) : null, $list))));
        });
    }

    /** @return list<int> */
    public function portalUserIds(CrmAccount $a): array
    {
        return $this->memo($a->id, 'pu', fn () => $this->rows('crm_account_users', fn ($q) => $q->where('account_id', $a->id))->pluck('user_id')->map(fn ($v) => (int) $v)->all());
    }

    public function projectIds(CrmAccount $a): array
    {
        return $this->memo($a->id, 'pids', fn () => $this->rows('prj_projects', fn ($q) => $q->where('customer_account_id', $a->id))->pluck('id')->map(fn ($v) => (int) $v)->all());
    }

    public function invoices(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'inv', function () use ($a) {
            if (! $this->has('sales_invoices', 'customer_id')) {
                return collect();
            }
            $pids = $this->projectIds($a);

            return DB::table('sales_invoices')->where(function ($q) use ($a, $pids) {
                $q->where('customer_id', $a->id);
                if ($pids && $this->has('sales_invoices', 'project_id')) {
                    $q->orWhereIn('project_id', $pids);
                }
            })->orderByDesc('issue_date')->orderByDesc('id')->limit(2000)->get()->map(function ($r) {
                $r->items = is_string($r->items ?? null) ? json_decode($r->items, true) : ($r->items ?? null);

                return $r;
            });
        });
    }

    public function payments(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'pay', function () use ($a) {
            $out = collect();
            if ($this->has('payment_intents')) {
                $invIds = $this->invoices($a)->pluck('id')->map(fn ($v) => (string) $v)->all();
                $domains = $this->domains($a);
                $users = $this->portalUserIds($a);
                if ($invIds || $domains || $users) {
                    $rows = DB::table('payment_intents')->where(function ($q) use ($invIds, $domains, $users) {
                        if ($invIds) {
                            $q->orWhere(fn ($w) => $w->where('payable_type', 'like', '%nvoice%')->whereIn('payable_id', $invIds));
                        }
                        if ($domains) {
                            $q->orWhereIn('domain', $domains);
                        }
                        if ($users) {
                            $q->orWhereIn('user_id', $users);
                        }
                    })->orderByDesc('id')->limit(2000)->get();
                    foreach ($rows as $r) {
                        $out->push([
                            'source' => 'gateway',
                            'id' => 'pi-'.$r->id,
                            'date' => $r->settled_at ?? $r->verified_at ?? $r->created_at,
                            'amount' => (float) ($r->total_amount ?? $r->base_amount ?? 0),
                            'gateway' => $r->gateway,
                            'tracking_code' => $r->provider_ref ?: ($r->provider_tx ?: $r->authority),
                            'status' => $r->status,
                            'paid' => in_array($r->status, self::PAID_INTENT, true),
                            'invoice_id' => str_contains((string) $r->payable_type, 'nvoice') ? (int) $r->payable_id : null,
                            'domain' => $r->domain,
                        ]);
                    }
                }
            }
            foreach ($this->rows('marketplace_orders', fn ($q) => $q->where('crm_account_id', $a->id)->orderByDesc('id')) as $o) {
                $out->push([
                    'source' => 'marketplace',
                    'id' => 'mo-'.$o->id,
                    'date' => $o->paid_at ?? $o->created_at,
                    'amount' => (float) ($o->total ?? 0),
                    'gateway' => $o->payment_gateway ?? null,
                    'tracking_code' => $o->payment_ref ?? null,
                    'status' => $o->status,
                    'paid' => ! empty($o->paid_at),
                    'invoice_id' => null,
                    'order_number' => $o->order_number ?? null,
                ]);
            }
            foreach ($this->rows('crm_activities', fn ($q) => $q->where('related_model', CrmAccount::class)->where('related_id', $a->id)->where('type', 'payment')->whereNull('deleted_at')) as $m) {
                $meta = $this->json($m->meta ?? null);
                $out->push([
                    'source' => 'manual',
                    'id' => 'mp-'.$m->id,
                    'date' => $m->completed_at ?? $m->created_at,
                    'amount' => (float) ($meta['amount'] ?? 0),
                    'gateway' => $meta['method'] ?? 'manual',
                    'tracking_code' => $meta['reference'] ?? null,
                    'status' => 'paid',
                    'paid' => true,
                    'invoice_id' => isset($meta['invoice_id']) ? (int) $meta['invoice_id'] : null,
                    'note' => $m->description,
                ]);
            }

            return $out->sortByDesc(fn ($p) => (string) $p['date'])->values();
        });
    }

    public function licenses(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'lic', function () use ($a) {
            if (! $this->has('core_licenses')) {
                return collect();
            }
            $ids = $this->provisions($a)->pluck('license_id')->filter()->merge($this->resources($a)->pluck('license_id')->filter())->unique()->values()->all();
            $domains = $this->domains($a);
            $rows = DB::table('core_licenses')->where(function ($q) use ($a, $ids, $domains) {
                $q->whereRaw('1 = 0');
                if ($this->has('core_licenses', 'crm_account_id')) {
                    $q->orWhere('crm_account_id', $a->id);
                }
                if ($ids) {
                    $q->orWhereIn('id', $ids);
                }
                if ($domains) {
                    $q->orWhereIn('domain', $domains);
                }
            })->orderBy('expires_at')->get();
            $now = CarbonImmutable::now();

            return $rows->map(function ($l) use ($now) {
                $exp = $l->expires_at ? CarbonImmutable::parse($l->expires_at) : null;
                $days = $exp ? (int) floor($now->diffInDays($exp, false)) : null;
                $limits = $this->json($l->usage_limits ?? null) ?: array_filter(['users' => $l->max_users ?? null]);
                $usage = $this->json($l->usage_current ?? null) ?: [];
                $near = [];
                foreach ($limits as $k => $cap) {
                    if (is_numeric($cap) && $cap > 0 && isset($usage[$k]) && $usage[$k] / $cap >= 0.8) {
                        $near[] = $k;
                    }
                }
                $status = (string) $l->status;
                $effective = in_array($status, ['suspended', 'revoked'], true) ? 'suspended'
                    : ($days !== null && $days < 0 ? 'expired' : ($days !== null && $days <= 30 ? 'expiring' : ($status ?: 'active')));
                $meta = $this->json($l->meta ?? null);
                $key = (string) ($l->license_key ?? '');

                return [
                    'id' => (int) $l->id,
                    'product' => $l->product ?? 'webino',
                    'project_name' => $l->project_name ?? null,
                    'domain' => $l->domain,
                    'type' => $l->license_type ?? ($meta['type'] ?? null),
                    'start_date' => $l->start_date ?? null,
                    'expires_at' => $l->expires_at,
                    'days_left' => $days,
                    'status' => $status,
                    'effective_status' => $effective,
                    'alert' => $days !== null && $days >= 0 ? ($days <= 7 ? 7 : ($days <= 14 ? 14 : ($days <= 30 ? 30 : null))) : null,
                    'limits' => $limits,
                    'usage' => $usage,
                    'near_limit' => $near,
                    'key_masked' => $key !== '' ? str_repeat('•', 6).substr($key, -4) : null,
                    'feature_flags' => $this->json($l->feature_flags ?? null) ?: ($meta['features'] ?? []),
                    'renewals' => $meta['renewals'] ?? [],
                ];
            })->values();
        });
    }

    public function tickets(CrmAccount $a): Collection
    {
        return $this->memo($a->id, 'tk', function () use ($a) {
            $users = $this->portalUserIds($a);

            return $this->rows('prj_tickets', function ($q) use ($a, $users) {
                $q->where(function ($w) use ($a, $users) {
                    $w->where('customer_account_id', $a->id);
                    if ($users) {
                        $w->orWhereIn('customer_user_id', $users);
                    }
                })->orderByDesc('id');
            }, 2000)->map(function ($t) {
                $now = now();
                $t->is_open = in_array((string) $t->status, self::OPEN_TICKET, true);
                $t->sla_breached = ! empty($t->sla_breached_at)
                    || ($t->is_open && ! empty($t->sla_resolve_due_at) && $now->greaterThan($t->sla_resolve_due_at));

                return $t;
            });
        });
    }

    private function json(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (is_string($v) && $v !== '') {
            $d = json_decode($v, true);

            return is_array($d) ? $d : [];
        }

        return [];
    }

    // ───────────────────────── derived ─────────────────────────

    public function financials(CrmAccount $a): array
    {
        $inv = $this->invoices($a);
        $paidInv = $inv->filter(fn ($i) => in_array($i->status, self::PAID_INVOICE, true));
        $openInv = $inv->reject(fn ($i) => in_array($i->status, self::CLOSED_INVOICE, true));
        $overdue = $openInv->filter(fn ($i) => $i->issue_date && CarbonImmutable::parse($i->issue_date)->lt(now()->subDays(30)));
        $payments = $this->payments($a)->where('paid', true);
        // Revenue = paid invoices + paid payments not tied to an invoice (avoid double-counting).
        $unlinked = $payments->filter(fn ($p) => empty($p['invoice_id']) || ! $inv->firstWhere('id', $p['invoice_id']));
        $lifetime = (float) $paidInv->sum('total') + (float) $unlinked->sum('amount');
        $since = now()->subMonths(12)->startOfDay();
        $last12 = (float) $paidInv->filter(fn ($i) => $i->issue_date && CarbonImmutable::parse($i->issue_date)->gte($since))->sum('total')
            + (float) $unlinked->filter(fn ($p) => $p['date'] && CarbonImmutable::parse($p['date'])->gte($since))->sum('amount');
        $wallets = $this->wallets($a);
        $ordersCount = $paidInv->count() + $unlinked->count();
        $methods = $payments->pluck('gateway')->filter()->countBy()->sortDesc();

        return [
            'lifetime_revenue' => round($lifetime, 2),
            'revenue_12m' => round($last12, 2),
            'outstanding' => round((float) $openInv->sum('total'), 2),
            'overdue' => round((float) $overdue->sum('total'), 2),
            'overdue_count' => $overdue->count(),
            'wallet_credit' => round((float) collect($wallets['general'])->sum('balance'), 2),
            'sms_credit' => round((float) collect($wallets['sms'])->sum('balance'), 2),
            'balance' => round((float) collect($wallets['general'])->sum('balance') - (float) $openInv->sum('total'), 2),
            'total_paid' => round((float) $payments->sum('amount'), 2),
            'aov' => $ordersCount > 0 ? round($lifetime / $ordersCount, 2) : 0,
            'preferred_payment_method' => $methods->keys()->first(),
            'last_payment' => $payments->first(),
            'credit_limit' => $a->credit_limit !== null ? (float) $a->credit_limit : null,
        ];
    }

    public function wallets(CrmAccount $a): array
    {
        $domains = $this->domains($a);
        if (! $domains) {
            return ['general' => [], 'sms' => []];
        }

        return [
            'general' => $this->rows('payment_wallets', fn ($q) => $q->whereIn('domain', $domains))->map(fn ($w) => ['domain' => $w->domain, 'balance' => (float) $w->balance])->values()->all(),
            'sms' => $this->rows('modirpayamak_accounts', fn ($q) => $q->whereIn('domain', $domains))->map(fn ($w) => ['domain' => $w->domain, 'balance' => (float) $w->balance, 'status' => $w->status ?? null, 'sender' => $w->default_from ?? null])->values()->all(),
        ];
    }

    public function walletLedger(CrmAccount $a): Collection
    {
        $domains = $this->domains($a);
        if (! $domains) {
            return collect();
        }
        $g = $this->rows('payment_wallet_ledgers', fn ($q) => $q->whereIn('domain', $domains)->orderByDesc('id'))->map(fn ($r) => [
            'wallet' => 'general', 'domain' => $r->domain, 'amount' => (float) $r->amount, 'balance_after' => (float) $r->balance_after, 'note' => $r->note ?? null, 'date' => $r->created_at,
        ]);
        $s = $this->rows('modirpayamak_balance_ledger', fn ($q) => $q->whereIn('domain', $domains)->orderByDesc('id'))->map(fn ($r) => [
            'wallet' => 'sms', 'domain' => $r->domain, 'amount' => (float) $r->amount, 'balance_after' => (float) $r->balance_after, 'note' => $r->type ?? null, 'date' => $r->created_at,
        ]);

        return $g->merge($s)->sortByDesc('date')->values();
    }

    public function lastActivityAt(CrmAccount $a): ?string
    {
        $c = collect([
            $this->rows('crm_activities', fn ($q) => $q->where('related_model', CrmAccount::class)->where('related_id', $a->id)->orderByDesc('created_at'), 1)->first()?->created_at,
            $this->tickets($a)->max('updated_at'),
            $this->invoices($a)->max('updated_at'),
            $this->payments($a)->max('date'),
            $this->lastLoginAt($a),
            $a->updated_at?->toDateTimeString(),
        ])->filter();

        return $c->max();
    }

    public function lastLoginAt(CrmAccount $a): ?string
    {
        $ids = $this->portalUserIds($a);
        if (! $ids || ! $this->has('users', 'last_login_at')) {
            return null;
        }

        return DB::table('users')->whereIn('id', $ids)->max('last_login_at');
    }

    public function health(CrmAccount $a): array
    {
        $fin = $this->financials($a);
        $tk = $this->tickets($a);
        $lic = $this->licenses($a);
        $billed = (float) $this->invoices($a)->reject(fn ($i) => in_array($i->status, ['draft', 'cancelled', 'canceled', 'void'], true))->sum('total');

        $payments = 30;
        if ($billed > 0) {
            $payments = (int) round(30 * (1 - min(1, ($fin['overdue'] * 1.0 + $fin['outstanding'] * 0.3) / $billed)));
        }
        if ($a->hub_status === 'debtor') {
            $payments = min($payments, 10);
        }

        $open = $tk->where('is_open', true)->count();
        $breached = $tk->where('sla_breached', true)->count();
        $support = max(0, 25 - 4 * $open - 8 * $breached);

        $activeSites = $this->provisions($a)->whereIn('status', ['ready', 'active', 'live'])->count() + $this->resources($a)->whereIn('status', ['running', 'active'])->count();
        $last = $this->lastActivityAt($a);
        $recent = $last && CarbonImmutable::parse($last)->gte(now()->subDays(30));
        $usage = ($activeSites > 0 ? 15 : ($this->provisions($a)->isNotEmpty() ? 8 : 5)) + ($recent ? 10 : ($last && CarbonImmutable::parse($last)->gte(now()->subDays(90)) ? 5 : 0));

        $expired = $lic->where('effective_status', 'expired')->count();
        $expiring = $lic->where('effective_status', 'expiring')->count();
        $renewals = max(0, 20 - 10 * $expired - 4 * $expiring);

        $score = max(0, min(100, $payments + $support + $usage + $renewals));
        if ($a->hub_status === 'blocked' || $a->suspended_at) {
            $score = min($score, 20);
        }
        $autoRenew = $this->customerDomains($a)->where('auto_renew', true)->isNotEmpty();
        $renewalProb = max(0, min(100, (int) round($score * 0.85 + ($autoRenew ? 10 : 0) + ($lic->isEmpty() ? 0 : 5))));

        return [
            'score' => $score,
            'grade' => $score >= 75 ? 'good' : ($score >= 50 ? 'fair' : 'at_risk'),
            'breakdown' => [
                'payments' => ['score' => $payments, 'max' => 30],
                'support' => ['score' => $support, 'max' => 25, 'open' => $open, 'breached' => $breached],
                'usage' => ['score' => $usage, 'max' => 25, 'active_sites' => $activeSites, 'recent_activity' => $recent],
                'renewals' => ['score' => $renewals, 'max' => 20, 'expired' => $expired, 'expiring' => $expiring],
            ],
            'renewal_probability' => $renewalProb,
            'churn_risk' => 100 - $renewalProb,
        ];
    }

    /** @return list<array<string,mixed>> computed + stored alerts */
    public function alerts(CrmAccount $a): array
    {
        $out = [];
        foreach ($this->rows('crm_customer_alerts', fn ($q) => $q->where('account_id', $a->id)->whereNull('resolved_at')->orderByDesc('id')) as $r) {
            $out[] = ['id' => $r->id, 'kind' => $r->kind, 'severity' => $r->severity, 'body' => $r->body, 'created_at' => $r->created_at, 'stored' => true];
        }
        $fin = $this->financials($a);
        if ($fin['overdue'] > 0) {
            $out[] = ['kind' => 'risk', 'code' => 'overdue_debt', 'severity' => 'critical', 'body' => 'بدهی سررسیدشده', 'value' => $fin['overdue'], 'stored' => false];
        }
        foreach ($this->licenses($a) as $l) {
            if ($l['alert']) {
                $out[] = ['kind' => 'risk', 'code' => 'license_expiring', 'severity' => $l['alert'] <= 7 ? 'critical' : 'warning', 'body' => 'لایسنس رو به انقضا', 'value' => $l['domain'], 'days' => $l['days_left'], 'stored' => false];
            } elseif ($l['effective_status'] === 'expired') {
                $out[] = ['kind' => 'risk', 'code' => 'license_expired', 'severity' => 'critical', 'body' => 'لایسنس منقضی', 'value' => $l['domain'], 'stored' => false];
            }
            if ($l['near_limit']) {
                $out[] = ['kind' => 'risk', 'code' => 'near_limit', 'severity' => 'warning', 'body' => 'مصرف نزدیک به سقف', 'value' => $l['domain'], 'metrics' => $l['near_limit'], 'stored' => false];
            }
        }
        $crit = $this->tickets($a)->filter(fn ($t) => $t->is_open && (in_array($t->priority ?? null, ['urgent', 'critical', 'high'], true) || $t->sla_breached));
        if ($crit->isNotEmpty()) {
            $out[] = ['kind' => 'risk', 'code' => 'critical_tickets', 'severity' => 'critical', 'body' => 'تیکت بحرانی باز', 'value' => $crit->count(), 'stored' => false];
        }
        foreach ($this->customerDomains($a) as $d) {
            if ($d->expires_at && CarbonImmutable::parse($d->expires_at)->between(now(), now()->addDays(30))) {
                $out[] = ['kind' => 'risk', 'code' => 'domain_expiring', 'severity' => 'warning', 'body' => 'دامنه رو به انقضا', 'value' => $d->domain, 'stored' => false];
            }
        }
        foreach ($this->rows('crm_customer_documents', fn ($q) => $q->where('account_id', $a->id)->whereNotNull('expires_at')) as $d) {
            if (CarbonImmutable::parse($d->expires_at)->lt(now()->addDays(30))) {
                $out[] = ['kind' => 'risk', 'code' => 'document_expiring', 'severity' => 'warning', 'body' => 'اعتبار مدرک', 'value' => $d->title, 'stored' => false];
            }
        }

        return $out;
    }

    public function kpis(CrmAccount $a, bool $finance): array
    {
        $fin = $finance ? $this->financials($a) : null;

        return [
            'lifetime_revenue' => $fin['lifetime_revenue'] ?? null,
            'revenue_12m' => $fin['revenue_12m'] ?? null,
            'balance' => $fin['balance'] ?? null,
            'outstanding' => $fin['outstanding'] ?? null,
            'wallet_credit' => $fin['wallet_credit'] ?? null,
            'active_sites' => $this->provisions($a)->whereNotIn('status', ['failed', 'cancelled', 'deleted'])->count() + $this->resources($a)->count(),
            'domains' => count($this->domains($a)),
            'active_licenses' => $this->licenses($a)->whereIn('effective_status', ['active', 'expiring'])->count(),
            'open_tickets' => $this->tickets($a)->where('is_open', true)->count(),
            'last_payment' => $fin ? ($fin['last_payment'] ? ['date' => $fin['last_payment']['date'], 'amount' => $fin['last_payment']['amount']] : null) : null,
            'last_activity_at' => $this->lastActivityAt($a),
        ];
    }

    public function header(CrmAccount $a): array
    {
        $owner = $a->owner_id ? DB::table('users')->where('id', $a->owner_id)->first(['id', 'name', 'email']) : null;
        $tags = [];
        if ($this->has('crm_taggables')) {
            $tags = DB::table('crm_taggables')->join('crm_tags', 'crm_tags.id', '=', 'crm_taggables.tag_id')
                ->where('crm_taggables.taggable_id', $a->id)->whereIn('crm_taggables.taggable_type', ['account', CrmAccount::class])
                ->get(['crm_tags.id', 'crm_tags.name', 'crm_tags.color'])->all();
        }
        $primary = $this->rows('crm_contacts', fn ($q) => $q->where('account_id', $a->id)->whereNull('deleted_at')->orderByDesc('is_primary')->orderBy('id'), 1)->first();

        return [
            'id' => $a->id,
            'name' => $a->name,
            'brand_name' => $a->brand_name,
            'code' => $a->account_code ?: sprintf('C-%05d', $a->id),
            'kind' => $a->customer_kind ?: ($a->type === 'partner' ? 'partner' : null),
            'status' => $a->suspended_at ? 'blocked' : ($a->hub_status ?: 'active'),
            'logo_url' => $a->logo_url,
            'owner' => $owner,
            'tags' => $tags,
            'primary_phone' => $primary->mobile ?? $primary->phone ?? null,
            'primary_email' => $primary->email ?? null,
            'website' => $a->website,
        ];
    }

    // ───────────────────────── sections ─────────────────────────

    public function identity(CrmAccount $a): array
    {
        $contacts = $this->rows('crm_contacts', fn ($q) => $q->where('account_id', $a->id)->whereNull('deleted_at')->orderByDesc('is_primary')->orderBy('id'), 200)
            ->map(fn ($c) => [
                'id' => $c->id, 'first_name' => $c->first_name, 'last_name' => $c->last_name, 'name' => trim($c->first_name.' '.$c->last_name),
                'job_title' => $c->job_title, 'role' => $c->contact_role ?? $c->decision_role ?? null, 'mobile' => $c->mobile, 'phone' => $c->phone,
                'email' => $c->email, 'whatsapp' => $c->whatsapp ?? null, 'is_default' => (bool) $c->is_primary,
            ])->values();

        return [
            'profile' => [
                'name' => $a->name, 'brand_name' => $a->brand_name, 'customer_kind' => $a->customer_kind, 'type' => $a->type,
                'national_id' => $a->national_id, 'economic_code' => $a->economic_code ?: $a->tax_id, 'registration_no' => $a->registration_no,
                'postal_code' => $a->postal_code, 'address_line' => $a->address_line ?: ($a->billing_address['line1'] ?? $a->billing_address['address'] ?? null),
                'city' => $a->city, 'province' => $a->province, 'country' => $a->country, 'website' => $a->website, 'logo_url' => $a->logo_url,
                'industry' => $a->industry, 'description' => $a->description, 'parent_id' => $a->parent_id,
            ],
            'contacts' => $contacts,
            'kyc' => ['status' => $a->kyc_status ?: 'none', 'note' => $a->kyc_note, 'verified_at' => $a->kyc_verified_at],
            'preferences' => [
                'language' => $a->preferred_language, 'channel' => $a->preferred_channel, 'timezone' => $a->timezone,
                'marketing_sms_opt_out' => (bool) $a->marketing_sms_opt_out,
            ],
        ];
    }

    public function assets(CrmAccount $a): array
    {
        $servers = $this->has('platform_servers') ? DB::table('platform_servers')->get(['id', 'name', 'ip', 'status'])->keyBy('id') : collect();
        $installs = $this->provisions($a)->isNotEmpty()
            ? $this->rows('site_module_installs', fn ($q) => $q->whereIn('site_provision_id', $this->provisions($a)->pluck('id')))->groupBy('site_provision_id')
            : collect();
        $sites = [];
        foreach ($this->provisions($a) as $p) {
            $wiz = $this->json($p->wizard_payload ?? null);
            $domain = $p->domain ?: ($p->subdomain ?? null);
            $sites[] = [
                'id' => 'prov-'.$p->id, 'provision_id' => $p->id, 'domain' => $domain, 'product_type' => $wiz['site_type'] ?? $wiz['business_type'] ?? 'webino',
                'environment' => 'production', 'status' => $p->status, 'created_at' => $p->created_at, 'last_deploy_at' => $p->ready_at ?? $p->launched_at,
                'panel_url' => $domain ? 'https://'.$domain.'/dashboard' : null, 'server' => $p->server_host_id, 'version' => $wiz['version'] ?? null,
                'ssl' => in_array($p->status, ['ready', 'active', 'live'], true) ? 'active' : ($p->status === 'ssl_pending' ? 'pending' : null),
                'license_id' => $p->license_id, 'modules' => collect($installs->get($p->id, []))->map(fn ($m) => ['slug' => $m->module_slug, 'status' => $m->status, 'version' => $m->version])->values(),
            ];
        }
        foreach ($this->resources($a) as $r) {
            $srv = $servers->get($r->server_id);
            $sites[] = [
                'id' => 'res-'.$r->id, 'resource_id' => $r->id, 'domain' => $r->fqdn ? preg_replace('#^https?://#', '', $r->fqdn) : $r->name,
                'product_type' => $r->site_type_slug ?: ($r->build_pack ?? $r->type), 'environment' => 'production', 'status' => $r->status,
                'created_at' => $r->created_at, 'last_deploy_at' => $this->rows('platform_deployments', fn ($q) => $q->where('resource_id', $r->id)->orderByDesc('id'), 1)->first()?->created_at,
                'panel_url' => null, 'server' => $srv ? ['name' => $srv->name, 'ip' => $srv->ip, 'status' => $srv->status] : null,
                'version' => $r->docker_image ?? $r->git_branch, 'ssl' => $this->rows('platform_domains', fn ($q) => $q->where('resource_id', $r->id), 1)->first()?->ssl_status,
                'license_id' => $r->license_id, 'modules' => [],
            ];
        }
        $services = $this->rows('crm_customer_services', fn ($q) => $q->where('account_id', $a->id)->orderBy('kind'));

        return [
            'sites' => $sites,
            'domains' => $this->customerDomains($a)->map(fn ($d) => (array) $d + ['nameservers_list' => $this->json($d->nameservers)])->values(),
            'discovered_domains' => $this->domains($a),
            'hosting' => $services->whereIn('kind', ['hosting', 'vps'])->values(),
            'integrations' => $services->whereNotIn('kind', ['hosting', 'vps'])->map(fn ($s) => (array) $s + ['key_masked' => $s->key_last4 ? '••••'.$s->key_last4 : null])->values(),
        ];
    }

    public function billing(CrmAccount $a): array
    {
        $contracts = $this->rows('prj_contracts', fn ($q) => $q->where('customer_account_id', $a->id)->orderByDesc('id'));
        $inst = $contracts->isNotEmpty() ? $this->rows('prj_contract_installments', fn ($q) => $q->whereIn('contract_id', $contracts->pluck('id'))->orderBy('due_date')) : collect();

        return [
            'summary' => $this->financials($a),
            'wallets' => $this->wallets($a),
            'contracts' => $contracts->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'status' => $c->status, 'amount' => (float) $c->amount, 'signed_at' => $c->signed_at ?? null,
                'start' => $this->json($c->installments_data ?? null)['start_date'] ?? null, 'project_id' => $c->project_id,
            ])->values(),
            'installments' => [
                'open' => $inst->whereNull('paid_at')->values(),
                'next_due' => $inst->whereNull('paid_at')->first(),
            ],
        ];
    }

    public function sales(CrmAccount $a): array
    {
        $deals = $this->rows('crm_deals', fn ($q) => $q->where('account_id', $a->id)->whereNull('deleted_at')->orderByDesc('id'), 200);
        $stages = $this->has('crm_stages') ? DB::table('crm_stages')->pluck('name', 'id') : collect();
        $leads = $this->rows('crm_leads', fn ($q) => $q->where('converted_to_account_id', $a->id)->orderByDesc('id'), 50);
        $quotes = $deals->isNotEmpty() ? $this->rows('crm_quote_approvals', fn ($q) => $q->whereIn('deal_id', $deals->pluck('id'))->orderByDesc('id')) : collect();
        $projects = $this->rows('prj_projects', fn ($q) => $q->where('customer_account_id', $a->id)->orderByDesc('id'), 200);
        $pids = $projects->pluck('id');
        $tasks = $pids->isNotEmpty() ? $this->rows('prj_tasks', fn ($q) => $q->whereIn('project_id', $pids)) : collect();
        $time = $pids->isNotEmpty() ? $this->rows('prj_time_entries', fn ($q) => $q->whereIn('project_id', $pids), 5000) : collect();
        $done = ['done', 'completed', 'closed'];

        return [
            'opportunities' => $deals->map(fn ($d) => ['id' => $d->id, 'title' => $d->name, 'stage' => $stages[$d->stage_id] ?? null, 'amount' => (float) $d->amount, 'probability' => $d->probability, 'owner_id' => $d->assigned_to, 'won_at' => $d->won_at, 'lost_at' => $d->lost_at])->values(),
            'leads' => $leads->map(fn ($l) => ['id' => $l->id, 'title' => $l->topic ?: trim(($l->first_name ?? '').' '.($l->last_name ?? '')), 'created_at' => $l->created_at])->values(),
            'proposals' => $quotes->map(fn ($q) => ['id' => $q->id, 'deal_id' => $q->deal_id, 'total' => (float) $q->total, 'status' => $q->status, 'date' => $q->created_at])->values(),
            'projects' => $projects->map(function ($p) use ($tasks, $done) {
                $pt = $tasks->where('project_id', $p->id);
                $pct = $pt->count() ? (int) round(100 * $pt->whereIn('status', $done)->count() / $pt->count()) : 0;

                return ['id' => $p->id, 'name' => $p->name, 'status' => $p->status, 'progress' => $pct, 'manager_id' => $p->manager_user_id ?? null, 'start_date' => $p->start_date ?? null, 'due_date' => $p->due_date ?? null];
            })->values(),
            'open_tasks' => $tasks->whereNotIn('status', $done)->sortBy('due_at')->take(100)->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'assignee_id' => $t->assignee_id, 'due_at' => $t->due_at, 'priority' => $t->priority ?? null, 'project_id' => $t->project_id])->values(),
            'time' => [
                'billable_hours' => round($time->where('is_billable', true)->sum('duration_seconds') / 3600, 1),
                'non_billable_hours' => round($time->where('is_billable', false)->sum('duration_seconds') / 3600, 1),
            ],
        ];
    }

    public function communications(CrmAccount $a): Collection
    {
        $acts = $this->rows('crm_activities', fn ($q) => $q->where('related_model', CrmAccount::class)->where('related_id', $a->id)->whereNull('deleted_at')->whereIn('type', ['call', 'email', 'sms', 'meeting', 'note', 'chat'])->orderByDesc('created_at'), 1000)
            ->map(fn ($r) => ['id' => 'act-'.$r->id, 'type' => $r->type, 'subject' => $r->subject, 'body' => $r->description, 'outcome' => $r->outcome, 'date' => $r->completed_at ?? $r->scheduled_at ?? $r->created_at, 'user_id' => $r->created_by]);
        $msgs = $this->rows('crm_messages', fn ($q) => $q->where('related_type', 'account')->where('related_id', $a->id)->orderByDesc('id'), 1000)
            ->map(fn ($m) => ['id' => 'msg-'.$m->id, 'type' => $m->channel, 'subject' => $m->subject, 'body' => $m->body, 'outcome' => $m->status, 'date' => $m->created_at, 'user_id' => $m->created_by, 'to' => $m->to_address]);
        $domains = $this->domains($a);
        $sms = $domains ? $this->rows('modirpayamak_messages', fn ($q) => $q->whereIn('domain', $domains)->orderByDesc('id'), 200)
            ->map(fn ($m) => ['id' => 'sms-'.$m->id, 'type' => 'sms_system', 'subject' => $m->pattern_code ?? $m->sending_type, 'body' => $m->message, 'outcome' => $m->status, 'date' => $m->created_at, 'user_id' => null]) : collect();

        return $acts->merge($msgs)->merge($sms)->sortByDesc(fn ($r) => (string) $r['date'])->values();
    }

    public function timeline(CrmAccount $a): Collection
    {
        $ev = collect();
        foreach ($this->communications($a) as $c) {
            $ev->push(['kind' => $c['type'], 'title' => $c['subject'] ?: $c['type'], 'date' => $c['date'], 'ref' => $c['id']]);
        }
        foreach ($this->payments($a) as $p) {
            $ev->push(['kind' => 'payment', 'title' => 'پرداخت '.number_format($p['amount']), 'date' => $p['date'], 'ref' => $p['id'], 'status' => $p['status']]);
        }
        foreach ($this->invoices($a) as $i) {
            $ev->push(['kind' => 'invoice', 'title' => 'فاکتور '.($i->invoice_number ?: $i->number), 'date' => $i->issue_date ?? $i->created_at, 'ref' => 'inv-'.$i->id, 'status' => $i->status]);
        }
        foreach ($this->tickets($a) as $t) {
            $ev->push(['kind' => 'ticket', 'title' => $t->subject, 'date' => $t->created_at, 'ref' => 'tk-'.$t->id, 'status' => $t->status]);
        }
        foreach ($this->provisions($a) as $p) {
            $ev->push(['kind' => 'site', 'title' => 'ایجاد سایت '.($p->domain ?: $p->slug), 'date' => $p->created_at, 'ref' => 'prov-'.$p->id, 'status' => $p->status]);
            if ($p->ready_at) {
                $ev->push(['kind' => 'deploy', 'title' => 'استقرار '.($p->domain ?: $p->slug), 'date' => $p->ready_at, 'ref' => 'prov-'.$p->id]);
            }
        }
        foreach ($this->licenses($a) as $l) {
            if ($l['start_date']) {
                $ev->push(['kind' => 'license', 'title' => 'لایسنس '.$l['domain'], 'date' => $l['start_date'], 'ref' => 'lic-'.$l['id']]);
            }
            foreach ((array) $l['renewals'] as $r) {
                $ev->push(['kind' => 'license_renewal', 'title' => 'تمدید لایسنس '.$l['domain'], 'date' => $r['at'] ?? null, 'ref' => 'lic-'.$l['id']]);
            }
        }
        foreach ($this->rows('crm_customer_audit', fn ($q) => $q->where('account_id', $a->id)->orderByDesc('id'), 500) as $r) {
            $ev->push(['kind' => 'audit', 'title' => $r->action, 'date' => $r->created_at, 'ref' => 'audit-'.$r->id]);
        }
        if ($ids = $this->portalUserIds($a)) {
            if ($this->has('users', 'last_login_at')) {
                foreach (DB::table('users')->whereIn('id', $ids)->whereNotNull('last_login_at')->get(['name', 'last_login_at']) as $u) {
                    $ev->push(['kind' => 'login', 'title' => 'ورود به پنل: '.$u->name, 'date' => $u->last_login_at, 'ref' => null]);
                }
            }
        }

        return $ev->filter(fn ($e) => ! empty($e['date']))->sortByDesc(fn ($e) => (string) $e['date'])->values();
    }

    public function usage(CrmAccount $a): array
    {
        $metrics = $this->rows('crm_customer_usage_metrics', fn ($q) => $q->where('account_id', $a->id)->orderByDesc('period'), 2000);
        $latest = $metrics->groupBy('metric')->map(fn ($g) => (float) $g->first()->value);
        $domains = $this->domains($a);
        $smsSent = $domains ? ($this->has('modirpayamak_messages') ? DB::table('modirpayamak_messages')->whereIn('domain', $domains)->count() : 0) : 0;
        $orders = $this->has('marketplace_orders') ? DB::table('marketplace_orders')->where('crm_account_id', $a->id)->count() : 0;

        return [
            'modules' => collect($this->assets($a)['sites'])->map(fn ($s) => ['domain' => $s['domain'], 'modules' => $s['modules']])->values(),
            'sms' => ['sent' => (int) ($latest['sms_sent'] ?? $smsSent), 'remaining_credit' => (float) collect($this->wallets($a)['sms'])->sum('balance')],
            'orders' => (int) ($latest['orders'] ?? $orders),
            'traffic_mb' => $latest['traffic_mb'] ?? null,
            'storage_mb' => $latest['storage_mb'] ?? null,
            'bandwidth_mb' => $latest['bandwidth_mb'] ?? null,
            'cpu_pct' => $latest['cpu_pct'] ?? null,
            'ram_mb' => $latest['ram_mb'] ?? null,
            'panel_users' => count($this->portalUserIds($a)),
            'last_login_at' => $this->lastLoginAt($a),
            'server_metrics_connected' => $metrics->whereIn('metric', ['cpu_pct', 'ram_mb', 'storage_mb', 'traffic_mb'])->isNotEmpty(),
        ];
    }

    public function documents(CrmAccount $a, array $roles, bool $admin): Collection
    {
        $docs = $this->rows('crm_customer_documents', fn ($q) => $q->where('account_id', $a->id)->orderByDesc('id'), 1000)
            ->filter(function ($d) use ($roles, $admin) {
                $vis = $this->json($d->visible_to_roles);

                return $admin || ! $vis || array_intersect($vis, $roles);
            })->map(fn ($d) => (array) $d + ['visible_to_roles' => $this->json($d->visible_to_roles), 'expired' => $d->expires_at && CarbonImmutable::parse($d->expires_at)->isPast(), 'source' => 'hub']);
        // contracts from Projects appear as documents too
        foreach ($this->rows('prj_contracts', fn ($q) => $q->where('customer_account_id', $a->id)) as $c) {
            $docs->push(['id' => 'contract-'.$c->id, 'category' => 'contract', 'title' => $c->title, 'url' => null, 'review_status' => $c->status, 'source' => 'projects']);
        }

        return $docs->values();
    }

    public function access(CrmAccount $a): array
    {
        $ids = $this->portalUserIds($a);
        $cols = array_values(array_filter(['id', 'name', 'email', 'last_login_at', 'is_active', 'created_at', 'two_factor_confirmed_at', 'two_factor_secret'], fn ($c) => $this->has('users', $c)));
        $users = $ids ? DB::table('users')->whereIn('id', $ids)->get($cols) : collect();
        $pivot = $this->rows('crm_account_users', fn ($q) => $q->where('account_id', $a->id))->keyBy('user_id');
        $roles = $ids && $this->has('model_has_roles')
            ? DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->whereIn('model_id', $ids)->get(['model_id', 'roles.name'])->groupBy('model_id')
            : collect();
        $tokens = $ids && $this->has('personal_access_tokens')
            ? DB::table('personal_access_tokens')->where('tokenable_type', 'like', '%User')->whereIn('tokenable_id', $ids)->orderByDesc('id')->get(['id', 'tokenable_id', 'name', 'last_used_at', 'expires_at', 'created_at'])
            : collect();
        $twoFa = $this->has('users', 'two_factor_confirmed_at') || $this->has('users', 'two_factor_secret');

        return [
            'users' => $users->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'last_login_at' => $u->last_login_at ?? null,
                'is_primary' => (bool) ($pivot[$u->id]->is_primary ?? false), 'roles' => collect($roles->get($u->id, []))->pluck('name')->values(),
                'two_factor' => $twoFa ? (! empty($u->two_factor_confirmed_at ?? null) || ! empty($u->two_factor_secret ?? null)) : null,
            ])->values(),
            'pending_invites' => $users->filter(fn ($u) => empty($u->last_login_at ?? null))->map(fn ($u) => ['id' => $u->id, 'email' => $u->email, 'invited_at' => $u->created_at ?? null])->values(),
            'api_tokens' => $tokens->map(fn ($t) => ['id' => $t->id, 'user_id' => $t->tokenable_id, 'name' => $t->name, 'last_used_at' => $t->last_used_at, 'expires_at' => $t->expires_at])->values(),
            'two_factor_supported' => $twoFa,
            'allowed_ips' => $this->json($a->allowed_ips),
            'audit' => $this->rows('crm_customer_audit', fn ($q) => $q->where('account_id', $a->id)->orderByDesc('id'), 50)->map(fn ($r) => (array) $r + ['changes' => $this->json($r->changes)])->values(),
        ];
    }

    public function relations(CrmAccount $a): array
    {
        $name = fn ($id) => $id ? DB::table('crm_accounts')->where('id', $id)->first(['id', 'name']) : null;
        $rel = $this->rows('crm_customer_relations', fn ($q) => $q->where('account_id', $a->id)->orWhere('related_account_id', $a->id));
        $other = DB::table('crm_accounts')->whereIn('id', $rel->pluck('account_id')->merge($rel->pluck('related_account_id'))->unique())->pluck('name', 'id');

        return [
            'parent' => $name($a->parent_id),
            'children' => DB::table('crm_accounts')->where('parent_id', $a->id)->whereNull('deleted_at')->get(['id', 'name']),
            'referrer' => $name($a->referrer_account_id),
            'referred' => DB::table('crm_accounts')->where('referrer_account_id', $a->id)->whereNull('deleted_at')->get(['id', 'name']),
            'partner' => $name($a->partner_account_id),
            'related' => $rel->map(function ($r) use ($a, $other) {
                $oid = (int) $r->account_id === $a->id ? $r->related_account_id : $r->account_id;

                return ['id' => $r->id, 'account_id' => $oid, 'name' => $other[$oid] ?? null, 'type' => $r->relation_type, 'note' => $r->note];
            })->values(),
            'originating_lead' => $this->rows('crm_leads', fn ($q) => $q->where('converted_to_account_id', $a->id)->orderBy('id'), 1)->first(),
        ];
    }

    public function notes(CrmAccount $a): array
    {
        return [
            'pinned' => $this->rows('crm_customer_alerts', fn ($q) => $q->where('account_id', $a->id)->where('kind', 'pinned_note')->whereNull('resolved_at')->orderByDesc('id'))->values(),
            'alerts' => $this->alerts($a),
            'onboarding' => $this->rows('crm_customer_onboarding_items', fn ($q) => $q->where('account_id', $a->id)->orderBy('sort')->orderBy('id'))->values(),
            'recent_notes' => $this->rows('crm_activities', fn ($q) => $q->where('related_model', CrmAccount::class)->where('related_id', $a->id)->where('type', 'note')->whereNull('deleted_at')->orderByDesc('id'), 20)->values(),
        ];
    }

    public function analytics(CrmAccount $a, bool $finance): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i)->format('Y-m');
        }
        $bucket = fn (iterable $items, callable $date, callable $val) => collect($months)->mapWithKeys(fn ($m) => [$m => 0.0])
            ->merge(collect($items)->groupBy(fn ($x) => substr((string) $date($x), 0, 7))->map(fn ($g) => (float) $g->sum($val))->only($months))
            ->map(fn ($v, $m) => ['month' => $m, 'value' => round($v, 2)])->values();

        $revenue = null;
        $supportCost = null;
        $fin = null;
        if ($finance) {
            $fin = $this->financials($a);
            $paidInv = $this->invoices($a)->filter(fn ($i) => in_array($i->status, self::PAID_INVOICE, true));
            $unlinked = $this->payments($a)->where('paid', true)->filter(fn ($p) => empty($p['invoice_id']));
            $revenue = $bucket($paidInv->map(fn ($i) => ['d' => $i->issue_date, 'v' => (float) $i->total])->merge($unlinked->map(fn ($p) => ['d' => $p['date'], 'v' => $p['amount']])), fn ($x) => $x['d'], fn ($x) => $x['v']);
            $pids = $this->projectIds($a);
            $rate = $pids && $this->has('prj_projects', 'hourly_rate') ? (float) (DB::table('prj_projects')->whereIn('id', $pids)->avg('hourly_rate') ?? 0) : 0;
            $hours = $pids ? (float) $this->rows('prj_time_entries', fn ($q) => $q->whereIn('project_id', $pids), 10000)->where('is_billable', false)->sum('duration_seconds') / 3600 : 0;
            $supportCost = ['hours' => round($hours, 1), 'estimated_cost' => round($hours * $rate, 2), 'revenue_12m' => $fin['revenue_12m']];
        }
        $tickets = $bucket($this->tickets($a), fn ($t) => $t->created_at, fn () => 1);
        $smsRows = $this->rows('crm_customer_usage_metrics', fn ($q) => $q->where('account_id', $a->id)->where('metric', 'sms_sent'), 2000);
        $domains = $this->domains($a);
        if ($smsRows->isEmpty() && $domains) {
            $smsRows = $this->rows('modirpayamak_messages', fn ($q) => $q->whereIn('domain', $domains)->where('created_at', '>=', now()->subMonths(12)), 20000)->map(fn ($m) => (object) ['period' => $m->created_at, 'value' => 1]);
        }
        $sms = $bucket($smsRows, fn ($r) => $r->period, fn ($r) => (float) $r->value);
        $health = $this->health($a);
        $monthsActive = max(1, (int) ceil(($a->created_at ?? now())->diffInMonths(now())) ?: 1);

        return [
            'revenue_monthly' => $revenue,
            'tickets_monthly' => $tickets,
            'sms_monthly' => $sms,
            'health' => $health,
            'churn_risk' => $health['churn_risk'],
            'renewal_probability' => $health['renewal_probability'],
            'ltv' => $fin ? [
                'historical' => $fin['lifetime_revenue'],
                'monthly_avg_12m' => round($fin['revenue_12m'] / 12, 2),
                'projected_24m' => round($fin['revenue_12m'] / 12 * 24 * $health['renewal_probability'] / 100, 2),
                'months_active' => $monthsActive,
            ] : null,
            'support_cost_vs_revenue' => $supportCost,
            'nps' => null,
        ];
    }
}
