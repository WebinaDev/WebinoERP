'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { AiAssist } from '@/features/ai/content-assist';

export type MarketingField = {
  key: string;
  labelKey: string;
  /** `lines` = string array edited one item per line; `select` loads options from `optionsEndpoint`. */
  kind?: 'text' | 'textarea' | 'checkbox' | 'number' | 'lines' | 'select';
  optionsEndpoint?: string;
  /** Fixed choices for `select` (value stored as string). */
  options?: { value: string; label: string }[];
  optionLabel?: string;
};

type MarketingResourcePageProps = {
  titleKey: string;
  endpoint: string;
  columns: { key: string; labelKey: string }[];
  fields?: MarketingField[];
  /** Adds the SEO block (title, description, og image, canonical, noindex) with AI. `meta` = stored in row.meta.seo and saved via `${endpoint}/{id}/seo`. */
  seo?: 'column' | 'meta';
};

export function MarketingResourcePage({ titleKey, endpoint, columns, fields, seo }: MarketingResourcePageProps) {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [draft, setDraft] = useState<Record<string, string> | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const formFields: MarketingField[] = fields ?? columns.map((c) => ({ key: c.key, labelKey: c.labelKey, kind: 'text' as const }));
  const [seoDraft, setSeoDraft] = useState<SeoValue>({});
  const [options, setOptions] = useState<Record<string, { value: string; label: string }[]>>({});
  const selectSources = formFields.filter((f) => f.kind === 'select' && f.optionsEndpoint).map((f) => `${f.key}|${f.optionsEndpoint}|${f.optionLabel ?? 'name'}`).join(',');

  useEffect(() => {
    if (!selectSources) return;
    void Promise.all(
      selectSources.split(',').map(async (src) => {
        const [key, url, label] = src.split('|');
        try {
          const res = await apiClient.get(url);
          return [key, normalizeListPayload(res.data).map((r) => ({ value: String(r.id), label: String(r[label] ?? r.id) }))] as const;
        } catch {
          return [key, []] as const;
        }
      }),
    ).then((pairs) => setOptions(Object.fromEntries(pairs)));
  }, [selectSources]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get(endpoint);
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, endpoint]);

  useEffect(() => {
    void load();
  }, [load]);

  function openNew() {
    setEditingId(null);
    setDraft(Object.fromEntries(formFields.map((f) => [f.key, f.kind === 'checkbox' ? 'false' : ''])));
    setSeoDraft({});
  }

  function openEdit(row: Record<string, unknown>) {
    setEditingId(Number(row.id));
    const stored = seo === 'meta' ? (row.meta as Record<string, unknown> | null)?.seo : row.seo;
    setSeoDraft(stored && typeof stored === 'object' ? (stored as SeoValue) : {});
    setDraft(Object.fromEntries(formFields.map((f) => {
      const v = row[f.key];
      if (f.kind === 'lines') return [f.key, Array.isArray(v) ? v.join('\n') : ''];
      if (f.kind === 'checkbox') return [f.key, v ? 'true' : 'false'];
      return [f.key, v === null || v === undefined ? '' : String(v)];
    })));
  }

  async function save() {
    if (!draft) return;
    const body: Record<string, unknown> = {};
    for (const field of formFields) {
      const raw = draft[field.key] ?? '';
      if (field.kind === 'checkbox') body[field.key] = raw === 'true' || raw === '1';
      else if (field.kind === 'lines') body[field.key] = raw.split('\n').map((x) => x.trim()).filter(Boolean);
      else if (field.kind === 'number' || (field.kind === 'select' && field.optionsEndpoint)) body[field.key] = raw === '' ? null : Number(raw);
      else if (field.kind === 'select') body[field.key] = raw === '' ? null : raw;
      else if (raw !== '' || editingId) body[field.key] = raw;
    }
    if (seo === 'column') body.seo = seoDraft;
    try {
      let id = editingId;
      if (editingId) await apiClient.put(`${endpoint}/${editingId}`, body);
      else id = Number((await apiClient.post(endpoint, body)).data?.data?.id ?? 0) || null;
      if (seo === 'meta' && id) await apiClient.put(`${endpoint}/${id}/seo`, { seo: seoDraft });
      setSuccess(tNav('common.saved'));
      setDraft(null);
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  async function remove(id: number) {
    try {
      await apiClient.delete(`${endpoint}/${id}`);
      setSuccess(tNav('common.deleted'));
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  return (
    <CrmPageLayout
      title={tNav(titleKey)}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openNew}>
          <Plus className="size-4" aria-hidden />
          {tNav('common.create')}
        </Button>
      }
    >
      {draft ? (
        <Card className="mb-4">
          <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
            {formFields.map((field) => (
              <label key={field.key} className="grid gap-1 text-sm">
                <span className="flex items-center justify-between gap-2">
                  {tNav(field.labelKey)}
                  {!field.kind || field.kind === 'text' || field.kind === 'textarea' || field.kind === 'lines' ? (
                    <AiAssist
                      value={draft[field.key] ?? ''}
                      onApply={(next) => setDraft((d) => (d ? { ...d, [field.key]: next } : d))}
                      surface={endpoint.replace(/^\/v1\/marketing\//, '').replace(/\//g, '.')}
                      field={field.key}
                      kind={field.key === 'body' ? 'html' : field.kind === 'text' || !field.kind ? 'text' : 'textarea'}
                      targetId={editingId}
                      context={Object.fromEntries(['title', 'name', 'question', 'excerpt', 'description', 'group', 'category'].filter((k) => k !== field.key && draft[k]).map((k) => [k, draft[k]]))}
                    />
                  ) : null}
                </span>
                {field.kind === 'select' ? (
                  <select
                    className="border-input h-9 rounded-md border bg-transparent px-3"
                    value={draft[field.key] ?? ''}
                    onChange={(e) => setDraft({ ...draft, [field.key]: e.target.value })}
                  >
                    <option value="">—</option>
                    {(field.options ?? options[field.key] ?? []).map((o) => (
                      <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                  </select>
                ) : field.kind === 'textarea' || field.kind === 'lines' ? (
                  <textarea
                    className="border-input min-h-24 rounded-md border bg-transparent px-3 py-2"
                    value={draft[field.key] ?? ''}
                    onChange={(e) => setDraft({ ...draft, [field.key]: e.target.value })}
                  />
                ) : field.kind === 'checkbox' ? (
                  <input
                    type="checkbox"
                    checked={draft[field.key] === 'true'}
                    onChange={(e) => setDraft({ ...draft, [field.key]: e.target.checked ? 'true' : 'false' })}
                  />
                ) : (
                  <input
                    type={field.kind === 'number' ? 'number' : 'text'}
                    className="border-input h-9 rounded-md border bg-transparent px-3"
                    value={draft[field.key] ?? ''}
                    onChange={(e) => setDraft({ ...draft, [field.key]: e.target.value })}
                  />
                )}
              </label>
            ))}
            {seo ? (
              <SeoFields
                value={seoDraft}
                onChange={setSeoDraft}
                surface={endpoint.replace(/^\/v1\/marketing\//, '').replace(/\//g, '.')}
                targetId={editingId}
                context={Object.fromEntries(['title', 'name', 'title_fa', 'excerpt', 'summary', 'description', 'body', 'path'].filter((k) => draft[k]).map((k) => [k, draft[k]]))}
              />
            ) : null}
            <div className="flex gap-2 md:col-span-2">
              <Button size="sm" onClick={() => void save()}>{tNav('common.save')}</Button>
              <Button size="sm" variant="outline" onClick={() => setDraft(null)}>{tNav('common.cancel')}</Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                {columns.map((c) => (
                  <TableHead key={c.key}>{tNav(c.labelKey)}</TableHead>
                ))}
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  {columns.map((c) => (
                    <TableCell key={c.key}>{String(r[c.key] ?? '')}</TableCell>
                  ))}
                  <TableCell className="flex justify-end gap-1">
                    <Button size="icon" variant="ghost" onClick={() => openEdit(r)} aria-label={tNav('common.edit')}>
                      <Pencil className="size-4" />
                    </Button>
                    <Button size="icon" variant="ghost" onClick={() => void remove(Number(r.id))} aria-label={tNav('common.delete')}>
                      <Trash2 className="size-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}

type SeoValue = { title?: string; description?: string; og_image?: string; canonical?: string; noindex?: boolean };

/** Editable SEO overrides; empty fields fall back to the item's title / excerpt / cover on the site. */
export function SeoFields({ value, onChange, surface, targetId, context }: { value: SeoValue; onChange: (v: SeoValue) => void; surface: string; targetId?: number | null; context: Record<string, string> }) {
  const set = (k: keyof SeoValue, v: string | boolean) => onChange({ ...value, [k]: v });
  const base = context.title || context.name || context.title_fa || context.path || '';
  const src = [base, context.excerpt || context.summary || context.description || context.body || ''].filter(Boolean).join('\n');
  const box = 'border-input h-9 rounded-md border bg-transparent px-3';
  return (
    <fieldset className="grid gap-3 rounded-lg border p-3 md:col-span-2">
      <legend className="px-1 text-sm font-semibold">سئو</legend>
      <label className="grid gap-1 text-sm">
        <span className="flex items-center justify-between">عنوان سئو ({(value.title ?? '').length}/۶۰)<AiAssist value={value.title || src} onApply={(v) => set('title', v)} surface={surface} field="seo_title" targetId={targetId} context={context} /></span>
        <input className={box} value={value.title ?? ''} placeholder={base} onChange={(e) => set('title', e.target.value)} />
      </label>
      <label className="grid gap-1 text-sm">
        <span className="flex items-center justify-between">توضیح متا ({(value.description ?? '').length}/۱۵۵)<AiAssist value={value.description || src} onApply={(v) => set('description', v)} surface={surface} field="seo_description" targetId={targetId} context={context} /></span>
        <textarea className="border-input min-h-16 rounded-md border bg-transparent px-3 py-2" value={value.description ?? ''} onChange={(e) => set('description', e.target.value)} />
      </label>
      <div className="grid gap-3 md:grid-cols-2">
        <label className="grid gap-1 text-sm">تصویر اشتراک (og:image)<input dir="ltr" className={box} value={value.og_image ?? ''} onChange={(e) => set('og_image', e.target.value)} /></label>
        <label className="grid gap-1 text-sm">canonical (اختیاری)<input dir="ltr" className={box} value={value.canonical ?? ''} onChange={(e) => set('canonical', e.target.value)} /></label>
      </div>
      <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={value.noindex === true} onChange={(e) => set('noindex', e.target.checked)} />noindex (از نتایج جست‌وجو و نقشه سایت حذف شود)</label>
    </fieldset>
  );
}
