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

export type MarketingField = {
  key: string;
  labelKey: string;
  /** `lines` = string array edited one item per line; `select` loads options from `optionsEndpoint`. */
  kind?: 'text' | 'textarea' | 'checkbox' | 'number' | 'lines' | 'select';
  optionsEndpoint?: string;
  optionLabel?: string;
};

type MarketingResourcePageProps = {
  titleKey: string;
  endpoint: string;
  columns: { key: string; labelKey: string }[];
  fields?: MarketingField[];
};

export function MarketingResourcePage({ titleKey, endpoint, columns, fields }: MarketingResourcePageProps) {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [draft, setDraft] = useState<Record<string, string> | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const formFields: MarketingField[] = fields ?? columns.map((c) => ({ key: c.key, labelKey: c.labelKey, kind: 'text' as const }));
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
  }

  function openEdit(row: Record<string, unknown>) {
    setEditingId(Number(row.id));
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
      else if (field.kind === 'number' || field.kind === 'select') body[field.key] = raw === '' ? null : Number(raw);
      else if (raw !== '' || editingId) body[field.key] = raw;
    }
    try {
      if (editingId) await apiClient.put(`${endpoint}/${editingId}`, body);
      else await apiClient.post(endpoint, body);
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
                <span>{tNav(field.labelKey)}</span>
                {field.kind === 'select' ? (
                  <select
                    className="border-input h-9 rounded-md border bg-transparent px-3"
                    value={draft[field.key] ?? ''}
                    onChange={(e) => setDraft({ ...draft, [field.key]: e.target.value })}
                  >
                    <option value="">—</option>
                    {(options[field.key] ?? []).map((o) => (
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
