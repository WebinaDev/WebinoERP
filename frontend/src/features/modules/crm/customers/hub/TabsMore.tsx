'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { Check, Pin, Plus, Trash2 } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { hubBase, useHubList, useHubSection, type Row } from './hub-api';
import { useHub } from './HubContext';
import { useRemove } from './TabsCore';
import { DataTable, Empty, HubCard, KV, Loading, Pager, StatusBadge, useFmt } from './ui';

function usePost() {
  const { id, refresh, setNotice } = useHub();
  return async (path: string, body: unknown = {}, method: 'post' | 'patch' = 'post') => {
    try {
      await apiClient[method](`${hubBase(id)}/${path}`, body);
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  };
}

/* ───────────── E. Sales & Projects ───────────── */
export function SalesTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const { data, loading } = useHubSection<Row>(id, 'sales', true, version);
  if (loading && !data) return <Loading />;
  if (!data) return <Empty />;
  return (
    <div className="space-y-4">
      <HubCard title={t('sa.opportunities')}>
        <DataTable rows={data.opportunities ?? []} cols={[
          { key: 'title', label: t('doc.title') }, { key: 'stage', label: t('sa.stage') },
          { key: 'amount', label: t('b.amount'), render: (r) => f.money(r.amount) }, { key: 'probability', label: '%' },
          { key: 'st', label: t('b.status'), render: (r) => <StatusBadge value={r.won_at ? 'paid' : r.lost_at ? 'rejected' : 'open'} label={r.won_at ? '✓' : r.lost_at ? '✗' : '…'} /> },
        ]} />
      </HubCard>
      <div className="grid gap-4 md:grid-cols-2">
        <HubCard title={t('sa.leads')}>
          <DataTable rows={data.leads ?? []} cols={[{ key: 'title', label: t('doc.title') }, { key: 'created_at', label: t('b.date'), render: (r) => f.date(r.created_at) }]} />
        </HubCard>
        <HubCard title={t('sa.proposals')}>
          <DataTable rows={data.proposals ?? []} cols={[{ key: 'id', label: '#' }, { key: 'total', label: t('b.amount'), render: (r) => f.money(r.total) }, { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} /> }, { key: 'date', label: t('b.date'), render: (r) => f.date(r.date) }]} />
        </HubCard>
      </div>
      <HubCard title={t('sa.projects')} actions={canDo('project') ? <Button size="sm" variant="outline" onClick={() => open('project')}><Plus className="me-1 h-4 w-4" />{t('act.project')}</Button> : null}>
        <DataTable rows={data.projects ?? []} cols={[
          { key: 'name', label: t('a.projectName') }, { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'progress', label: t('sa.progress'), render: (r) => <div className="flex w-32 items-center gap-2"><Progress value={Number(r.progress ?? 0)} className="h-2" /><span className="text-xs">{r.progress ?? 0}%</span></div> },
          { key: 'start_date', label: t('l.start'), render: (r) => f.date(r.start_date) }, { key: 'due_date', label: t('a.dueDate'), render: (r) => f.date(r.due_date) },
        ]} />
      </HubCard>
      <div className="grid gap-4 md:grid-cols-3">
        <div className="md:col-span-2">
          <HubCard title={t('sa.tasks')}>
            <DataTable rows={data.open_tasks ?? []} cols={[{ key: 'title', label: t('doc.title') }, { key: 'priority', label: t('a.priority') }, { key: 'due_at', label: t('a.dueDate'), render: (r) => f.date(r.due_at) }]} />
          </HubCard>
        </div>
        <HubCard title={t('sa.time')}>
          <p>{t('sa.billable', { h: data.time?.billable_hours ?? 0 })}</p>
          <p className="text-muted-foreground">{t('sa.nonBillable', { h: data.time?.non_billable_hours ?? 0 })}</p>
        </HubCard>
      </div>
    </div>
  );
}

/* ───────────── F. Support ───────────── */
export function SupportTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const [status, setStatus] = useState('');
  const tickets = useHubList(id, 'tickets', { status }, true, version);
  const comms = useHubList(id, 'communications', {}, true, version);
  return (
    <div className="space-y-4">
      <HubCard
        title={t('su.tickets')}
        actions={
          <>
            <select className="h-8 rounded-md border bg-transparent px-2 text-xs" value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">{t('su.all')}</option>
              {['open', 'pending', 'in_progress', 'resolved', 'closed'].map((x) => <option key={x} value={x}>{x}</option>)}
            </select>
            {canDo('ticket') ? <Button size="sm" variant="outline" onClick={() => open('ticket')}><Plus className="me-1 h-4 w-4" />{t('act.ticket')}</Button> : null}
          </>
        }
      >
        <DataTable rows={tickets.rows} cols={[
          { key: 'id', label: '#' }, { key: 'subject', label: t('a.subject') },
          { key: 'status', label: t('b.status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'priority', label: t('su.priority'), render: (r) => (['low', 'normal', 'high', 'urgent'].includes(r.priority) ? t(`pr.${r.priority}` as never) : r.priority ?? '—') },
          { key: 'department', label: t('su.dept') },
          { key: 'sla', label: t('su.sla'), render: (r) => (r.sla_breached ? <Badge variant="destructive">{t('su.breached')}</Badge> : f.dateTime(r.sla_resolve_due_at)) },
          { key: 'rating', label: t('su.rating'), render: (r) => (r.rating ?? r.satisfaction_rating ? '★'.repeat(Number(r.rating ?? r.satisfaction_rating)) : '—') },
          { key: 'created_at', label: t('b.date'), render: (r) => f.dateTime(r.created_at) },
        ]} />
        <Pager meta={tickets.meta} onPage={tickets.setPage} />
      </HubCard>
      <HubCard title={t('su.comms')}>
        <DataTable rows={comms.rows} cols={[
          { key: 'date', label: t('b.date'), render: (r) => f.dateTime(r.date) }, { key: 'type', label: t('i.kind'), render: (r) => <Badge variant="outline">{r.type}</Badge> },
          { key: 'subject', label: t('a.subject') }, { key: 'body', label: t('a.body'), render: (r) => <span className="line-clamp-1 max-w-xs whitespace-normal">{r.body ?? '—'}</span> },
          { key: 'outcome', label: t('b.status') },
        ]} />
        <Pager meta={comms.meta} onPage={comms.setPage} />
      </HubCard>
    </div>
  );
}

const TL_KINDS = ['invoice', 'payment', 'ticket', 'site', 'deploy', 'license', 'license_renewal', 'note', 'call', 'meeting', 'sms', 'email', 'login', 'audit'];

export function TimelineTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version } = useHub();
  const [kind, setKind] = useState('');
  const list = useHubList(id, 'timeline', { kind }, true, version);
  return (
    <HubCard
      title={t('tab.timeline')}
      actions={
        <select className="h-8 rounded-md border bg-transparent px-2 text-xs" value={kind} onChange={(e) => setKind(e.target.value)}>
          <option value="">{t('tl.all')}</option>
          {TL_KINDS.map((k) => <option key={k} value={k}>{k}</option>)}
        </select>
      }
    >
      {list.rows.length === 0 ? <Empty /> : (
        <ol className="relative space-y-3 border-s ps-4">
          {list.rows.map((e, i) => (
            <li key={`${e.ref}-${i}`} className="relative">
              <span className="absolute -start-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-primary" />
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{e.kind}</Badge>
                <span className="font-medium">{e.title}</span>
                {e.status ? <StatusBadge value={e.status} /> : null}
              </div>
              <div className="text-xs text-muted-foreground">{f.dateTime(e.date)}</div>
            </li>
          ))}
        </ol>
      )}
      <Pager meta={list.meta} onPage={list.setPage} />
    </HubCard>
  );
}

/* ───────────── G. Usage ───────────── */
export function UsageTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const { data } = useHubSection<Row>(id, 'usage', true, version);
  if (!data) return <Loading />;
  return (
    <div className="space-y-4">
      <HubCard title={t('tab.usage')} actions={canDo('usage') ? <Button size="sm" variant="outline" onClick={() => open('usage')}><Plus className="me-1 h-4 w-4" />{t('add')}</Button> : null}>
        {!data.server_metrics_connected ? <p className="mb-3 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">{t('u.notConnected')}</p> : null}
        <KV items={[
          [t('u.smsSent'), f.num(data.sms?.sent)], [t('u.smsCredit'), f.money(data.sms?.remaining_credit)], [t('u.orders'), f.num(data.orders)],
          [t('u.traffic'), f.num(data.traffic_mb)], [t('u.storage'), f.num(data.storage_mb)], [t('u.bandwidth'), f.num(data.bandwidth_mb)],
          [t('u.cpu'), data.cpu_pct ?? null], [t('u.ram'), f.num(data.ram_mb)], [t('u.panelUsers'), f.num(data.panel_users)],
          [t('u.lastLogin'), f.dateTime(data.last_login_at)],
        ]} />
      </HubCard>
      <HubCard title={t('u.modules')}>
        {(data.modules ?? []).length === 0 ? <Empty /> : (data.modules as Row[]).map((s) => (
          <div key={s.domain} className="mb-2 flex flex-wrap items-center gap-1">
            <span className="me-2 font-medium" dir="ltr">{s.domain}</span>
            {(s.modules as Row[]).map((m) => <Badge key={m.slug} variant={m.status === 'active' || m.status === 'installed' ? 'default' : 'outline'}>{m.slug}</Badge>)}
            {!(s.modules as Row[]).length ? <span className="text-xs text-muted-foreground">—</span> : null}
          </div>
        ))}
      </HubCard>
    </div>
  );
}

/* ───────────── H. Documents ───────────── */
export function DocumentsTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo, open } = useHub();
  const list = useHubList(id, 'documents', {}, true, version);
  const remove = useRemove();
  const post = usePost();
  return (
    <HubCard title={t('tab.documents')} actions={canDo('document') ? <Button size="sm" variant="outline" onClick={() => open('document')}><Plus className="me-1 h-4 w-4" />{t('act.document')}</Button> : null}>
      <DataTable rows={list.rows} cols={[
        { key: 'category', label: t('doc.category'), render: (r) => t(`dc.${['national_card', 'gazette', 'enamad', 'permit', 'contract', 'proposal', 'invoice', 'brief', 'deliverable'].includes(r.category) ? r.category : 'other'}` as never) },
        { key: 'title', label: t('doc.title'), render: (r) => (r.url ? <a className="text-primary underline" href={r.url} target="_blank" rel="noreferrer">{r.title}</a> : r.title) },
        { key: 'expires_at', label: t('doc.expires'), render: (r) => <span>{f.date(r.expires_at)}{r.expired ? <Badge className="ms-1" variant="destructive">{t('doc.expired')}</Badge> : null}</span> },
        { key: 'review_status', label: t('doc.review'), render: (r) => <StatusBadge value={r.review_status} /> },
        { key: 'visible_to_roles', label: t('c.role'), render: (r) => (Array.isArray(r.visible_to_roles) && r.visible_to_roles.length ? r.visible_to_roles.join('، ') : '—') },
        {
          key: 'ops', label: '', render: (r) => canDo('document') && r.source === 'hub' ? (
            <div className="flex gap-1">
              {r.review_status !== 'approved' ? <Button size="sm" variant="ghost" onClick={() => void post(`documents/${r.id}`, { review_status: 'approved' }, 'patch')}>{t('doc.approve')}</Button> : null}
              {r.review_status !== 'rejected' ? <Button size="sm" variant="ghost" onClick={() => void post(`documents/${r.id}`, { review_status: 'rejected' }, 'patch')}>{t('doc.reject')}</Button> : null}
              <Button size="icon" variant="ghost" onClick={() => void remove(`documents/${r.id}`)}><Trash2 className="h-4 w-4" /></Button>
            </div>
          ) : null,
        },
      ]} />
      <Pager meta={list.meta} onPage={list.setPage} />
    </HubCard>
  );
}

/* ───────────── I. Access & Security ───────────── */
export function AccessTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, canDo } = useHub();
  const { data } = useHubSection<Row>(id, 'access', true, version);
  const post = usePost();
  const [ips, setIps] = useState<string | null>(null);
  if (!data) return <Loading />;
  const ipText = ips ?? ((data.allowed_ips ?? []) as string[]).join('\n');
  return (
    <div className="space-y-4">
      <HubCard title={t('ac.users')}>
        <DataTable rows={data.users ?? []} cols={[
          { key: 'name', label: t('ac.portalName'), render: (r) => <span>{r.name}{r.is_primary ? <Badge className="ms-1" variant="secondary">{t('c.default')}</Badge> : null}</span> },
          { key: 'email', label: t('c.email'), render: (r) => <span dir="ltr">{r.email}</span> },
          { key: 'roles', label: t('c.role'), render: (r) => (r.roles ?? []).join('، ') || '—' },
          { key: 'last_login_at', label: t('u.lastLogin'), render: (r) => f.dateTime(r.last_login_at) },
          { key: 'two_factor', label: t('ac.twoFa'), render: (r) => (r.two_factor === null ? t('ac.twoFaNA') : r.two_factor ? '✓' : '✗') },
        ]} />
      </HubCard>
      <div className="grid gap-4 md:grid-cols-2">
        <HubCard title={t('ac.invites')}>
          <DataTable rows={data.pending_invites ?? []} cols={[{ key: 'email', label: t('c.email'), render: (r) => <span dir="ltr">{r.email}</span> }, { key: 'invited_at', label: t('b.date'), render: (r) => f.date(r.invited_at) }]} />
        </HubCard>
        <HubCard title={t('ac.tokens')}>
          <DataTable rows={data.api_tokens ?? []} cols={[{ key: 'name', label: t('doc.title') }, { key: 'last_used_at', label: t('ac.lastUsed'), render: (r) => (r.last_used_at ? f.dateTime(r.last_used_at) : t('ac.never')) }, { key: 'expires_at', label: t('l.expires'), render: (r) => f.date(r.expires_at) }]} />
        </HubCard>
      </div>
      <HubCard title={t('ac.ips')}>
        <textarea dir="ltr" className="h-24 w-full rounded-md border bg-transparent p-2 font-mono text-xs" disabled={!canDo('profile')} placeholder={t('ac.ipsHint')} value={ipText} onChange={(e) => setIps(e.target.value)} />
        {canDo('profile') ? <Button size="sm" className="mt-2" onClick={() => void post('profile', { allowed_ips: ipText.split(/\s+/).filter(Boolean) }, 'patch')}>{t('save')}</Button> : null}
      </HubCard>
      <HubCard title={t('ac.audit')}>
        <DataTable rows={data.audit ?? []} cols={[
          { key: 'created_at', label: t('b.date'), render: (r) => f.dateTime(r.created_at) }, { key: 'action', label: t('i.kind'), render: (r) => <Badge variant="outline">{r.action}</Badge> },
          { key: 'user_id', label: t('owner') }, { key: 'changes', label: t('a.body'), render: (r) => <code className="block max-w-md truncate text-xs" dir="ltr">{JSON.stringify(r.changes)}</code> },
        ]} />
      </HubCard>
    </div>
  );
}

/* ───────────── J. Relations ───────────── */
export function RelationsTab() {
  const t = useTranslations('crm.hub');
  const { id, version, canDo, open } = useHub();
  const { data } = useHubSection<Row>(id, 'relations', true, version);
  const remove = useRemove();
  if (!data) return <Loading />;
  const link = (a: Row | null | undefined) => (a ? <a className="text-primary underline" href={`./${a.id}`}>{a.name ?? `#${a.id}`}</a> : null);
  const list = (rows: Row[] | undefined) => (rows?.length ? <div className="flex flex-wrap gap-2">{rows.map((r) => <span key={r.id}>{link(r)}</span>)}</div> : null);
  return (
    <div className="space-y-4">
      <HubCard title={t('tab.relations')}>
        <KV items={[
          [t('r.parent'), link(data.parent)], [t('r.children'), list(data.children)], [t('r.referrer'), link(data.referrer)],
          [t('r.referred'), list(data.referred)], [t('r.partner'), link(data.partner)],
          [t('r.lead'), data.originating_lead ? `#${data.originating_lead.id} ${data.originating_lead.topic ?? ''}` : null],
        ]} />
      </HubCard>
      <HubCard title={t('r.related')} actions={canDo('relation') ? <Button size="sm" variant="outline" onClick={() => open('relation')}><Plus className="me-1 h-4 w-4" />{t('act.relation')}</Button> : null}>
        <DataTable rows={data.related ?? []} cols={[
          { key: 'name', label: t('f.name'), render: (r) => link({ id: r.account_id, name: r.name }) },
          { key: 'type', label: t('r.type'), render: (r) => (['holding', 'related', 'partner', 'referral'].includes(r.type) ? t(`rt.${r.type}` as never) : r.type) },
          { key: 'note', label: t('a.body') },
          { key: 'ops', label: '', render: (r) => (canDo('relation') ? <Button size="icon" variant="ghost" onClick={() => void remove(`relations/${r.id}`)}><Trash2 className="h-4 w-4" /></Button> : null) },
        ]} />
      </HubCard>
    </div>
  );
}

/* ───────────── K. Notes / alerts / onboarding ───────────── */
export function NotesTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, summary, version, canDo, open } = useHub();
  const { data: fresh } = useHubSection<Row>(id, 'notes', true, version);
  const data = fresh ?? summary.notes;
  const post = usePost();
  if (!data) return <Loading />;
  const onboarding = (data.onboarding ?? []) as Row[];
  const done = onboarding.filter((o) => o.done_at || o.is_done).length;
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2">
        <HubCard title={t('n.pinned')} actions={canDo('pinned') ? <Button size="sm" variant="outline" onClick={() => open('pinned')}><Pin className="me-1 h-4 w-4" />{t('add')}</Button> : null}>
          {(data.pinned ?? []).length === 0 ? <Empty /> : (data.pinned as Row[]).map((n) => (
            <div key={n.id} className="mb-2 flex items-start justify-between gap-2 rounded-md border border-amber-300/50 bg-amber-50 p-2 dark:bg-amber-950/20">
              <p className="whitespace-pre-wrap">{n.body}</p>
              {canDo('pinned') ? <Button size="icon" variant="ghost" onClick={() => void post(`alerts/${n.id}/resolve`)}><Check className="h-4 w-4" /></Button> : null}
            </div>
          ))}
        </HubCard>
        <HubCard title={t('n.alerts')} actions={canDo('risk') ? <Button size="sm" variant="outline" onClick={() => open('risk')}><Plus className="me-1 h-4 w-4" />{t('add')}</Button> : null}>
          {(data.alerts ?? []).filter((a: Row) => a.kind === 'risk').length === 0 ? <Empty /> : (data.alerts as Row[]).filter((a) => a.kind === 'risk').map((a, i) => (
            <div key={String(a.id ?? i)} className="mb-2 flex items-center justify-between gap-2 rounded-md border p-2">
              <div className="flex items-center gap-2">
                <StatusBadge value={a.severity} label={t(`sev.${['info', 'warning', 'critical'].includes(a.severity) ? a.severity : 'info'}` as never)} />
                <span>{a.code ? t(`alert.${a.code}` as never) : a.body}</span>
                {a.value !== undefined && typeof a.value !== 'object' ? <span className="text-xs text-muted-foreground" dir="ltr">{String(a.value)}</span> : null}
              </div>
              {a.stored && canDo('risk') ? <Button size="sm" variant="ghost" onClick={() => void post(`alerts/${a.id}/resolve`)}>{t('n.resolve')}</Button> : null}
            </div>
          ))}
        </HubCard>
      </div>
      <HubCard
        title={`${t('n.onboarding')} (${done}/${onboarding.length})`}
        actions={canDo('pinned') && onboarding.length === 0 ? <Button size="sm" variant="outline" onClick={() => void post('onboarding', { preset: true })}>{t('n.preset')}</Button> : null}
      >
        {onboarding.length ? <Progress value={onboarding.length ? (done / onboarding.length) * 100 : 0} className="mb-3 h-2" /> : <Empty />}
        <ul className="space-y-1">
          {onboarding.map((o) => (
            <li key={o.id}>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={Boolean(o.done_at || o.is_done)} disabled={!canDo('pinned')} onChange={() => void post(`onboarding/${o.id}/toggle`)} />
                <span className={o.done_at || o.is_done ? 'text-muted-foreground line-through' : ''}>{o.title}</span>
              </label>
            </li>
          ))}
        </ul>
      </HubCard>
      <HubCard title={t('n.recent')} actions={canDo('note') ? <Button size="sm" variant="outline" onClick={() => open('note')}><Plus className="me-1 h-4 w-4" />{t('act.note')}</Button> : null}>
        {(data.recent_notes ?? []).length === 0 ? <Empty /> : (data.recent_notes as Row[]).map((n) => (
          <div key={n.id} className="mb-2 border-b pb-2 last:border-0">
            <p className="whitespace-pre-wrap">{n.description ?? n.subject}</p>
            <span className="text-xs text-muted-foreground">{f.dateTime(n.created_at)}</span>
          </div>
        ))}
      </HubCard>
    </div>
  );
}

/* ───────────── L. Analytics ───────────── */
export function AnalyticsTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version } = useHub();
  const { data } = useHubSection<Row>(id, 'analytics', true, version);
  if (!data) return <Loading />;
  const chart = (rows: Row[] | null | undefined, kind: 'bar' | 'line') =>
    rows && rows.length ? (
      <div className="h-56 w-full" dir="ltr">
        <ResponsiveContainer width="100%" height="100%">
          {kind === 'bar' ? (
            <BarChart data={rows}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="month" fontSize={11} /><YAxis fontSize={11} /><Tooltip /><Bar dataKey="value" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} /></BarChart>
          ) : (
            <LineChart data={rows}><CartesianGrid strokeDasharray="3 3" /><XAxis dataKey="month" fontSize={11} /><YAxis fontSize={11} allowDecimals={false} /><Tooltip /><Line type="monotone" dataKey="value" stroke="hsl(var(--primary))" strokeWidth={2} /></LineChart>
          )}
        </ResponsiveContainer>
      </div>
    ) : <Empty />;
  const hb = (data.health?.breakdown ?? {}) as Record<string, Row>;
  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-3">
        <HubCard title={t('an.renewal')}><div className="text-3xl font-bold text-emerald-600">{data.renewal_probability ?? '—'}%</div></HubCard>
        <HubCard title={t('an.churn')}><div className="text-3xl font-bold text-destructive">{data.churn_risk ?? '—'}%</div></HubCard>
        <HubCard title={t('an.ltv')}>
          {data.ltv ? <KV items={[[t('an.ltvHist'), f.money(data.ltv.historical)], [t('an.ltvProj'), f.money(data.ltv.projected_24m ?? data.ltv.projected)]]} /> : <Empty text={t('kpi.hidden')} />}
        </HubCard>
      </div>
      <HubCard title={t('an.health')}>
        <div className="space-y-2">
          {Object.entries(hb).map(([k, v]) => (
            <div key={k} className="flex items-center gap-3">
              <span className="w-24 text-xs">{t(`hb.${k}` as never)}</span>
              <Progress value={(Number(v.score) / Number(v.max)) * 100} className="h-2 flex-1" />
              <span className="w-14 text-xs" dir="ltr">{v.score}/{v.max}</span>
            </div>
          ))}
        </div>
      </HubCard>
      <div className="grid gap-4 lg:grid-cols-2">
        <HubCard title={t('an.revenue')}>{data.revenue_monthly ? chart(data.revenue_monthly, 'bar') : <Empty text={t('kpi.hidden')} />}</HubCard>
        <HubCard title={t('an.tickets')}>{chart(data.tickets_monthly, 'line')}</HubCard>
        <HubCard title={t('an.sms')}>{chart(data.sms_monthly, 'bar')}</HubCard>
        <HubCard title={t('an.supportCost')}>
          {data.support_cost_vs_revenue ? (
            <KV items={[[t('an.supportHours', { h: data.support_cost_vs_revenue.hours }), f.money(data.support_cost_vs_revenue.estimated_cost)], [t('kpi.revenue12m'), f.money(data.support_cost_vs_revenue.revenue_12m)]]} />
          ) : <Empty text={t('kpi.hidden')} />}
        </HubCard>
      </div>
    </div>
  );
}
