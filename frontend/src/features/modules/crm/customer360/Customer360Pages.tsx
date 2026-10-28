'use client';

import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Download, FileUp, GitMerge, X } from 'lucide-react';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { DataTable, Empty, KV, Loading, Pager, StatusBadge, useFmt } from '../customers/hub/ui';
import type { Row } from '../customers/hub/hub-api';
import { DetailSheet, FileDrop, FilterBar, Kpi, TabForm, selectCls } from '../customers/hub/tabs360/shared';
import {
  IMPORT_MAX_BYTES, IO_ENTITIES, PORTAL_SECTIONS, REPORT_SECTIONS, dismissDuplicate, downloadImportTemplate, exportCustomers, getDuplicates, getReport,
  importCustomers, mergeCustomers, portal, retryAfter,
  type DupRes, type DupRow, type ImportResult, type IoEntity, type IoFormat, type PortalContractDetail, type ReportData, type ReportFilters, type ReportSection,
} from './phase5-api';

type T = ReturnType<typeof useTranslations>;
const R = (rows: unknown) => rows as Row[];
const money = (f: ReturnType<typeof useFmt>, v: number | null | undefined) => (v === null || v === undefined ? '—' : f.money(v));

/** Load helper: data + loading + error + reload. */
function useLoad<D>(fn: () => Promise<D>, deps: unknown[]) {
  const [data, setData] = useState<D | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const load = useCallback(async () => {
    setLoading(true);
    try { setData(await fn()); setError(null); } catch (e) { setData(null); setError(getApiErrorMessage(e)); } finally { setLoading(false); }
  }, deps); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => { void load(); }, [load]);
  return { data, loading, error, reload: load };
}

function Body({ loading, error, empty, children }: { loading: boolean; error: string | null; empty?: boolean; children: ReactNode }) {
  if (loading) return <Loading />;
  if (error) return <p className="text-sm text-destructive" role="alert">{error}</p>;
  if (empty) return <Empty />;
  return <>{children}</>;
}

function Tabs<K extends string>({ title, keys, cur, setCur, label, extra }: { title: string; keys: readonly K[]; cur: K; setCur: (k: K) => void; label: (k: K) => string; extra?: ReactNode }) {
  return (
    <>
      <div className="flex flex-wrap items-center justify-between gap-2"><h1 className="text-xl font-bold">{title}</h1>{extra}</div>
      <div className="flex flex-wrap gap-1" role="tablist">
        {keys.map((k) => <Button key={k} role="tab" aria-selected={cur === k} size="sm" variant={cur === k ? 'default' : 'outline'} onClick={() => setCur(k)}>{label(k)}</Button>)}
      </div>
    </>
  );
}

// ======================= Portal =======================
/** Customer-facing portal: profile, contracts (OTP signing), invoices (pay), services, documents, tickets. */
export function Customer360PortalPage() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p5');
  const [cur, setCur] = useState<(typeof PORTAL_SECTIONS)[number]>('profile');
  const [acc, setAcc] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const prof = useLoad(() => portal.profile(acc), [acc]);
  // Gateway return: verify payment_id server-side, never trust the query string alone.
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const q = new URLSearchParams(window.location.search);
    const pid = q.get('payment_id');
    if (!pid) return;
    portal.payment(pid).then((r) => { setNotice(r.paid ? p('paySuccess') : p('payFailed', { status: r.status })); setCur('invoices'); })
      .catch((e) => setNotice(getApiErrorMessage(e)));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const accounts = prof.data?.accounts ?? [];
  return (
    <div dir="rtl" className="space-y-4 p-4">
      <Tabs title={t('portal')} keys={PORTAL_SECTIONS} cur={cur} setCur={setCur} label={(k) => t(k as never)}
        extra={accounts.length > 1 ? (
          <select aria-label={p('account')} className={`${selectCls} w-56`} value={acc ?? ''} onChange={(e) => setAcc(e.target.value ? Number(e.target.value) : null)}>
            <option value="">{p('primaryAccount')}</option>
            {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
          </select>
        ) : null} />
      {notice ? <p className="rounded-md border bg-muted p-2 text-sm" data-testid="portal-notice">{notice}</p> : null}
      <Card>
        <CardHeader><CardTitle className="text-base">{t(cur as never)}</CardTitle></CardHeader>
        <CardContent>
          {cur === 'profile' ? <Body loading={prof.loading} error={prof.error} empty={!prof.data}><ProfileView p={p} a={prof.data?.account} /></Body> : null}
          {cur === 'contracts' ? <PortalContracts acc={acc} setNotice={setNotice} /> : null}
          {cur === 'invoices' ? <PortalInvoices acc={acc} setNotice={setNotice} /> : null}
          {cur === 'services' ? <PortalServices acc={acc} /> : null}
          {cur === 'documents' ? <PortalDocuments acc={acc} /> : null}
          {cur === 'tickets' ? <PortalTickets acc={acc} /> : null}
        </CardContent>
      </Card>
    </div>
  );
}

function ProfileView({ p, a }: { p: T; a?: import('./phase5-api').PortalAccount }) {
  if (!a) return null;
  return <KV items={[
    [p('name'), a.name], [p('brand'), a.brand_name], [p('nationalId'), a.national_id ? <span key="n" dir="ltr">{a.national_id}</span> : null],
    [p('economicCode'), a.economic_code], [p('mobile'), a.mobile ? <span key="m" dir="ltr">{a.mobile}</span> : null],
    [p('email'), a.email ? <span key="e" dir="ltr">{a.email}</span> : null], [p('website'), a.website ? <span key="w" dir="ltr">{a.website}</span> : null],
    [p('address'), [a.province, a.city, a.address_line].filter(Boolean).join('، ') || null], [p('postalCode'), a.postal_code],
    [p('status'), <StatusBadge key="s" value={a.hub_status} />],
  ]} />;
}

function PortalContracts({ acc, setNotice }: { acc: number | null; setNotice: (s: string) => void }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const list = useLoad(() => portal.contracts(acc), [acc]);
  const [detail, setDetail] = useState<PortalContractDetail | null>(null);
  const [signing, setSigning] = useState<number | null>(null);
  const [sent, setSent] = useState<string | null>(null);
  const [wait, setWait] = useState(0);
  useEffect(() => { if (wait <= 0) return; const h = setTimeout(() => setWait((w) => w - 1), 1000); return () => clearTimeout(h); }, [wait]);
  const sendOtp = async (id: number) => {
    try { const r = await portal.sendOtp(id, acc); setSent(r.sent_to); setWait(r.resend_in); } catch (e) {
      const ra = retryAfter(e);
      if (ra) { setWait(ra); setNotice(p('otpWait', { s: ra })); } else setNotice(getApiErrorMessage(e));
      throw e;
    }
  };
  const startSign = async (id: number) => { setSent(null); try { await sendOtp(id); setSigning(id); } catch { /* notice set */ } };
  return (
    <Body loading={list.loading} error={list.error} empty={!list.data?.length}>
      <DataTable rows={R(list.data)} cols={[
        { key: 'title', label: p('title'), render: (r) => <button type="button" className="text-primary hover:underline" onClick={() => void portal.contract(r.id, acc).then(setDetail).catch((e) => setNotice(getApiErrorMessage(e)))}><span dir="auto">{r.title}</span></button> },
        { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
        { key: 'amount', label: p('amount'), render: (r) => money(f, r.amount) },
        { key: 'starts_at', label: p('startsAt'), render: (r) => f.date(r.starts_at) },
        { key: 'ends_at', label: p('endsAt'), render: (r) => f.date(r.ends_at) },
        { key: 'signed_at', label: p('signedAt'), render: (r) => f.date(r.signed_at) },
        { key: 'act', label: '', render: (r) => (r.can_sign ? <Button size="sm" onClick={() => void startSign(r.id)}>{p('sign')}</Button> : null) },
      ]} />
      <DetailSheet open={!!detail} onOpenChange={(v) => !v && setDetail(null)} title={detail?.title ?? ''}>
        {detail ? (
          <>
            {detail.body ? <div className="whitespace-pre-wrap rounded-md border p-3 text-sm" dir="auto">{detail.body}</div> : null}
            <DataTable rows={R(detail.installments)} cols={[
              { key: 'due_date', label: p('dueDate'), render: (r) => f.date(r.due_date) },
              { key: 'amount', label: p('amount'), render: (r) => money(f, r.amount) },
              { key: 'paid_at', label: p('paidAt'), render: (r) => f.date(r.paid_at) },
            ]} />
          </>
        ) : null}
      </DetailSheet>
      <TabForm open={signing !== null} onOpenChange={(v) => !v && setSigning(null)} title={p('signTitle')} submitLabel={p('sign')}
        fields={[{ name: 'code', label: p('otpCode'), required: true, hint: sent ? p('otpSentTo', { to: sent }) : undefined }]}
        onSubmit={async (v) => {
          if (signing === null) return;
          if (v.accept !== '1') throw { response: { data: { message: p('mustAccept'), errors: { code: [p('mustAccept')] } } } };
          await portal.sign(signing, String(v.code).trim(), acc);
          setNotice(p('signed')); void list.reload();
        }}>
        {(v, set) => (
          <div className="space-y-2 text-sm">
            <label className="flex items-center gap-2"><input type="checkbox" checked={v.accept === '1'} onChange={(e) => set('accept', e.target.checked ? '1' : '')} />{p('accept')}</label>
            <Button size="sm" variant="outline" disabled={wait > 0} onClick={() => signing !== null && void sendOtp(signing).catch(() => undefined)}>
              {wait > 0 ? p('resendIn', { s: wait }) : p('resend')}
            </Button>
          </div>
        )}
      </TabForm>
    </Body>
  );
}

function PortalInvoices({ acc, setNotice }: { acc: number | null; setNotice: (s: string) => void }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const [status, setStatus] = useState<'open' | 'paid' | 'all'>('open');
  const list = useLoad(() => portal.invoices(status, acc), [status, acc]);
  const [busy, setBusy] = useState<number | null>(null);
  const pay = async (id: number) => {
    setBusy(id);
    try { const r = await portal.pay(id, {}, acc); window.location.assign(r.redirect_url); } catch (e) { setNotice(getApiErrorMessage(e)); setBusy(null); }
  };
  const k = list.data?.meta?.kpis;
  return (
    <>
      <FilterBar>
        <select aria-label={p('status')} className={`${selectCls} w-40`} value={status} onChange={(e) => setStatus(e.target.value as typeof status)}>
          {(['open', 'paid', 'all'] as const).map((s) => <option key={s} value={s}>{p(`inv.${s}` as never)}</option>)}
        </select>
      </FilterBar>
      {k ? <div className="mb-3 grid grid-cols-2 gap-2">
        {k.outstanding !== null ? <Kpi label={p('outstanding')} value={f.money(k.outstanding)} /> : null}
        {k.overdue !== null ? <Kpi label={p('overdue')} value={f.money(k.overdue)} tone="danger" /> : null}
      </div> : null}
      <Body loading={list.loading} error={list.error} empty={!list.data?.data.length}>
        <DataTable rows={R(list.data?.data)} cols={[
          { key: 'number', label: p('number'), render: (r) => <span dir="ltr">{r.number}</span> },
          { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'issue_date', label: p('issueDate'), render: (r) => f.date(r.issue_date) },
          { key: 'due_date', label: p('dueDate'), render: (r) => f.date(r.due_date) },
          { key: 'total', label: p('total'), render: (r) => money(f, r.total) },
          { key: 'remaining', label: p('remaining'), render: (r) => money(f, r.remaining) },
          { key: 'act', label: '', render: (r) => (r.payable ? <Button size="sm" disabled={busy === r.id} onClick={() => void pay(r.id)}>{p('pay')}</Button> : null) },
        ]} />
      </Body>
    </>
  );
}

function PortalServices({ acc }: { acc: number | null }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const s = useLoad(() => portal.services(acc), [acc]);
  return (
    <Body loading={s.loading} error={s.error} empty={!s.data?.data.length && !s.data?.meta?.domains?.length}>
      <DataTable rows={R(s.data?.data)} cols={[
        { key: 'name', label: p('name'), render: (r) => <span dir="auto">{r.name}</span> },
        { key: 'kind', label: p('kind') }, { key: 'plan', label: p('plan') },
        { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
        { key: 'site_domain', label: p('domain'), render: (r) => <span dir="ltr">{r.site_domain ?? '—'}</span> },
        { key: 'renews_at', label: p('renewsAt'), render: (r) => f.date(r.renews_at) },
      ]} />
      {s.data?.meta?.domains?.length ? (
        <div className="mt-4"><div className="mb-1 text-sm font-medium">{p('domains')}</div>
          <DataTable rows={R(s.data.meta.domains)} cols={[
            { key: 'domain', label: p('domain'), render: (r) => <span dir="ltr">{r.domain}</span> },
            { key: 'expires_at', label: p('expiresAt'), render: (r) => f.date(r.expires_at) },
            { key: 'auto_renew', label: p('autoRenew'), render: (r) => (r.auto_renew ? p('yes') : p('no')) },
          ]} /></div>
      ) : null}
    </Body>
  );
}

function PortalDocuments({ acc }: { acc: number | null }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const d = useLoad(() => portal.documents(acc), [acc]);
  return (
    <Body loading={d.loading} error={d.error} empty={!d.data?.length}>
      <DataTable rows={R(d.data)} cols={[
        { key: 'title', label: p('title'), render: (r) => <span dir="auto">{r.title}</span> },
        { key: 'category', label: p('category') },
        { key: 'created_at', label: p('createdAt'), render: (r) => f.date(r.created_at) },
        { key: 'expires_at', label: p('expiresAt'), render: (r) => f.date(r.expires_at) },
        { key: 'dl', label: '', render: (r) => <a className="inline-flex items-center gap-1 text-primary hover:underline" href={r.download_url} target="_blank" rel="noreferrer"><Download className="h-4 w-4" />{p('download')}</a> },
      ]} />
    </Body>
  );
}

function PortalTickets({ acc }: { acc: number | null }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const s = useLoad(() => portal.summary(acc), [acc]);
  const d = (s.data?.data ?? s.data) as Record<string, unknown> | null;
  return (
    <Body loading={s.loading} error={s.error}>
      <Kpi label={p('openTickets')} value={f.num(d?.open_tickets ?? 0)} />
      <p className="mt-2 text-xs text-muted-foreground">{p('ticketsHint')}</p>
    </Body>
  );
}

// ======================= BI reports =======================
/** BI: LTV, debt, churn, cohorts, top customers, segments. */
export function Customer360ReportsPage() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p5');
  const [cur, setCur] = useState<ReportSection>('ltv');
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [segment, setSegment] = useState('');
  const filters: ReportFilters = { ...(from ? { from: from.slice(0, 10) } : {}), ...(to ? { to: to.slice(0, 10) } : {}), ...(segment.trim() ? { segment: segment.trim() } : {}) };
  const rep = useLoad(() => getReport(cur, filters), [cur, from, to, segment]);
  return (
    <div dir="rtl" className="space-y-4 p-4">
      <Tabs title={t('reports')} keys={REPORT_SECTIONS} cur={cur} setCur={setCur} label={(k) => (k === 'segments' ? p('segments') : t(k as never))} />
      <FilterBar>
        <div className="w-44"><LocaleDatePicker value={from} onChange={(iso) => setFrom(iso || null)} /></div>
        <div className="w-44"><LocaleDatePicker value={to} onChange={(iso) => setTo(iso || null)} /></div>
        <Input className="w-40" placeholder={p('segment')} value={segment} onChange={(e) => setSegment(e.target.value)} />
      </FilterBar>
      <Card>
        <CardHeader><CardTitle className="text-base">{cur === 'segments' ? p('segments') : t(cur as never)}</CardTitle></CardHeader>
        <CardContent><Body loading={rep.loading} error={rep.error} empty={!rep.data}><ReportView section={cur} data={rep.data as never} /></Body></CardContent>
      </Card>
    </div>
  );
}

function ReportView<S extends ReportSection>({ section, data }: { section: S; data: ReportData[S] | null }) {
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  if (!data) return null;
  const m = (v: number | null | undefined) => money(f, v);
  if (section === 'ltv') {
    const d = data as ReportData['ltv'];
    return <><div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
      <Kpi label={p('avgLtv')} value={m(d.avg_ltv)} /><Kpi label={p('medianLtv')} value={m(d.median_ltv)} />
      <Kpi label={p('totalRevenue')} value={m(d.total_revenue)} /><Kpi label={p('customers')} value={f.num(d.customers)} />
    </div><DataTable rows={R(d.buckets)} cols={[
      { key: 'label', label: p('bucket'), render: (r) => <span dir="ltr">{r.to === null ? `≥ ${f.num(r.from)}` : `${f.num(r.from)} – ${f.num(r.to)}`}</span> },
      { key: 'count', label: p('customers'), render: (r) => f.num(r.count) },
    ]} /></>;
  }
  if (section === 'debt') {
    const d = data as ReportData['debt'];
    return <><div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-6">
      <Kpi label={p('total')} value={m(d.total)} />
      {(['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'] as const).map((b) => <Kpi key={b} label={p(`ag.${b}` as never)} value={m(d.buckets?.[b])} tone={b === 'd90_plus' ? 'danger' : undefined} />)}
    </div><DataTable rows={R(d.top_debtors)} cols={[
      { key: 'name', label: p('name'), render: (r) => <span dir="auto">{r.name}</span> },
      { key: 'outstanding', label: p('outstanding'), render: (r) => m(r.outstanding) },
      { key: 'overdue', label: p('overdue'), render: (r) => m(r.overdue) },
      { key: 'oldest_days', label: p('oldestDays'), render: (r) => f.num(r.oldest_days) },
    ]} /></>;
  }
  if (section === 'churn') {
    const d = data as ReportData['churn'];
    return <><div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
      <Kpi label={p('active')} value={f.num(d.active)} /><Kpi label={p('churned')} value={f.num(d.churned)} tone="danger" />
      <Kpi label={p('atRisk')} value={f.num(d.at_risk)} /><Kpi label={p('churnRate')} value={`${f.num(d.churn_rate)}٪`} />
    </div><div className="grid gap-4 md:grid-cols-2">
      <DataTable rows={R(d.by_month)} cols={[{ key: 'month', label: p('month'), render: (r) => <span dir="ltr">{r.month}</span> }, { key: 'new', label: p('new'), render: (r) => f.num(r.new) }, { key: 'churned', label: p('churned'), render: (r) => f.num(r.churned) }]} />
      <DataTable rows={R(d.at_risk_list)} cols={[{ key: 'name', label: p('name'), render: (r) => <span dir="auto">{r.name}</span> }, { key: 'health', label: p('health'), render: (r) => <Badge variant="destructive">{f.num(r.health)}</Badge> }]} />
    </div></>;
  }
  if (section === 'cohorts') {
    const d = data as ReportData['cohorts'];
    if (!d.length) return <Empty />;
    return <DataTable rows={R(d)} cols={[
      { key: 'cohort', label: p('cohort'), render: (r) => <span dir="ltr">{r.cohort}</span> },
      { key: 'size', label: p('size'), render: (r) => f.num(r.size) },
      ...Array.from({ length: 12 }, (_, i) => ({ key: `m${i}`, label: `M${i}`, render: (r: Row) => <span className="tabular-nums">{f.num(r.months?.[i] ?? 0)}٪</span> })),
    ]} />;
  }
  if (section === 'top') {
    return <DataTable rows={R(data)} cols={[
      { key: 'name', label: p('name'), render: (r) => <span dir="auto">{r.name}</span> },
      { key: 'revenue', label: p('revenue'), render: (r) => m(r.revenue) },
      { key: 'invoices', label: p('invoices'), render: (r) => f.num(r.invoices) },
      { key: 'last_paid_at', label: p('lastPaidAt'), render: (r) => f.date(r.last_paid_at) },
    ]} />;
  }
  return <DataTable rows={R(data)} cols={[
    { key: 'segment', label: p('segment'), render: (r) => (r.segment === 'none' ? p('noSegment') : <span dir="auto">{r.segment}</span>) },
    { key: 'customers', label: p('customers'), render: (r) => f.num(r.customers) },
    { key: 'revenue', label: p('revenue'), render: (r) => m(r.revenue) },
    { key: 'avg_ltv', label: p('avgLtv'), render: (r) => m(r.avg_ltv) },
    { key: 'outstanding', label: p('outstanding'), render: (r) => m(r.outstanding) },
  ]} />;
}

// ======================= Duplicates + import/export =======================
/** Duplicate detection / merge + import / export. */
export function Customer360DataPage() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const [minScore, setMinScore] = useState(50);
  const [page, setPage] = useState(1);
  const [notice, setNotice] = useState<string | null>(null);
  const dups = useLoad<DupRes>(() => getDuplicates({ min_score: minScore, page }), [minScore, page]);
  const canMerge = !!dups.data?.meta?.can?.merge;
  const [merge, setMerge] = useState<DupRow | null>(null);
  const brief = (b: DupRow['a']) => <div className="text-xs"><div className="font-medium" dir="auto">{b.name}</div><div className="text-muted-foreground" dir="ltr">{[b.mobile, b.email, b.national_id].filter(Boolean).join(' · ') || '—'}</div></div>;
  return (
    <div dir="rtl" className="space-y-4 p-4">
      {notice ? <p className="rounded-md border bg-muted p-2 text-sm" data-testid="data-notice">{notice}</p> : null}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2"><CardTitle className="text-base">{t('duplicates')}</CardTitle>
          <label className="flex items-center gap-2 text-xs">{p('minScore')}
            <Input type="number" dir="ltr" className="w-20" min={0} max={100} value={minScore} onChange={(e) => { setMinScore(Math.max(0, Math.min(100, Number(e.target.value) || 0))); setPage(1); }} />
          </label>
        </CardHeader>
        <CardContent>
          <Body loading={dups.loading} error={dups.error} empty={!dups.data?.data.length}>
            <DataTable rows={R(dups.data?.data)} cols={[
              { key: 'score', label: p('score'), render: (r) => <Badge variant={r.score >= 80 ? 'destructive' : 'secondary'}>{f.num(r.score)}</Badge> },
              { key: 'a', label: p('customerA'), render: (r) => brief(r.a) },
              { key: 'b', label: p('customerB'), render: (r) => brief(r.b) },
              { key: 'reasons', label: p('reasons'), render: (r) => <div className="flex flex-wrap gap-1">{(r.reasons as string[]).map((x) => <Badge key={x} variant="outline">{p(`reason.${x}` as never)}</Badge>)}</div> },
              ...(canMerge ? [{ key: 'act', label: '', render: (r: Row) => (
                <div className="flex gap-1">
                  <Button size="sm" onClick={() => setMerge(r as DupRow)}><GitMerge className="me-1 h-4 w-4" />{p('merge')}</Button>
                  <Button size="sm" variant="ghost" onClick={() => void dismissDuplicate(r.a.id, r.b.id).then(() => { setNotice(p('dismissed')); void dups.reload(); }).catch((e) => setNotice(getApiErrorMessage(e)))}><X className="me-1 h-4 w-4" />{p('dismiss')}</Button>
                </div>) }] : []),
            ]} />
            <Pager meta={dups.data?.meta ?? null} onPage={setPage} />
          </Body>
        </CardContent>
      </Card>
      <TabForm open={!!merge} onOpenChange={(v) => !v && setMerge(null)} title={p('mergeTitle')} submitLabel={p('merge')}
        initial={merge ? { target: String(merge.suggested_target_id) } : {}}
        fields={merge ? [{ name: 'target', label: p('keep'), type: 'select', required: true, full: true, options: [merge.a, merge.b].map((x) => [String(x.id), `${x.name} (#${x.id})`]) }] : []}
        onSubmit={async (v) => {
          if (!merge) return;
          const target = Number(v.target); const source = target === merge.a.id ? merge.b.id : merge.a.id;
          await mergeCustomers(target, source); setNotice(p('merged')); void dups.reload();
        }}>
        {() => <p className="text-xs text-muted-foreground">{p('mergeHint')}</p>}
      </TabForm>
      <ImportExportCard setNotice={setNotice} />
    </div>
  );
}

function ImportExportCard({ setNotice }: { setNotice: (s: string) => void }) {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p5');
  const f = useFmt();
  const [entity, setEntity] = useState<IoEntity>('customers');
  const [format, setFormat] = useState<IoFormat>('xlsx');
  const [file, setFile] = useState<File | null>(null);
  const [mode, setMode] = useState<'create' | 'upsert'>('upsert');
  const [dry, setDry] = useState(true);
  const [busy, setBusy] = useState(false);
  const [res, setRes] = useState<ImportResult | null>(null);
  const run = async (fn: () => Promise<unknown>) => { setBusy(true); try { await fn(); } catch (e) { setNotice(getApiErrorMessage(e)); } finally { setBusy(false); } };
  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('exportImport')}</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <FilterBar>
          <select aria-label={p('entity')} className={`${selectCls} w-40`} value={entity} onChange={(e) => setEntity(e.target.value as IoEntity)}>
            {IO_ENTITIES.map((x) => <option key={x} value={x}>{p(`ent.${x}` as never)}</option>)}
          </select>
          <select aria-label={p('format')} className={`${selectCls} w-28`} value={format} onChange={(e) => setFormat(e.target.value as IoFormat)}>
            <option value="xlsx">XLSX</option><option value="csv">CSV</option>
          </select>
          <Button size="sm" disabled={busy} onClick={() => void run(() => exportCustomers(entity, format))}><Download className="me-1 h-4 w-4" />{p('export')}</Button>
          <Button size="sm" variant="outline" disabled={busy} onClick={() => void run(() => downloadImportTemplate(entity, format))}>{p('template')}</Button>
        </FilterBar>
        <div className="space-y-2 rounded-md border p-3">
          <div className="text-sm font-medium">{t('import')}</div>
          <FileDrop value={file} onChange={setFile} maxBytes={IMPORT_MAX_BYTES} accept=".csv,.xlsx" />
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <select aria-label={p('mode')} className={`${selectCls} w-44`} value={mode} onChange={(e) => setMode(e.target.value as 'create' | 'upsert')}>
              <option value="upsert">{p('modeUpsert')}</option><option value="create">{p('modeCreate')}</option>
            </select>
            <label className="flex items-center gap-2"><input type="checkbox" checked={dry} onChange={(e) => setDry(e.target.checked)} />{p('dryRun')}</label>
            <Button size="sm" disabled={!file || busy} onClick={() => file && void run(async () => { setRes(await importCustomers(file, entity, mode, dry)); })}><FileUp className="me-1 h-4 w-4" />{dry ? p('validate') : t('import')}</Button>
          </div>
          {res ? (
            <div className="space-y-2" data-testid="import-result">
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <Kpi label={p('total')} value={f.num(res.total)} /><Kpi label={p('created')} value={f.num(res.created)} />
                <Kpi label={p('updated')} value={f.num(res.updated)} /><Kpi label={p('skipped')} value={f.num(res.skipped)} tone={res.skipped ? 'danger' : undefined} />
              </div>
              {dry ? <p className="text-xs text-muted-foreground">{p('dryRunHint')}</p> : null}
              {res.errors.length ? <DataTable rows={R(res.errors)} cols={[
                { key: 'row', label: p('row'), render: (r) => f.num(r.row) }, { key: 'field', label: p('field'), render: (r) => <span dir="ltr">{r.field}</span> },
                { key: 'message', label: p('message'), render: (r) => <span dir="auto">{r.message}</span> },
              ]} /> : null}
            </div>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}
