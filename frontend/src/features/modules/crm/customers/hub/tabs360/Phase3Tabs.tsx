'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { FilePlus2, Link2, MessageSquarePlus, Search, Unlink, Wallet } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { hubBase, useHubList, type Row } from '../hub-api';
import { useHub } from '../HubContext';
import { DataTable, Empty, HubCard, KV, Loading, Pager, StatusBadge, useFmt, type Col } from '../ui';
import { DetailSheet, FilterBar, Kpi, TabForm, body, selectCls, type TField, type TValues } from './shared';
import {
  P3_AREAS, PAYMENT_METHODS, SMS_BODY_MAX, anyMoney, createInvoice, getBasalamStores, recordPayment, getOrder, hasMoney, linkBasalamStore, sendSms, unlinkBasalamStore,
  type AccountingSummaryRes, type BasalamStoresRes, type InvoicesMeta, type LedgerMeta, type OrderDetail, type OrdersMeta, type PaymentMethod, type SmsMeta, type SmsSendBody,
} from './phase3-api';

const Wrap = ({ children }: { children: React.ReactNode }) => <div dir="auto" className="space-y-4">{children}</div>;

/** Drop money columns when the viewer has no billing access (all values null). */
const moneyCols = (cols: (Col & { money?: boolean })[], rows: Row[]) => cols.filter((c) => !c.money || anyMoney(rows, c.key));

// ---------------- Orders ----------------
export function OrdersTab() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p3');
  const f = useFmt();
  const { id, version } = useHub();
  const [source, setSource] = useState('all');
  const [paid, setPaid] = useState('');
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');
  const list = useHubList(id, P3_AREAS.orders.path, { source, paid: paid || undefined, status: status || undefined, q: q.trim() || undefined }, true, version);
  const meta = list.meta as OrdersMeta | null;
  const k = meta?.kpis;
  const [detail, setDetail] = useState<OrderDetail | null>(null);
  const open = async (ref: string) => { try { setDetail(await getOrder(id, ref)); } catch { setDetail(null); } };
  const cols = moneyCols([
    { key: 'number', label: p('number'), render: (r) => <button type="button" className="text-primary underline-offset-2 hover:underline" onClick={() => void open(String(r.ref))}>{r.number}</button> },
    { key: 'source', label: p('source'), render: (r) => <Badge variant="outline">{p(`src.${r.source}` as never)}</Badge> },
    { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
    { key: 'total', label: p('total'), money: true, render: (r) => (hasMoney(r.total) ? f.money(r.total) : '—') },
    { key: 'paid', label: p('paid'), render: (r) => <StatusBadge value={r.paid ? 'paid' : 'unpaid'} label={r.paid ? p('yes') : p('no')} /> },
    { key: 'items_count', label: p('items'), render: (r) => f.num(r.items_count) },
    { key: 'buyer_name', label: p('buyer'), render: (r) => <span dir="auto">{r.buyer_name ?? '—'}</span> },
    { key: 'ordered_at', label: p('orderedAt'), render: (r) => f.date(r.ordered_at) },
    { key: 'paid_at', label: p('paidAt'), render: (r) => f.date(r.paid_at) },
  ], list.rows);
  return (
    <Wrap>
      <HubCard title={t('orders')}>
        {k ? (
          <div className="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
            <Kpi label={p('count')} value={f.num(k.count)} />
            <Kpi label={p('paidCount')} value={f.num(k.paid_count)} />
            {hasMoney(k.total_amount) ? <Kpi label={p('totalAmount')} value={f.money(k.total_amount)} /> : null}
            <Kpi label={p('src.marketplace')} value={f.num(k.marketplace_count)} />
            <Kpi label={p('src.basalam')} value={f.num(k.basalam_count)} />
          </div>
        ) : null}
        <FilterBar>
          <div className="relative"><Search className="absolute start-2 top-2.5 h-4 w-4 text-muted-foreground" /><Input className="ps-8" placeholder={p('searchOrders')} value={q} onChange={(e) => setQ(e.target.value)} /></div>
          <select aria-label={p('source')} className={`${selectCls} w-40`} value={source} onChange={(e) => setSource(e.target.value)}>
            {['all', 'marketplace', 'basalam'].map((s) => <option key={s} value={s}>{p(`src.${s}` as never)}</option>)}
          </select>
          <select aria-label={p('paid')} className={`${selectCls} w-32`} value={paid} onChange={(e) => setPaid(e.target.value)}>
            <option value="">{p('src.all')}</option><option value="1">{p('yes')}</option><option value="0">{p('no')}</option>
          </select>
          <select aria-label={p('orderStatus')} className={`${selectCls} w-40`} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{p('src.all')}</option>
            {[...new Set([...(meta?.statuses ?? []), ...(status ? [status] : [])])].map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </FilterBar>
        {list.loading && !list.rows.length ? <Loading /> : <><DataTable cols={cols} rows={list.rows} /><Pager meta={list.meta} onPage={list.setPage} /></>}
      </HubCard>
      <BasalamStores canLink={!!meta?.can?.link_basalam} />
      <DetailSheet open={!!detail} onOpenChange={(v) => !v && setDetail(null)} title={detail?.number ?? ''}>
        {detail ? (
          <>
            <KV items={[
              [p('source'), p(`src.${detail.source}` as never)], [p('status'), <StatusBadge key="s" value={detail.status} />],
              ...(hasMoney(detail.total) ? [[p('total'), f.money(detail.total)] as [string, string]] : []),
              [p('buyer'), detail.buyer_name], [p('mobile'), detail.buyer_mobile ? <span key="m" dir="ltr">{detail.buyer_mobile}</span> : null],
              [p('gateway'), detail.gateway], [p('paymentRef'), detail.payment_ref], [p('orderedAt'), f.dateTime(detail.ordered_at)], [p('paidAt'), f.dateTime(detail.paid_at)],
              [p('store'), detail.store ? <span key="st" dir="ltr">{detail.store.site_url}</span> : null],
            ]} />
            <DataTable rows={detail.items as unknown as Row[]} cols={moneyCols([
              { key: 'title', label: p('itemTitle'), render: (r) => <span dir="auto">{r.title}</span> },
              { key: 'sku', label: p('sku') },
              { key: 'qty', label: p('qty'), render: (r) => f.num(r.qty) },
              { key: 'unit_price', label: p('unitPrice'), money: true, render: (r) => (hasMoney(r.unit_price) ? f.money(r.unit_price) : '—') },
              { key: 'total', label: p('total'), money: true, render: (r) => (hasMoney(r.total) ? f.money(r.total) : '—') },
            ], detail.items as unknown as Row[])} />
          </>
        ) : null}
      </DetailSheet>
    </Wrap>
  );
}

function BasalamStores({ canLink }: { canLink: boolean }) {
  const p = useTranslations('crm.hub.c360.p3');
  const f = useFmt();
  const { id, version, refresh, setNotice } = useHub();
  const [res, setRes] = useState<BasalamStoresRes | null>(null);
  const load = useCallback(async () => { try { setRes(await getBasalamStores(id)); } catch { setRes(null); } }, [id]);
  useEffect(() => { void load(); }, [load, version]);
  const can = canLink && !!res?.meta.can.link;
  const [force, setForce] = useState<number | null>(null);
  const act = async (fn: () => Promise<unknown>, connectionId?: number) => {
    try { await fn(); await load(); refresh(); } catch (e) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 409 && connectionId !== undefined) setForce(connectionId);
      else setNotice(getApiErrorMessage(e));
    }
  };
  if (!res) return null;
  const all = [...res.data, ...res.meta.candidates];
  return (
    <HubCard title={p('basalamStores')}>
      {!all.length ? <Empty /> : (
        <DataTable rows={all as unknown as Row[]} cols={[
          { key: 'site_url', label: p('store'), render: (r) => <span dir="ltr">{r.site_url}</span> },
          { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'last_seen_at', label: p('lastSeen'), render: (r) => f.date(r.last_seen_at) },
          { key: 'linked', label: p('linked'), render: (r) => (r.linked ? p('yes') : p('candidate')) },
          ...(can ? [{ key: 'act', label: '', render: (r: Row) => (r.linked
            ? <Button size="sm" variant="ghost" onClick={() => void act(() => unlinkBasalamStore(id, r.connection_id))}><Unlink className="me-1 h-4 w-4" />{p('unlink')}</Button>
            : <Button size="sm" variant="outline" onClick={() => void act(() => linkBasalamStore(id, r.connection_id), r.connection_id)}><Link2 className="me-1 h-4 w-4" />{p('link')}</Button>) }] : []),
        ]} />
      )}
      <TabForm open={force !== null} onOpenChange={(v) => !v && setForce(null)} title={p('relinkTitle')} fields={[]} submitLabel={p('relinkConfirm')}
        onSubmit={async () => { if (force === null) return; await linkBasalamStore(id, force, true); await load(); refresh(); setNotice(p('relinked')); }}>
        {() => <p className="text-sm" dir="auto">{p('linkedElsewhere')} {p('relinkHint')}</p>}
      </TabForm>
    </HubCard>
  );
}

// ---------------- Accounting ----------------
export function AccountingTab() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p3');
  const f = useFmt();
  const { id, version } = useHub();
  const { refresh, setNotice } = useHub();
  const [sum, setSum] = useState<AccountingSummaryRes['data'] | null>(null);
  const [can, setCan] = useState<AccountingSummaryRes['meta']['can'] | null>(null);
  const [dlg, setDlg] = useState<'payment' | 'invoice' | null>(null);
  const [tick, setTick] = useState(0);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    let live = true;
    setLoading(true);
    apiClient.get(`${hubBase(id)}/${P3_AREAS.accounting.path}`)
      .then((r) => { if (live) { setSum((r.data as AccountingSummaryRes).data); setCan((r.data as AccountingSummaryRes).meta?.can ?? null); } })
      .catch(() => { if (live) { setSum(null); setCan(null); } })
      .finally(() => live && setLoading(false));
    return () => { live = false; };
  }, [id, version, tick]);
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [invStatus, setInvStatus] = useState('open');
  const ledger = useHubList(id, 'accounting/ledger', { from: from?.slice(0, 10), to: to?.slice(0, 10) }, true, version + tick);
  const lm = ledger.meta as LedgerMeta | null;
  const inv = useHubList(id, 'accounting/invoices', { status: invStatus }, true, version + tick);
  const im = inv.meta as InvoicesMeta | null;
  const pay = useHubList(id, 'accounting/payments', {}, true, version + tick);
  const after = (msg: string) => { setNotice(msg); setTick((n) => n + 1); refresh(); };
  const openInvoices = inv.rows.filter((r) => r.status !== 'paid');
  const payFields: TField[] = [
    { name: 'amount', label: p('amount'), type: 'number', required: true, hint: p('irrHint') },
    { name: 'method', label: p('method'), type: 'select', required: true, options: PAYMENT_METHODS.map((x) => [x, p(`pm.${x}` as never)]) },
    { name: 'paid_at', label: p('paidAt'), type: 'date' },
    { name: 'reference', label: p('reference') },
    { name: 'invoice_id', label: p('invoice'), type: 'select', options: openInvoices.map((r) => [String(r.id), String(r.number)]) },
    { name: 'note', label: p('note'), type: 'textarea' },
  ];
  const submitPayment = async (v: TValues) => {
    const b = body(v, ['amount', 'invoice_id']) as Record<string, unknown>;
    if (!(Number(b.amount) >= 1)) throw { response: { data: { message: p('amountMin'), errors: { amount: [p('amountMin')] } } } };
    if (typeof b.paid_at === 'string') b.paid_at = b.paid_at.slice(0, 10);
    await recordPayment(id, { ...(b as object), method: b.method as PaymentMethod } as never);
    after(p('paymentRecorded'));
  };
  const invFields: TField[] = [
    { name: 'title', label: p('itemTitle'), required: true, full: true },
    { name: 'price', label: p('unitPrice'), type: 'number', required: true, hint: p('irrHint') },
    { name: 'discount', label: p('discount'), type: 'number' },
    { name: 'desc', label: p('description'), full: true },
    { name: 'issue_date', label: p('issueDate'), type: 'date' },
    { name: 'status', label: p('status'), type: 'select', required: true, options: [['unpaid', p('invSt.unpaid')], ['draft', p('invSt.draft')]] },
    { name: 'notes', label: p('note'), type: 'textarea' },
  ];
  const submitInvoice = async (v: TValues) => {
    const price = Number(v.price);
    if (!(price >= 0) || Number.isNaN(price)) throw { response: { data: { message: p('amountMin'), errors: { price: [p('amountMin')] } } } };
    const item: { title: string; price: number; discount?: number; desc?: string } = { title: String(v.title).trim(), price };
    if (v.discount) item.discount = Number(v.discount);
    if (v.desc) item.desc = String(v.desc);
    await createInvoice(id, {
      items: [item], status: (v.status as 'draft' | 'unpaid') || 'unpaid',
      ...(v.issue_date ? { issue_date: String(v.issue_date).slice(0, 10) } : {}),
      ...(v.notes ? { notes: String(v.notes) } : {}),
    });
    after(p('invoiceCreated'));
  };
  const actions = can?.record_payment || can?.create_invoice ? (
    <div className="flex gap-2">
      {can?.create_invoice ? <Button size="sm" variant="outline" onClick={() => setDlg('invoice')}><FilePlus2 className="me-1 h-4 w-4" />{p('createInvoice')}</Button> : null}
      {can?.record_payment ? <Button size="sm" onClick={() => setDlg('payment')}><Wallet className="me-1 h-4 w-4" />{p('recordPayment')}</Button> : null}
    </div>
  ) : null;
  const m = (label: string, v: number | null | undefined, danger?: boolean) => (hasMoney(v) ? <Kpi key={label} label={label} value={f.money(v)} tone={danger ? 'danger' : undefined} /> : null);
  return (
    <Wrap>
      <HubCard title={t('accounting')} actions={actions}>
        {loading && !sum ? <Loading /> : !sum ? <Empty /> : (
          <>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              {m(p('balance'), sum.balance, (sum.balance ?? 0) < 0)}
              {m(p('outstanding'), sum.outstanding)}
              {m(p('overdue'), sum.overdue, true)}
              {sum.overdue_count !== null ? <Kpi label={p('overdueCount')} value={f.num(sum.overdue_count)} /> : null}
              {m(p('totalInvoiced'), sum.total_invoiced)}
              {m(p('totalPaid'), sum.total_paid)}
              {m(p('walletCredit'), sum.wallet_credit)}
              {m(p('creditLimit'), sum.credit_limit)}
            </div>
            {sum.aging ? (
              <div className="mt-3">
                <div className="mb-1 text-xs text-muted-foreground">{p('aging')}</div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-5" data-testid="aging">
                  {(['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'] as const).map((b) => m(p(`ag.${b}` as never), sum.aging?.[b], b === 'd90_plus'))}
                </div>
              </div>
            ) : null}
            <div className="mt-3"><KV items={[
              [p('accPerson'), sum.acc_person?.name ?? p('noAccPerson')],
              [p('lastPayment'), sum.last_payment ? `${f.date(sum.last_payment.date)}${hasMoney(sum.last_payment.amount) ? ` — ${f.money(sum.last_payment.amount)}` : ''}` : null],
            ]} /></div>
          </>
        )}
      </HubCard>

      <HubCard title={p('ledger')}>
        <FilterBar>
          <div className="w-44"><LocaleDatePicker value={from} onChange={(iso) => setFrom(iso || null)} /></div>
          <div className="w-44"><LocaleDatePicker value={to} onChange={(iso) => setTo(iso || null)} /></div>
        </FilterBar>
        {lm && (hasMoney(lm.opening_balance) || hasMoney(lm.closing_balance)) ? (
          <div className="mb-2 flex gap-4 text-xs text-muted-foreground">
            {hasMoney(lm.opening_balance) ? <span>{p('opening')}: {f.money(lm.opening_balance)}</span> : null}
            {hasMoney(lm.closing_balance) ? <span>{p('closing')}: {f.money(lm.closing_balance)}</span> : null}
          </div>
        ) : null}
        {ledger.loading && !ledger.rows.length ? <Loading /> : <><DataTable rows={ledger.rows} cols={moneyCols([
          { key: 'date', label: p('date'), render: (r) => f.date(r.date) },
          { key: 'kind', label: p('kind'), render: (r) => p(`lk.${r.kind}` as never) },
          { key: 'ref', label: p('ref'), render: (r) => <span dir="ltr">{r.ref}</span> },
          { key: 'description', label: p('description'), render: (r) => <span dir="auto">{r.description}</span> },
          { key: 'debit', label: p('debit'), money: true, render: (r) => (hasMoney(r.debit) ? f.money(r.debit) : '—') },
          { key: 'credit', label: p('credit'), money: true, render: (r) => (hasMoney(r.credit) ? f.money(r.credit) : '—') },
          { key: 'balance', label: p('balance'), money: true, render: (r) => (hasMoney(r.balance) ? f.money(r.balance) : '—') },
        ], ledger.rows)} /><Pager meta={ledger.meta} onPage={ledger.setPage} /></>}
      </HubCard>

      <HubCard title={p('invoices')}>
        <FilterBar>
          <select aria-label={p('status')} className={`${selectCls} w-40`} value={invStatus} onChange={(e) => setInvStatus(e.target.value)}>
            {['open', 'overdue', 'paid', 'all'].map((s) => <option key={s} value={s}>{p(`is.${s}` as never)}</option>)}
          </select>
          {im?.kpis ? <span className="text-xs text-muted-foreground">{p('is.open')}: {f.num(im.kpis.open)} · {p('is.overdue')}: {f.num(im.kpis.overdue)} · {p('is.paid')}: {f.num(im.kpis.paid)}</span> : null}
        </FilterBar>
        {inv.loading && !inv.rows.length ? <Loading /> : <><DataTable rows={inv.rows} cols={moneyCols([
          { key: 'number', label: p('number'), render: (r) => <span dir="ltr">{r.number}</span> },
          { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.status} /> },
          { key: 'issue_date', label: p('issueDate'), render: (r) => f.date(r.issue_date) },
          { key: 'due_date', label: p('dueDate'), render: (r) => f.date(r.due_date) },
          { key: 'total', label: p('total'), money: true, render: (r) => (hasMoney(r.total) ? f.money(r.total) : '—') },
          { key: 'paid_amount', label: p('paidAmount'), money: true, render: (r) => (hasMoney(r.paid_amount) ? f.money(r.paid_amount) : '—') },
          { key: 'remaining', label: p('remaining'), money: true, render: (r) => (hasMoney(r.remaining) ? f.money(r.remaining) : '—') },
          { key: 'overdue_days', label: p('overdueDays'), render: (r) => (r.overdue_days ? <span className="text-destructive">{f.num(r.overdue_days)}</span> : '—') },
        ], inv.rows)} /><Pager meta={inv.meta} onPage={inv.setPage} /></>}
      </HubCard>

      <HubCard title={p('payments')}>
        {pay.loading && !pay.rows.length ? <Loading /> : <><DataTable rows={pay.rows} cols={moneyCols([
          { key: 'date', label: p('date'), render: (r) => f.date(r.date) },
          { key: 'source', label: p('source'), render: (r) => p(`ps.${r.source}` as never) },
          { key: 'amount', label: p('amount'), money: true, render: (r) => (hasMoney(r.amount) ? f.money(r.amount) : '—') },
          { key: 'gateway', label: p('gateway') },
          { key: 'tracking_code', label: p('trackingCode'), render: (r) => <span dir="ltr">{r.tracking_code ?? '—'}</span> },
          { key: 'status', label: p('status'), render: (r) => <StatusBadge value={r.paid ? 'paid' : r.status} label={r.status} /> },
        ], pay.rows)} /><Pager meta={pay.meta} onPage={pay.setPage} /></>}
      </HubCard>
      {can?.record_payment ? <TabForm open={dlg === 'payment'} onOpenChange={(v) => !v && setDlg(null)} title={p('recordPayment')} fields={payFields} initial={{ method: 'card_to_card' }} onSubmit={submitPayment} submitLabel={p('recordPayment')} /> : null}
      {can?.create_invoice ? <TabForm open={dlg === 'invoice'} onOpenChange={(v) => !v && setDlg(null)} title={p('createInvoice')} fields={invFields} initial={{ status: 'unpaid' }} onSubmit={submitInvoice} submitLabel={p('createInvoice')} /> : null}
    </Wrap>
  );
}

// ---------------- SMS ----------------
export function SmsTab() {
  const t = useTranslations('crm.hub.c360');
  const p = useTranslations('crm.hub.c360.p3');
  const f = useFmt();
  const { id, version, refresh, setNotice } = useHub();
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');
  const list = useHubList(id, P3_AREAS.sms.path, { status: status || undefined, q: q.trim() || undefined }, true, version);
  const meta = list.meta as SmsMeta | null;
  const [open, setOpen] = useState(false);
  const recipients = meta?.recipients ?? [];
  const templates = meta?.templates ?? [];
  const fields: TField[] = [
    { name: 'recipient', label: p('recipient'), type: 'select', options: recipients.map((r) => [`${r.kind}:${r.id}`, `${r.label} (${r.mobile})`]) },
    { name: 'to', label: p('to'), hint: p('toHint') },
    { name: 'template_id', label: p('template'), type: 'select', options: templates.map((x) => [String(x.id), x.name]) },
    { name: 'body', label: p('body'), type: 'textarea', hint: p('bodyHint', { max: SMS_BODY_MAX }) },
    { name: 'purpose', label: p('purpose'), type: 'select', required: true, options: [['transactional', p('pp.transactional')], ['marketing', p('pp.marketing')]] },
  ];
  const submit = async (v: TValues) => {
    const b: SmsSendBody = { purpose: (v.purpose as SmsSendBody['purpose']) || 'transactional' };
    const rec = String(v.recipient ?? '');
    const r = recipients.find((x) => `${x.kind}:${x.id}` === rec);
    if (v.to) b.to = String(v.to).trim();
    else if (r?.kind === 'contact') b.contact_id = r.id;
    else if (r) b.to = r.mobile;
    if (v.template_id) b.template_id = Number(v.template_id);
    if (v.body) b.body = String(v.body);
    if (!b.body && !b.template_id) throw { response: { data: { message: p('bodyOrTemplate'), errors: { body: [p('bodyOrTemplate')] } } } };
    if (b.body && b.body.length > SMS_BODY_MAX) throw { response: { data: { message: p('tooLong'), errors: { body: [p('tooLong')] } } } };
    if (b.purpose === 'marketing' && meta?.opt_out) throw { response: { data: { message: p('optOut'), errors: { purpose: [p('optOut')] } } } };
    await sendSms(id, b);
    setNotice(p('sent'));
    refresh();
    void list.reload();
  };
  return (
    <HubCard title={t('sms')} actions={meta?.can?.send ? <Button size="sm" onClick={() => setOpen(true)}><MessageSquarePlus className="me-1 h-4 w-4" />{p('send')}</Button> : null}>
      <div dir="auto">
        {meta?.opt_out ? <p className="mb-2 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900" data-testid="opt-out">{p('optOut')}</p> : null}
        <FilterBar>
          <div className="relative"><Search className="absolute start-2 top-2.5 h-4 w-4 text-muted-foreground" /><Input className="ps-8" placeholder={p('searchSms')} value={q} onChange={(e) => setQ(e.target.value)} /></div>
          <select aria-label={p('status')} className={`${selectCls} w-36`} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{p('src.all')}</option>
            {['sent', 'queued', 'failed', 'logged'].map((s) => <option key={s} value={s}>{p(`ss.${s}` as never)}</option>)}
          </select>
        </FilterBar>
        {list.loading && !list.rows.length ? <Loading /> : <><DataTable rows={list.rows} cols={[
          { key: 'created_at', label: p('date'), render: (r) => f.dateTime(r.created_at) },
          { key: 'to', label: p('to'), render: (r) => <span dir="ltr">{r.to}</span> },
          { key: 'body', label: p('body'), render: (r) => <span dir="auto" className="block max-w-xs truncate" title={r.body}>{r.body}</span> },
          { key: 'status', label: p('status'), render: (r) => <span title={r.error ?? undefined}><StatusBadge value={r.status} label={p(`ss.${r.status}` as never)} /></span> },
          { key: 'provider', label: p('provider') },
          { key: 'created_by', label: p('createdBy'), render: (r) => r.created_by?.name ?? '—' },
        ]} /><Pager meta={list.meta} onPage={list.setPage} /></>}
      </div>
      {meta?.can?.send ? (
        <TabForm open={open} onOpenChange={setOpen} title={p('send')} fields={fields} initial={{ purpose: 'transactional' }} onSubmit={submit} submitLabel={p('send')} />
      ) : null}
    </HubCard>
  );
}
