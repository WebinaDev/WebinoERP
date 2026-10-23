'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { deployResource, startResource, stopResource } from '@/lib/api/platform';
import { hubBase, useHubList, useHubSection, type Row } from './hub-api';
import { useHub } from './HubContext';
import { DataTable, Empty, HubCard, KV, Loading, Pager, StatusBadge, useFmt } from './ui';

export function useRemove() {
  const { id, refresh, setNotice } = useHub();
  const t = useTranslations('crm.hub');
  return async (path: string) => {
    if (typeof window !== 'undefined' && !window.confirm(`${t('delete')}?`)) return;
    try {
      await apiClient.delete(`${hubBase(id)}/${path}`);
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  };
}

/* ───────────── A. Identity ───────────── */
export function IdentityTab() {
  const t = useTranslations('crm.hub');
  const { id, summary, version, canDo, open } = useHub();
  const { data } = useHubSection<Row>(id, 'identity', true, version);
  const remove = useRemove();
  const d = data ?? summary.identity;
  if (!d) return <Loading />;
  const p = d.profile ?? {};
  const initial = Object.fromEntries(
    Object.entries({ ...p, kyc_status: d.kyc?.status, kyc_note: d.kyc?.note, preferred_language: d.preferences?.language, preferred_channel: d.preferences?.channel, timezone: d.preferences?.timezone, marketing_sms_opt_out: d.preferences?.marketing_sms_opt_out, hub_status: summary.header.status === 'blocked' ? '' : summary.header.status })
      .filter(([k]) => !['type', 'parent_id', 'description'].includes(k))
      .map(([k, v]) => [k, typeof v === 'boolean' ? v : v === null || v === undefined ? '' : String(v)]),
  );
  return (
    <div className="space-y-4">
      <HubCard title={t('tab.identity')} actions={canDo('profile') ? <Button size="sm" variant="outline" onClick={() => open('profile', initial)}><Pencil className="me-1 h-4 w-4" />{t('edit')}</Button> : null}>
        <KV
          items={[
            [t('f.name'), p.name], [t('f.brand'), p.brand_name], [t('f.kind'), p.customer_kind ? t(`kind.${p.customer_kind}` as never) : null],
            [t('f.nationalId'), p.national_id], [t('f.economicCode'), p.economic_code], [t('f.registrationNo'), p.registration_no],
            [t('f.postalCode'), p.postal_code], [t('f.province'), p.province], [t('f.city'), p.city],
            [t('f.address'), p.address_line], [t('f.country'), p.country], [t('f.industry'), p.industry],
            [t('f.website'), p.website ? <a className="text-primary underline" href={String(p.website).startsWith('http') ? p.website : `https://${p.website}`} target="_blank" rel="noreferrer" dir="ltr">{p.website}</a> : null],
          ]}
        />
      </HubCard>
      <HubCard title={t('contacts')} actions={canDo('contact') ? <Button size="sm" variant="outline" onClick={() => open('contact')}><Plus className="me-1 h-4 w-4" />{t('act.contact')}</Button> : null}>
        <DataTable
          rows={d.contacts ?? []}
          cols={[
            { key: 'name', label: t('c.firstName'), render: (r) => <span>{r.name}{r.is_default ? <Badge className="ms-2" variant="secondary">{t('c.default')}</Badge> : null}</span> },
            { key: 'job_title', label: t('c.jobTitle') },
            { key: 'role', label: t('c.role'), render: (r) => (r.role && ['manager', 'finance', 'technical', 'support', 'other'].includes(r.role) ? t(`role.${r.role}` as never) : r.role ?? '—') },
            { key: 'mobile', label: t('c.mobile'), render: (r) => <span dir="ltr">{r.mobile ?? '—'}</span> },
            { key: 'phone', label: t('c.phone'), render: (r) => <span dir="ltr">{r.phone ?? '—'}</span> },
            { key: 'email', label: t('c.email'), render: (r) => <span dir="ltr">{r.email ?? '—'}</span> },
            { key: 'whatsapp', label: t('c.whatsapp'), render: (r) => (r.whatsapp ? <a className="text-primary" dir="ltr" href={`https://wa.me/${String(r.whatsapp).replace(/\D/g, '')}`} target="_blank" rel="noreferrer">{r.whatsapp}</a> : '—') },
            {
              key: 'ops', label: '', render: (r) => canDo('contact') ? (
                <div className="flex gap-1">
                  <Button size="icon" variant="ghost" onClick={() => open('contact', { _id: String(r.id), first_name: r.first_name ?? '', last_name: r.last_name ?? '', job_title: r.job_title ?? '', contact_role: r.role ?? '', mobile: r.mobile ?? '', phone: r.phone ?? '', email: r.email ?? '', whatsapp: r.whatsapp ?? '', is_primary: Boolean(r.is_default) })}><Pencil className="h-4 w-4" /></Button>
                  <Button size="icon" variant="ghost" onClick={() => void remove(`contacts/${r.id}`)}><Trash2 className="h-4 w-4" /></Button>
                </div>
              ) : null,
            },
          ]}
        />
      </HubCard>
      <div className="grid gap-4 md:grid-cols-2">
        <HubCard title={t('kyc.title')}>
          <KV items={[[t('f.status'), <StatusBadge key="k" value={d.kyc?.status} label={t(`kyc.${d.kyc?.status ?? 'none'}` as never)} />], [t('kyc.note'), d.kyc?.note], [t('b.date'), d.kyc?.verified_at]]} />
        </HubCard>
        <HubCard title={t('prefs')}>
          <KV
            items={[
              [t('p.language'), d.preferences?.language], [t('p.channel'), d.preferences?.channel ? t(`ch.${d.preferences.channel}` as never) : null],
              [t('p.timezone'), d.preferences?.timezone], [t('p.optOut'), d.preferences?.marketing_sms_opt_out ? '✓' : '✗'],
            ]}
          />
        </HubCard>
      </div>
    </div>
  );
}

/* ───────────── B. Sites & Assets ───────────── */
export function AssetsTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open, refresh, setNotice } = useHub();
  const { data, loading } = useHubSection<Row>(id, 'assets', true, version);
  const remove = useRemove();
  const [busy, setBusy] = useState<string | null>(null);
  if (loading && !data) return <Loading />;
  if (!data) return <Empty />;
  async function platform(resId: number, action: 'start' | 'stop' | 'deploy') {
    setBusy(`${resId}-${action}`);
    try {
      if (action === 'start') await startResource(resId);
      else if (action === 'stop') await stopResource(resId);
      else await deployResource(resId);
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    } finally {
      setBusy(null);
    }
  }
  return (
    <div className="space-y-4">
      <HubCard title={t('sites')}>
        {(data.sites as Row[]).length === 0 ? <Empty /> : (
          <div className="grid gap-3 md:grid-cols-2">
            {(data.sites as Row[]).map((s) => (
              <div key={s.id} className="space-y-2 rounded-lg border p-3">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-semibold" dir="ltr">{s.domain ?? '—'}</span>
                  <StatusBadge value={s.status} />
                </div>
                <KV
                  items={[
                    [t('s.product'), s.product_type], [t('s.env'), s.environment],
                    [t('s.server'), typeof s.server === 'object' && s.server ? `${s.server.name} (${s.server.ip ?? ''})` : s.server],
                    [t('s.version'), s.version], [t('s.ssl'), s.ssl ? <StatusBadge key="ssl" value={s.ssl} /> : null],
                    [t('s.lastDeploy'), f.dateTime(s.last_deploy_at)],
                  ]}
                />
                {(s.modules as Row[])?.length ? (
                  <div className="flex flex-wrap gap-1 text-xs">
                    <span className="text-muted-foreground">{t('s.modules')}:</span>
                    {(s.modules as Row[]).map((m) => <Badge key={m.slug} variant="outline">{m.slug}{m.version ? ` ${m.version}` : ''}</Badge>)}
                  </div>
                ) : null}
                <div className="flex flex-wrap gap-2">
                  {s.panel_url ? <Button size="sm" variant="outline" asChild><a href={s.panel_url} target="_blank" rel="noreferrer"><ExternalLink className="me-1 h-4 w-4" />{t('s.panel')}</a></Button> : null}
                  {s.resource_id && canDo('domain') ? (
                    <>
                      <Button size="sm" variant="outline" disabled={busy !== null} onClick={() => void platform(s.resource_id, 'start')}>{t('s.start')}</Button>
                      <Button size="sm" variant="outline" disabled={busy !== null} onClick={() => void platform(s.resource_id, 'stop')}>{t('s.stop')}</Button>
                      <Button size="sm" variant="outline" disabled={busy !== null} onClick={() => void platform(s.resource_id, 'deploy')}>{t('s.redeploy')}</Button>
                    </>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
        )}
      </HubCard>
      <HubCard title={t('domains')} actions={canDo('domain') ? <Button size="sm" variant="outline" onClick={() => open('domain')}><Plus className="me-1 h-4 w-4" />{t('act.domain')}</Button> : null}>
        <DataTable
          rows={data.domains ?? []}
          cols={[
            { key: 'domain', label: t('s.domain'), render: (r) => <span dir="ltr">{r.domain}</span> },
            { key: 'registrar', label: t('d.registrar') },
            { key: 'registered_at', label: t('d.registered'), render: (r) => f.date(r.registered_at) },
            { key: 'expires_at', label: t('d.expires'), render: (r) => f.date(r.expires_at) },
            { key: 'ns', label: t('d.ns'), render: (r) => <span dir="ltr" className="text-xs">{(r.nameservers_list ?? []).join(', ') || '—'}</span> },
            { key: 'auto_renew', label: t('d.autoRenew'), render: (r) => (r.auto_renew ? '✓' : '✗') },
            { key: 'ops', label: '', render: (r) => (canDo('domain') ? <Button size="icon" variant="ghost" onClick={() => void remove(`domains/${r.id}`)}><Trash2 className="h-4 w-4" /></Button> : null) },
          ]}
        />
        {(data.discovered_domains ?? []).length ? (
          <div className="mt-3 flex flex-wrap gap-1 text-xs">
            {(data.discovered_domains as string[]).map((d) => <Badge key={d} variant="outline" dir="ltr">{d}</Badge>)}
          </div>
        ) : null}
      </HubCard>
      <ServicesCard title={t('hosting')} rows={data.hosting ?? []} />
      <ServicesCard title={t('integrations')} rows={data.integrations ?? []} />
    </div>
  );
}

function ServicesCard({ title, rows }: { title: string; rows: Row[] }) {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { canDo, open } = useHub();
  const remove = useRemove();
  return (
    <HubCard title={title} actions={canDo('service') ? <Button size="sm" variant="outline" onClick={() => open('service')}><Plus className="me-1 h-4 w-4" />{t('act.service')}</Button> : null}>
      <DataTable
        rows={rows}
        cols={[
          { key: 'kind', label: t('i.kind') }, { key: 'name', label: t('doc.title') }, { key: 'provider', label: t('i.provider') },
          { key: 'plan', label: t('i.plan') }, { key: 'ip', label: 'IP', render: (r) => <span dir="ltr">{r.ip ?? '—'}</span> },
          { key: 'key', label: t('i.key'), render: (r) => <span dir="ltr">{r.key_masked ?? (r.key_last4 ? `••••${r.key_last4}` : '—')}</span> },
          { key: 'renews_at', label: t('i.renews'), render: (r) => f.date(r.renews_at) },
          { key: 'last_backup_at', label: t('i.backup'), render: (r) => f.date(r.last_backup_at) },
          { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'ops', label: '', render: (r) => (canDo('service') ? <Button size="icon" variant="ghost" onClick={() => void remove(`services/${r.id}`)}><Trash2 className="h-4 w-4" /></Button> : null) },
        ]}
      />
    </HubCard>
  );
}

/* ───────────── C. Licenses ───────────── */
export function LicensesTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const list = useHubList(id, 'licenses', {}, true, version);
  return (
    <HubCard title={t('tab.licenses')} actions={canDo('license') ? <Button size="sm" variant="outline" onClick={() => open('license')}><Plus className="me-1 h-4 w-4" />{t('act.license')}</Button> : null}>
      {list.loading && !list.rows.length ? <Loading /> : (
        <div className="space-y-3">
          {list.rows.length === 0 ? <Empty /> : list.rows.map((l) => (
            <div key={l.id} className="space-y-2 rounded-lg border p-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold" dir="ltr">{l.domain}</span>
                <Badge variant="outline">{l.product}</Badge>
                {l.type ? <Badge variant="secondary">{['monthly', 'yearly', 'perpetual', 'trial'].includes(l.type) ? t(`lt.${l.type}` as never) : l.type}</Badge> : null}
                <StatusBadge value={l.effective_status} />
                {l.alert ? <Badge variant={l.alert <= 7 ? 'destructive' : 'secondary'}>{t('l.alert', { days: l.alert })}</Badge> : null}
                {Object.keys(l.near_limit ?? {}).length ? <Badge variant="destructive">{t('l.nearLimit')}</Badge> : null}
                {canDo('license') ? <Button size="sm" variant="outline" className="ms-auto" onClick={() => open('renew', { _id: String(l.id), months: '12' })}>{t('l.renew')}</Button> : null}
              </div>
              <KV
                items={[
                  [t('l.start'), f.date(l.start_date)], [t('l.expires'), l.expires_at ? `${f.date(l.expires_at)}${l.days_left !== null ? ` · ${l.days_left < 0 ? t('l.expired') : t('l.daysLeft', { days: l.days_left })}` : ''}` : '∞'],
                  [t('l.key'), <span key="k" dir="ltr">{l.key_masked ?? '—'}</span>],
                ]}
              />
              {Object.keys(l.limits ?? {}).length ? (
                <div className="space-y-1">
                  <span className="text-xs text-muted-foreground">{t('l.limits')}</span>
                  {Object.entries(l.limits as Record<string, number>).map(([k, lim]) => {
                    const used = Number((l.usage ?? {})[k] ?? 0);
                    const pct = lim ? Math.min(100, Math.round((used / Number(lim)) * 100)) : 0;
                    return (
                      <div key={k} className="flex items-center gap-2 text-xs">
                        <span className="w-24">{k}</span>
                        <Progress value={pct} className={`h-2 flex-1 ${pct >= 80 ? '[&>div]:bg-destructive' : ''}`} />
                        <span dir="ltr">{f.num(used)} / {f.num(lim)}</span>
                      </div>
                    );
                  })}
                </div>
              ) : null}
              {(Array.isArray(l.feature_flags) ? l.feature_flags : Object.keys(l.feature_flags ?? {})).length ? (
                <div className="flex flex-wrap gap-1 text-xs">
                  <span className="text-muted-foreground">{t('l.features')}:</span>
                  {(Array.isArray(l.feature_flags) ? (l.feature_flags as string[]) : Object.entries(l.feature_flags as Record<string, unknown>).filter(([, v]) => v).map(([k]) => k)).map((x) => <Badge key={x} variant="outline">{x}</Badge>)}
                </div>
              ) : null}
            </div>
          ))}
          <Pager meta={list.meta} onPage={list.setPage} />
        </div>
      )}
    </HubCard>
  );
}

/* ───────────── D. Billing ───────────── */
export function BillingTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const { data } = useHubSection<Row>(id, 'billing', true, version);
  const [status, setStatus] = useState<string>('');
  const inv = useHubList(id, 'invoices', { status }, true, version);
  const pay = useHubList(id, 'payments', {}, true, version);
  const led = useHubList(id, 'wallet_ledger', {}, true, version);
  const s = data?.summary ?? {};
  return (
    <div className="space-y-4">
      <HubCard title={t('tab.billing')}>
        <KV
          items={[
            [t('kpi.lifetime'), f.money(s.lifetime_revenue)], [t('kpi.revenue12m'), f.money(s.revenue_12m)], [t('b.outstanding'), f.money(s.outstanding)],
            [t('b.overdue'), `${f.money(s.overdue)} (${f.num(s.overdue_count)})`], [t('b.totalPaid'), f.money(s.total_paid)], [t('b.aov'), f.money(s.aov)],
            [t('b.preferred'), s.preferred_payment_method], [t('f.creditLimit'), s.credit_limit !== null && s.credit_limit !== undefined ? f.money(s.credit_limit) : null],
            [t('b.nextDue'), data?.installments?.next_due ? `${f.money(data.installments.next_due.amount)} · ${f.date(data.installments.next_due.due_date)}` : null],
          ]}
        />
      </HubCard>
      <HubCard
        title={t('b.invoices')}
        actions={
          <>
            <select className="h-8 rounded-md border bg-transparent px-2 text-xs" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">{t('su.all')}</option>
              {['unpaid', 'paid', 'overdue', 'draft', 'cancelled'].map((x) => <option key={x} value={x}>{t(`inv.${x}` as never)}</option>)}
            </select>
            {canDo('invoice') ? <Button size="sm" variant="outline" onClick={() => open('invoice')}><Plus className="me-1 h-4 w-4" />{t('act.invoice')}</Button> : null}
          </>
        }
      >
        <DataTable
          rows={inv.rows}
          cols={[
            { key: 'number', label: t('b.number'), render: (r) => <span dir="ltr">{r.invoice_number ?? r.number}</span> },
            { key: 'issue_date', label: t('b.date'), render: (r) => f.date(r.issue_date ?? r.created_at) },
            { key: 'due_date', label: t('a.dueDate'), render: (r) => f.date(r.due_date) },
            { key: 'total', label: t('b.amount'), render: (r) => f.money(r.total) },
            { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} label={['paid', 'unpaid', 'draft', 'overdue', 'cancelled'].includes(r.status) ? t(`inv.${r.status}` as never) : r.status} /> },
            { key: 'pay', label: '', render: (r) => (canDo('payment') && r.status !== 'paid' ? <Button size="sm" variant="ghost" onClick={() => open('payment', { invoice_id: String(r.id), amount: String(r.total ?? '') })}>{t('act.payment')}</Button> : null) },
          ]}
        />
        <Pager meta={inv.meta} onPage={inv.setPage} />
      </HubCard>
      <HubCard title={t('b.payments')} actions={canDo('payment') ? <Button size="sm" variant="outline" onClick={() => open('payment')}><Plus className="me-1 h-4 w-4" />{t('act.payment')}</Button> : null}>
        <DataTable
          rows={pay.rows}
          cols={[
            { key: 'date', label: t('b.date'), render: (r) => f.dateTime(r.date) },
            { key: 'amount', label: t('b.amount'), render: (r) => f.money(r.amount) },
            { key: 'gateway', label: t('b.gateway'), render: (r) => (r.gateway && ['cash', 'card_to_card', 'bank_transfer', 'cheque', 'pos', 'other'].includes(r.gateway) ? t(`pm.${r.gateway}` as never) : r.gateway ?? '—') },
            { key: 'tracking_code', label: t('b.tracking'), render: (r) => <span dir="ltr">{r.tracking_code ?? '—'}</span> },
            { key: 'source', label: t('i.kind') },
            { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.paid ? 'paid' : r.status} /> },
          ]}
        />
        <Pager meta={pay.meta} onPage={pay.setPage} />
      </HubCard>
      <div className="grid gap-4 md:grid-cols-2">
        <HubCard title={t('b.wallets')}>
          <KV
            items={[
              ...((data?.wallets?.general ?? []) as Row[]).map((w): [string, string] => [`${t('b.generalWallet')} · ${w.domain}`, f.money(w.balance)]),
              ...((data?.wallets?.sms ?? []) as Row[]).map((w): [string, string] => [`${t('b.smsWallet')} · ${w.domain}`, f.money(w.balance)]),
            ]}
          />
          {!(data?.wallets?.general ?? []).length && !(data?.wallets?.sms ?? []).length ? <Empty /> : null}
        </HubCard>
        <HubCard title={t('b.contracts')}>
          <DataTable
            rows={data?.contracts ?? []}
            cols={[
              { key: 'title', label: t('doc.title') }, { key: 'amount', label: t('b.amount'), render: (r) => f.money(r.amount) },
              { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} /> }, { key: 'signed_at', label: t('b.date'), render: (r) => f.date(r.signed_at) },
            ]}
          />
          {(data?.installments?.open ?? []).length ? (
            <div className="mt-3">
              <p className="mb-1 text-xs text-muted-foreground">{t('b.installments')}</p>
              <DataTable rows={data?.installments?.open ?? []} cols={[{ key: 'due_date', label: t('a.dueDate'), render: (r) => f.date(r.due_date) }, { key: 'amount', label: t('b.amount'), render: (r) => f.money(r.amount) }]} />
            </div>
          ) : null}
        </HubCard>
      </div>
      <HubCard title={t('b.ledger')}>
        <DataTable
          rows={led.rows}
          cols={[
            { key: 'date', label: t('b.date'), render: (r) => f.dateTime(r.date) }, { key: 'wallet', label: t('i.kind'), render: (r) => (r.wallet === 'sms' ? t('b.smsWallet') : t('b.generalWallet')) },
            { key: 'domain', label: t('s.domain'), render: (r) => <span dir="ltr">{r.domain}</span> },
            { key: 'amount', label: t('b.amount'), render: (r) => <span className={Number(r.amount) < 0 ? 'text-destructive' : 'text-emerald-600'} dir="ltr">{f.money(r.amount)}</span> },
            { key: 'balance_after', label: t('kpi.balance'), render: (r) => f.money(r.balance_after) }, { key: 'note', label: t('a.body') },
          ]}
        />
        <Pager meta={led.meta} onPage={led.setPage} />
      </HubCard>
    </div>
  );
}
