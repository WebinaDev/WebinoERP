'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Download, Eye, History, Plus, Search, Trash2, Upload } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { hubBase, openDocument, useHubList, type Row } from '../hub-api';
import { useHub } from '../HubContext';
import { DataTable, HubCard, KV, Loading, Pager, StatusBadge, useFmt } from '../ui';
import { DetailSheet, DOC_CATEGORIES, FilterBar, TabForm, body, fmtSize, selectCls, toFormData, type TabMeta, type TField } from './shared';

const PREVIEWABLE = /^(image\/|application\/pdf)/;

export function DocumentsTab360() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, refresh, setNotice } = useHub();
  const [q, setQ] = useState('');
  const [category, setCategory] = useState('');
  const [review, setReview] = useState('');
  const [expired, setExpired] = useState('');
  const [source, setSource] = useState('');
  const list = useHubList(id, 'documents', { q: q.trim() || undefined, category: category || undefined, review_status: review || undefined, expired: expired || undefined, source: source || undefined }, true, version);
  const meta = list.meta as TabMeta | null;
  const [form, setForm] = useState<null | { mode: 'create' } | { mode: 'edit'; row: Row } | { mode: 'version'; row: Row }>(null);
  const [detail, setDetail] = useState<Row | null>(null);
  const cats: [string, string][] = DOC_CATEGORIES.map((c) => [c, t(`dc.${c}` as never)]);

  const remove = async (r: Row) => {
    if (typeof window !== 'undefined' && !window.confirm(t('t360.confirmDeleteDoc'))) return;
    try {
      await apiClient.delete(`${hubBase(id)}/documents/${r.id}`);
      setDetail(null);
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  };
  const doReview = async (r: Row, status: 'approved' | 'rejected') => {
    let note: string | null = null;
    if (status === 'rejected' && typeof window !== 'undefined') note = window.prompt(t('t360.rejectReason')) ?? null;
    try {
      await apiClient.patch(`${hubBase(id)}/documents/${r.id}`, { review_status: status, ...(note ? { review_note: note } : {}) });
      refresh();
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  };
  const download = (r: Row) => {
    if (r.source !== 'hub' && r.url) {
      window.open(String(r.url), '_blank', 'noopener');
      return;
    }
    void openDocument(id, r.id).catch((e) => setNotice(getApiErrorMessage(e)));
  };

  const baseFields: TField[] = [
    { name: 'title', label: t('doc.title'), required: true },
    { name: 'category', label: t('doc.category'), type: 'select', options: cats, required: true },
    { name: 'expires_at', label: t('doc.expires'), type: 'date' },
    { name: 'tags', label: t('t360.tags'), hint: t('t360.commaHint') },
    { name: 'visible_to_roles', label: t('doc.visibility') },
  ];
  const fields: TField[] = form?.mode === 'version'
    ? [{ name: 'file', label: t('doc.file'), type: 'file', required: true }, { name: 'note', label: t('t360.versionNote'), type: 'textarea' }]
    : form?.mode === 'edit' ? baseFields
      : [...baseFields, { name: 'file', label: t('doc.file'), type: 'file' }, { name: 'url', label: t('doc.url') }];

  const csv = (s: unknown) => String(s ?? '').split(/[,،]/).map((x) => x.trim()).filter(Boolean);
  const submit = async (v: Record<string, string | File | null>) => {
    const base = hubBase(id);
    if (form?.mode === 'version') {
      await apiClient.post(`${base}/documents/${form.row.id}/versions`, toFormData(v));
    } else if (form?.mode === 'edit') {
      const b = body(v);
      await apiClient.patch(`${base}/documents/${form.row.id}`, { ...b, expires_at: v.expires_at || null, tags: csv(v.tags), visible_to_roles: csv(v.visible_to_roles) });
    } else {
      const fd = toFormData({ ...v, tags: null, visible_to_roles: null });
      csv(v.tags).forEach((x) => fd.append('tags[]', x));
      csv(v.visible_to_roles).forEach((x) => fd.append('visible_to_roles[]', x));
      await apiClient.post(`${base}/documents`, fd);
    }
    refresh();
  };

  return (
    <HubCard
      title={t('tab.documents')}
      actions={meta?.can?.create ? <Button size="sm" onClick={() => setForm({ mode: 'create' })}><Plus className="me-1 h-4 w-4" />{t('t360.upload')}</Button> : null}
    >
      <FilterBar>
        <div className="relative">
          <Search className="pointer-events-none absolute start-2 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input className="h-9 w-48 ps-8" placeholder={t('t360.search')} value={q} onChange={(e) => setQ(e.target.value)} aria-label={t('t360.search')} />
        </div>
        <select className={`${selectCls} w-36`} aria-label={t('doc.category')} value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="">{t('t360.allCategories')}</option>
          {cats.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <select className={`${selectCls} w-32`} aria-label={t('doc.review')} value={review} onChange={(e) => setReview(e.target.value)}>
          <option value="">{t('t360.allReview')}</option>
          {['pending', 'approved', 'rejected'].map((s) => <option key={s} value={s}>{t(`t360.rv.${s}` as never)}</option>)}
        </select>
        <select className={`${selectCls} w-32`} aria-label={t('doc.expires')} value={expired} onChange={(e) => setExpired(e.target.value)}>
          <option value="">{t('t360.allValidity')}</option>
          <option value="0">{t('t360.valid')}</option>
          <option value="1">{t('doc.expired')}</option>
        </select>
        <select className={`${selectCls} w-32`} aria-label={t('t360.source')} value={source} onChange={(e) => setSource(e.target.value)}>
          <option value="">{t('t360.allSources')}</option>
          {['hub', 'projects', 'docs'].map((s) => <option key={s} value={s}>{t(`t360.src.${s}` as never)}</option>)}
        </select>
      </FilterBar>
      {list.loading && !list.rows.length ? <Loading /> : (
        <DataTable rows={list.rows} cols={[
          { key: 'title', label: t('doc.title'), render: (r) => (
            <button type="button" className="text-start font-medium text-primary hover:underline" onClick={() => setDetail(r)}>
              {r.title}{Number(r.version) > 1 ? <Badge variant="outline" className="ms-1">v{r.version}</Badge> : null}
            </button>
          ) },
          { key: 'category', label: t('doc.category'), render: (r) => t(`dc.${(DOC_CATEGORIES as readonly string[]).includes(r.category) ? r.category : 'other'}` as never) },
          { key: 'size', label: t('t360.size'), render: (r) => fmtSize(r.size, f.num) },
          { key: 'expires_at', label: t('doc.expires'), render: (r) => <span>{f.date(r.expires_at)}{r.expired ? <Badge className="ms-1" variant="destructive">{t('doc.expired')}</Badge> : null}</span> },
          { key: 'review_status', label: t('doc.review'), render: (r) => (r.review_status ? <StatusBadge value={r.review_status} label={t(`t360.rv.${r.review_status}` as never)} /> : '—') },
          { key: 'created_at', label: t('t360.uploadedAt'), render: (r) => f.date(r.created_at) },
          { key: 'ops', label: '', render: (r) => (
            <div className="flex gap-1">
              {r.has_file || r.url ? <Button size="icon" variant="ghost" aria-label={t('doc.download')} title={r.file_name ?? t('doc.download')} onClick={() => download(r)}><Download className="h-4 w-4" /></Button> : null}
              {r.source === 'hub' && r.can?.update ? <Button size="icon" variant="ghost" aria-label={t('t360.newVersion')} title={t('t360.newVersion')} onClick={() => setForm({ mode: 'version', row: r })}><Upload className="h-4 w-4" /></Button> : null}
              {r.source === 'hub' && r.can?.delete ? <Button size="icon" variant="ghost" aria-label={t('delete')} onClick={() => void remove(r)}><Trash2 className="h-4 w-4" /></Button> : null}
            </div>
          ) },
        ]} />
      )}
      <Pager meta={list.meta} onPage={list.setPage} />

      <TabForm
        open={!!form}
        onOpenChange={(o) => { if (!o) setForm(null); }}
        title={form?.mode === 'version' ? t('t360.newVersion') : form?.mode === 'edit' ? t('edit') : t('t360.upload')}
        fields={fields}
        initial={form?.mode === 'edit' ? {
          title: form.row.title ?? '', category: form.row.category ?? 'other', expires_at: form.row.expires_at ?? '',
          tags: (form.row.tags ?? []).join(', '), visible_to_roles: (form.row.visible_to_roles ?? []).join(', '),
        } : form?.mode === 'create' ? { category: 'other' } : {}}
        onSubmit={submit}
      />
      {detail ? (
        <DocumentDetail
          row={detail}
          onClose={() => setDetail(null)}
          onEdit={() => setForm({ mode: 'edit', row: detail })}
          onVersion={() => setForm({ mode: 'version', row: detail })}
          onReview={(s) => void doReview(detail, s)}
          onDelete={() => void remove(detail)}
          onDownload={download}
        />
      ) : null}
    </HubCard>
  );
}

function DocumentDetail({ row, onClose, onEdit, onVersion, onReview, onDelete, onDownload }: {
  row: Row; onClose: () => void; onEdit: () => void; onVersion: () => void; onReview: (s: 'approved' | 'rejected') => void; onDelete: () => void; onDownload: (r: Row) => void;
}) {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { id, version, setNotice } = useHub();
  const [doc, setDoc] = useState<Row | null>(row.source === 'hub' ? null : row);
  const [preview, setPreview] = useState<string | null>(null);
  useEffect(() => {
    if (row.source !== 'hub') return;
    let live = true;
    apiClient.get(`${hubBase(id)}/documents/${row.id}`)
      .then((res) => { if (live) setDoc((res.data as { data: Row }).data); })
      .catch((e) => { if (live) { setDoc(row); setNotice(getApiErrorMessage(e)); } });
    return () => { live = false; };
  }, [id, row, version, setNotice]);
  const canPreview = row.source === 'hub' && row.has_file && PREVIEWABLE.test(String(row.mime ?? ''));
  const loadPreview = async () => {
    try {
      const res = await apiClient.post(`${hubBase(id)}/documents/${row.id}/download-link`);
      setPreview((res.data as { data: { url: string } }).data.url);
    } catch (e) {
      setNotice(getApiErrorMessage(e));
    }
  };
  const d = doc ?? row;
  return (
    <DetailSheet open onOpenChange={(o) => { if (!o) onClose(); }} title={d.title}>
      <div className="flex flex-wrap gap-2">
        {d.has_file || d.url ? <Button size="sm" variant="outline" onClick={() => onDownload(d)}><Download className="me-1 h-4 w-4" />{t('doc.download')}</Button> : null}
        {canPreview && !preview ? <Button size="sm" variant="outline" onClick={() => void loadPreview()}><Eye className="me-1 h-4 w-4" />{t('t360.preview')}</Button> : null}
        {d.source === 'hub' && d.can?.update ? <>
          <Button size="sm" variant="outline" onClick={onEdit}>{t('edit')}</Button>
          <Button size="sm" variant="outline" onClick={onVersion}><Upload className="me-1 h-4 w-4" />{t('t360.newVersion')}</Button>
        </> : null}
        {d.source === 'hub' && d.can?.review ? <>
          {d.review_status !== 'approved' ? <Button size="sm" variant="ghost" onClick={() => onReview('approved')}>{t('doc.approve')}</Button> : null}
          {d.review_status !== 'rejected' ? <Button size="sm" variant="ghost" onClick={() => onReview('rejected')}>{t('doc.reject')}</Button> : null}
        </> : null}
        {d.source === 'hub' && d.can?.delete ? <Button size="sm" variant="ghost" className="text-destructive" onClick={onDelete}><Trash2 className="me-1 h-4 w-4" />{t('delete')}</Button> : null}
      </div>
      {preview ? (
        String(d.mime).startsWith('image/')
          // eslint-disable-next-line @next/next/no-img-element -- short-lived signed URL, not optimisable
          ? <img src={preview} alt={d.title} className="max-h-96 w-full rounded border object-contain" />
          : <iframe src={preview} title={d.title} className="h-96 w-full rounded border" />
      ) : null}
      <KV items={[
        [t('doc.category'), t(`dc.${(DOC_CATEGORIES as readonly string[]).includes(d.category) ? d.category : 'other'}` as never)],
        [t('t360.version'), `v${d.version ?? 1}`],
        [t('t360.fileName'), d.file_name ? <span dir="ltr">{d.file_name}</span> : null],
        [t('t360.size'), fmtSize(d.size, f.num)],
        [t('doc.expires'), d.expires_at ? <span>{f.date(d.expires_at)}{d.expired ? <Badge className="ms-1" variant="destructive">{t('doc.expired')}</Badge> : null}</span> : null],
        [t('doc.review'), d.review_status ? t(`t360.rv.${d.review_status}` as never) : null],
        [t('t360.reviewedBy'), d.reviewed_by?.name ? `${d.reviewed_by.name} — ${f.dateTime(d.reviewed_at)}` : null],
        [t('t360.reviewNote'), d.review_note],
        [t('t360.tags'), Array.isArray(d.tags) && d.tags.length ? d.tags.join('، ') : null],
        [t('t360.contract'), d.contract?.title],
        [t('t360.project'), d.project?.name],
        [t('t360.uploadedBy'), d.created_by?.name],
        [t('t360.uploadedAt'), f.dateTime(d.created_at)],
      ]} />
      {Array.isArray(d.versions) && d.versions.length > 1 ? (
        <div>
          <h4 className="mb-2 flex items-center gap-1 font-medium"><History className="h-4 w-4" />{t('t360.versions')}</h4>
          <DataTable rows={d.versions} cols={[
            { key: 'version', label: t('t360.version'), render: (v) => `v${v.version}` },
            { key: 'file_name', label: t('t360.fileName'), render: (v) => <span dir="ltr">{v.file_name ?? '—'}</span> },
            { key: 'size', label: t('t360.size'), render: (v) => fmtSize(v.size, f.num) },
            { key: 'created_at', label: t('t360.uploadedAt'), render: (v) => f.dateTime(v.created_at) },
            { key: 'dl', label: '', render: (v) => (v.has_file ? <Button size="icon" variant="ghost" aria-label={`${t('doc.download')} v${v.version}`} onClick={() => void openDocument(id, v.id).catch((e) => setNotice(getApiErrorMessage(e)))}><Download className="h-4 w-4" /></Button> : null) },
          ]} />
        </div>
      ) : null}
    </DetailSheet>
  );
}
