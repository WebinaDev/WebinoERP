'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Ban, FilePlus2, FileSignature, Link2, Plus, RefreshCw, Search, Trash2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { hubBase, openDocument, useHubList, type Row } from '../hub-api';
import { useHub } from '../HubContext';
import { DataTable, Empty, HubCard, KV, Loading, Pager, StatusBadge, useFmt } from '../ui';
import { DetailSheet, FilterBar, Kpi, TabForm, body, selectCls, toFormData, type TabMeta, type TField, type TValues } from './shared';

export const CONTRACT_STATUSES = ['draft', 'active', 'signed', 'expired', 'cancelled'] as const;

type Inst = { id?: number; title: string; due_date: string; amount: string };

/** Installment rows editor; sum must not exceed the contract amount (also enforced server-side). */
export function InstallmentsEditor({ rows, onChange, amount }: { rows: Inst[]; onChange: (r: Inst[]) => void; amount: number }) {
  const t = useTranslations('crm.hub.t360');
  const f = useFmt();
  const sum = rows.reduce((s, r) => s + (Number(r.amount) || 0), 0);
  const over = amount > 0 && sum > amount;
  const set = (i: number, k: keyof Inst, v: string) => onChange(rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
  return (
    <div className="space-y-2 rounded-md border p-3">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium">{t('installments')}</span>
        <Button type="button" size="sm" variant="outline" onClick={() => onChange([...rows, { title: '', due_date: '', amount: '' }])}><Plus className="me-1 h-4 w-4" />{t('addInstallment')}</Button>
      </div>
      {rows.map((r, i) => (
        <div key={i} className="grid grid-cols-12 items-center gap-2">
          <Input className="col-span-4" placeholder={t('instTitle')} value={r.title} onChange={(e) => set(i, 'title', e.target.value)} />
          <div className="col-span-4"><LocaleDatePicker value={r.due_date || null} onChange={(iso) => set(i, 'due_date', iso)} /></div>
          <Input className="col-span-3" type="number" dir="ltr" placeholder={t('amount')} aria-label={`${t('amount')} ${i + 1}`} value={r.amount} onChange={(e) => set(i, 'amount', e.target.value)} />
          <Button type="button" size="icon" variant="ghost" className="col-span-1" aria-label={t('removeInstallment')} onClick={() => onChange(rows.filter((_, j) => j !== i))}><Trash2 className="h-4 w-4" /></Button>
        </div>
      ))}
      {rows.length ? (
        <p className={`text-xs ${over ? 'text-destructive' : 'text-muted-foreground'}`} data-testid="inst-sum">
          {t('instSum', { sum: f.money(sum), amount: f.money(amount) })}{over ? ` — ${t('instOver')}` : ''}
        </p>
      ) : null}
    </div>
  );
}

type Dlg =
  | { k: 'form'; row?: Row }
  | { k: 'sign'; row: Row }
  | { k: 'cancel'; row: Row }
  | { k: 'renew'; row: Row }
  | { k: 'project'; row: Row };

export function ContractsTab() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, refresh, setNotice } = useHub();
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [expiring, setExpiring] = useState(false);
  const list = useHubList(id, 'contracts', { q: q.trim() || undefined, status: status || undefined, expiring_in_days: expiring ? '30' : undefined }, true, version);
  const meta = list.meta as TabMeta | null;
  const k = meta?.kpis ?? {};
  const [dlg, setDlg] = useState<Dlg | null>(null);
  const [inst, setInst] = useState<Inst[]>([]);
  const [detailId, setDetailId] = useState<number | null>(null);
  const base = hubBase(id);
  const st = (s: string) => t(`t360.cs.${s}` as never);
  const projects = useHubList(id, 'projects', { per_page: '100' }, dlg?.k === 'project', version);

  const act = useCallback(async (fn: () => Promise<unknown>) => {
    try {
      await fn();
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  }, [refresh, setNotice]);

  const openForm = (row?: Row) => {
    setInst(row ? (row.installments ?? []).map((i: Row) => ({ id: i.id, title: i.title ?? '', due_date: i.due_date ?? '', amount: i.amount === null ? '' : String(i.amount) })) : []);
    setDlg({ k: 'form', row });
  };

  const formFields: TField[] = [
    { name: 'title', label: t('doc.title'), required: true },
    { name: 'contract_no', label: t('t360.contractNo') },
    { name: 'amount', label: t('t360.amount'), type: 'number', required: true },
    { name: 'tax_pct', label: t('t360.taxPct'), type: 'number' },
    { name: 'start_date', label: t('t360.startDate'), type: 'date' },
    { name: 'end_date', label: t('t360.endDate'), type: 'date' },
    { name: 'renewal_type', label: t('t360.renewalType'), type: 'select', options: ['none', 'manual', 'auto'].map((x) => [x, t(`t360.rn.${x}` as never)]) },
    { name: 'renewal_notice_days', label: t('t360.noticeDays'), type: 'number' },
    ...(dlg?.k === 'form' && dlg.row ? [{ name: 'status', label: t('t360.status'), type: 'select' as const, required: true, options: CONTRACT_STATUSES.filter((s) => s !== 'cancelled').map((s) => [s, st(s)] as [string, string]) }] : []),
    { name: 'notes', label: t('t360.notes'), type: 'textarea' },
  ];

  const submitForm = async (v: TValues) => {
    const row = dlg?.k === 'form' ? dlg.row : undefined;
    const payload = {
      ...body(v, ['amount', 'tax_pct', 'renewal_notice_days']),
      installments: inst.filter((i) => i.due_date && i.amount !== '').map((i) => ({ ...(i.id ? { id: i.id } : {}), title: i.title || null, due_date: i.due_date, amount: Number(i.amount) })),
    };
    if (row) await apiClient.patch(`${base}/contracts/${row.id}`, payload);
    else await apiClient.post(`${base}/contracts`, payload);
    refresh();
  };

  const formInitial = (row?: Row): TValues => (row ? {
    title: row.title ?? '', contract_no: row.contract_no ?? '', amount: row.amount === null ? '' : String(row.amount), tax_pct: row.tax_pct === null || row.tax_pct === undefined ? '' : String(row.tax_pct),
    start_date: row.start_date ?? '', end_date: row.end_date ?? '', renewal_type: row.renewal_type ?? 'none', renewal_notice_days: row.renewal_notice_days === null || row.renewal_notice_days === undefined ? '' : String(row.renewal_notice_days),
    status: row.status === 'expired' && row.end_date ? 'active' : row.status, notes: row.notes ?? '',
  } : { renewal_type: 'none' });

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-5" data-testid="contract-kpis">
        <Kpi label={t('t360.k.total')} value={f.num(k.total ?? 0)} />
        <Kpi label={t('t360.k.active')} value={f.num(k.active ?? 0)} />
        <Kpi label={t('t360.k.expiring')} value={f.num(k.expiring_30 ?? 0)} tone={(k.expiring_30 ?? 0) > 0 ? 'danger' : undefined} />
        <Kpi label={t('t360.k.activeAmount')} value={f.money(k.active_amount)} />
        <Kpi label={t('t360.k.paid')} value={f.money(k.paid_total)} />
      </div>
      <HubCard title={t('t360.contracts')} actions={meta?.can?.create ? <Button size="sm" onClick={() => openForm()}><Plus className="me-1 h-4 w-4" />{t('t360.newContract')}</Button> : null}>
        <FilterBar>
          <div className="relative">
            <Search className="pointer-events-none absolute start-2 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input className="h-9 w-48 ps-8" placeholder={t('t360.search')} aria-label={t('t360.search')} value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          <select className={`${selectCls} w-36`} aria-label={t('t360.status')} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{t('t360.allStatuses')}</option>
            {CONTRACT_STATUSES.map((s) => <option key={s} value={s}>{st(s)}</option>)}
          </select>
          <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={expiring} onChange={(e) => setExpiring(e.target.checked)} />{t('t360.expiring30')}</label>
        </FilterBar>
        {list.loading && !list.rows.length ? <Loading /> : (
          <DataTable rows={list.rows} cols={[
            { key: 'title', label: t('doc.title'), render: (r) => (
              r.source === 'projects'
                ? <button type="button" className="text-start font-medium text-primary hover:underline" onClick={() => setDetailId(r.id)}>{r.title}</button>
                : <span>{r.title} <Badge variant="outline">{t('t360.src.docs')}</Badge></span>
            ) },
            { key: 'contract_no', label: t('t360.contractNo'), render: (r) => (r.contract_no ? <span dir="ltr">{r.contract_no}</span> : '—') },
            { key: 'status', label: t('t360.status'), render: (r) => <StatusBadge value={r.status} label={st(r.status)} /> },
            { key: 'amount', label: t('t360.amount'), render: (r) => f.money(r.amount) },
            { key: 'paid', label: t('t360.paid'), render: (r) => f.money(r.paid_total) },
            { key: 'period', label: t('t360.period'), render: (r) => (r.start_date || r.end_date ? `${f.date(r.start_date)} ← ${f.date(r.end_date)}` : '—') },
            { key: 'days_to_end', label: t('t360.daysLeft'), render: (r) => (r.days_to_end === null || r.days_to_end === undefined ? '—'
              : <span className={r.days_to_end < 0 ? 'text-destructive' : r.days_to_end <= 30 ? 'text-amber-600' : ''}>{f.num(r.days_to_end)}</span>) },
          ]} />
        )}
        <Pager meta={list.meta} onPage={list.setPage} />
      </HubCard>

      {detailId !== null ? (
        <ContractDetail
          contractId={detailId}
          onClose={() => setDetailId(null)}
          onAction={(kind, row) => (kind === 'edit' ? openForm(row) : setDlg({ k: kind, row } as Dlg))}
          act={act}
        />
      ) : null}

      <TabForm open={dlg?.k === 'form'} onOpenChange={(o) => { if (!o) setDlg(null); }} title={dlg?.k === 'form' && dlg.row ? t('edit') : t('t360.newContract')}
        fields={formFields} initial={formInitial(dlg?.k === 'form' ? dlg.row : undefined)} onSubmit={submitForm}>
        {(v) => <InstallmentsEditor rows={inst} onChange={setInst} amount={Number(v.amount) || 0} />}
      </TabForm>
      <TabForm open={dlg?.k === 'sign'} onOpenChange={(o) => { if (!o) setDlg(null); }} title={t('t360.sign')} submitLabel={t('t360.sign')}
        fields={[{ name: 'signed_at', label: t('t360.signedAt'), type: 'date' }, { name: 'file', label: t('t360.signedFile'), type: 'file' }]}
        onSubmit={async (v) => { await apiClient.post(`${base}/contracts/${(dlg as { row: Row }).row.id}/sign`, toFormData(v)); refresh(); }} />
      <TabForm open={dlg?.k === 'cancel'} onOpenChange={(o) => { if (!o) setDlg(null); }} title={t('t360.cancelContract')} submitLabel={t('t360.cancelContract')}
        fields={[{ name: 'reason', label: t('t360.reason'), type: 'textarea', required: true }]}
        onSubmit={async (v) => { await apiClient.post(`${base}/contracts/${(dlg as { row: Row }).row.id}/cancel`, body(v)); refresh(); }} />
      <TabForm open={dlg?.k === 'renew'} onOpenChange={(o) => { if (!o) setDlg(null); }} title={t('t360.renew')} submitLabel={t('t360.renew')}
        fields={[{ name: 'start_date', label: t('t360.startDate'), type: 'date' }, { name: 'end_date', label: t('t360.endDate'), type: 'date' }, { name: 'amount', label: t('t360.amount'), type: 'number' }]}
        onSubmit={async (v) => { await apiClient.post(`${base}/contracts/${(dlg as { row: Row }).row.id}/renew`, body(v, ['amount'])); refresh(); }} />
      <TabForm open={dlg?.k === 'project'} onOpenChange={(o) => { if (!o) setDlg(null); }} title={t('t360.linkProject')}
        fields={[{ name: 'project_id', label: t('t360.project'), type: 'select', required: true, options: projects.rows.map((p) => [String(p.id), p.name] as [string, string]) }]}
        onSubmit={async (v) => { await apiClient.post(`${base}/contracts/${(dlg as { row: Row }).row.id}/projects`, body(v, ['project_id'])); refresh(); }} />
    </div>
  );
}

function ContractDetail({ contractId, onClose, onAction, act }: {
  contractId: number; onClose: () => void; onAction: (k: 'edit' | 'sign' | 'cancel' | 'renew' | 'project', row: Row) => void; act: (fn: () => Promise<unknown>) => Promise<void>;
}) {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, setNotice } = useHub();
  const [c, setC] = useState<Row | null>(null);
  const base = hubBase(id);
  useEffect(() => {
    let live = true;
    apiClient.get(`${base}/contracts/${contractId}`)
      .then((res) => { if (live) setC((res.data as { data: Row }).data); })
      .catch((e) => { if (live) { setNotice(getApiErrorMessage(e)); onClose(); } });
    return () => { live = false; };
  }, [base, contractId, version, setNotice, onClose]);
  const can = c?.can ?? {};
  const st = (s: string) => t(`t360.cs.${s}` as never);
  return (
    <DetailSheet open onOpenChange={(o) => { if (!o) onClose(); }} title={c ? c.title : t('loading')}>
      {!c ? <Loading /> : <>
        <div className="flex flex-wrap gap-2">
          {can.update ? <Button size="sm" variant="outline" onClick={() => onAction('edit', c)}>{t('edit')}</Button> : null}
          {can.sign ? <Button size="sm" variant="outline" onClick={() => onAction('sign', c)}><FileSignature className="me-1 h-4 w-4" />{t('t360.sign')}</Button> : null}
          {can.renew ? <Button size="sm" variant="outline" onClick={() => onAction('renew', c)}><RefreshCw className="me-1 h-4 w-4" />{t('t360.renew')}</Button> : null}
          {can.update ? <Button size="sm" variant="outline" onClick={() => onAction('project', c)}><Link2 className="me-1 h-4 w-4" />{t('t360.linkProject')}</Button> : null}
          {can.cancel ? <Button size="sm" variant="ghost" className="text-destructive" onClick={() => onAction('cancel', c)}><Ban className="me-1 h-4 w-4" />{t('t360.cancelContract')}</Button> : null}
          {can.delete ? <Button size="sm" variant="ghost" className="text-destructive" onClick={() => {
            if (typeof window !== 'undefined' && !window.confirm(`${t('delete')}?`)) return;
            void act(() => apiClient.delete(`${base}/contracts/${c.id}`)).then(onClose);
          }}><Trash2 className="me-1 h-4 w-4" />{t('delete')}</Button> : null}
        </div>
        <KV items={[
          [t('t360.status'), <StatusBadge key="s" value={c.status} label={st(c.status)} />],
          [t('t360.contractNo'), c.contract_no ? <span dir="ltr">{c.contract_no}</span> : null],
          [t('t360.amount'), f.money(c.amount)],
          [t('t360.taxPct'), c.tax_pct === null || c.tax_pct === undefined ? null : `${f.num(c.tax_pct)}٪`],
          [t('t360.paid'), f.money(c.paid_total)],
          [t('t360.invoiced'), f.money(c.invoiced_total)],
          [t('t360.remaining'), f.money(c.remaining)],
          [t('t360.startDate'), f.date(c.start_date)],
          [t('t360.endDate'), f.date(c.end_date)],
          [t('t360.signedAt'), f.date(c.signed_at)],
          [t('t360.renewalType'), c.renewal_type ? t(`t360.rn.${c.renewal_type}` as never) : null],
          [t('t360.noticeDays'), c.renewal_notice_days],
          [t('owner'), c.owner?.name],
          [t('t360.deal'), c.deal?.name ?? c.deal?.title],
          [t('t360.notes'), c.notes ? <span className="whitespace-pre-wrap">{c.notes}</span> : null],
        ]} />
        {c.signed_document?.id ? (
          <Button size="sm" variant="link" className="px-0" onClick={() => void openDocument(id, c.signed_document.id).catch((e) => setNotice(getApiErrorMessage(e)))}>{t('t360.signedFile')}</Button>
        ) : null}
        <section>
          <h4 className="mb-2 font-medium">{t('t360.installments')}</h4>
          <DataTable rows={c.installments ?? []} empty={t('noData')} cols={[
            { key: 'title', label: t('t360.instTitle'), render: (i) => i.title ?? '—' },
            { key: 'due_date', label: t('t360.dueDate'), render: (i) => f.date(i.due_date) },
            { key: 'amount', label: t('t360.amount'), render: (i) => f.money(i.amount) },
            { key: 'status', label: t('t360.status'), render: (i) => <StatusBadge value={i.status} label={t(`t360.is.${i.status}` as never)} /> },
            { key: 'inv', label: '', render: (i) => (i.invoice_id ? <Badge variant="outline">{t('t360.invoicedBadge')}</Badge>
              : can.invoice && i.status !== 'paid' ? <Button size="sm" variant="ghost" onClick={() => void act(() => apiClient.post(`${base}/contracts/${c.id}/installments/${i.id}/invoice`))}><FilePlus2 className="me-1 h-4 w-4" />{t('t360.makeInvoice')}</Button> : null) },
          ]} />
        </section>
        <section>
          <h4 className="mb-2 font-medium">{t('t360.projects')}</h4>
          {(c.projects ?? []).length ? (
            <ul className="space-y-1">{c.projects.map((p: Row) => <li key={p.id} className="flex items-center gap-2"><span>{p.name}</span>{p.status ? <StatusBadge value={p.status} label={t(`t360.ps.${p.status}` as never)} /> : null}</li>)}</ul>
          ) : <Empty />}
        </section>
        {Array.isArray(c.invoices) && c.invoices.length ? (
          <section>
            <h4 className="mb-2 font-medium">{t('t360.invoices')}</h4>
            <DataTable rows={c.invoices} cols={[
              { key: 'number', label: '#', render: (i) => <span dir="ltr">{i.number}</span> },
              { key: 'status', label: t('t360.status'), render: (i) => <StatusBadge value={i.status} /> },
              { key: 'total', label: t('t360.amount'), render: (i) => f.money(i.total) },
              { key: 'created_at', label: t('t360.date'), render: (i) => f.date(i.created_at) },
            ]} />
          </section>
        ) : null}
        {Array.isArray(c.documents) && c.documents.length ? (
          <section>
            <h4 className="mb-2 font-medium">{t('tab.documents')}</h4>
            <ul className="space-y-1">{c.documents.map((d: Row) => (
              <li key={d.id}><button type="button" className="text-primary hover:underline" onClick={() => void openDocument(id, d.id).catch((e) => setNotice(getApiErrorMessage(e)))}>{d.title}</button></li>
            ))}</ul>
          </section>
        ) : null}
      </>}
    </DetailSheet>
  );
}
