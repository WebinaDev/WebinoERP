'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { PmConfirmDialog, PmEmptyState, PmFilterBar, PmPagination } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale-next';

export type EntityField = {
  name: string;
  label: string;
  type?: 'text' | 'number' | 'date' | 'textarea';
  required?: boolean;
};

export type EntityColumn = {
  key: string;
  label: string;
  render?: (row: Record<string, unknown>) => React.ReactNode;
};

type Props = {
  titleKey: string;
  descriptionKey?: string;
  apiPath: string;
  columns: EntityColumn[];
  fields: EntityField[];
  searchPlaceholder?: string;
  idKey?: string;
};

export function EntityCrudPage({
  titleKey,
  descriptionKey,
  apiPath,
  columns,
  fields,
  searchPlaceholder,
  idKey = 'id',
}: Props) {
  const t = useTranslations();
  const { formatDate } = useLocale();
  const title = t(titleKey);
  const description = descriptionKey ? t(descriptionKey) : undefined;

  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Record<string, unknown> | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [deleteId, setDeleteId] = useState<string | number | null>(null);
  const [pending, setPending] = useState(false);

  const emptyForm = useMemo(() => {
    const o: Record<string, string> = {};
    for (const f of fields) o[f.name] = '';
    return o;
  }, [fields]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(apiPath, {
        params: { page, per_page: 15, search: search || undefined },
      });
      const body = res.data as { data?: unknown; meta?: typeof meta };
      setRows(normalizeListPayload(body));
      if (body.meta) setMeta(body.meta);
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [apiPath, page, search]);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setDialogOpen(true);
  }

  function openEdit(row: Record<string, unknown>) {
    setEditing(row);
    const o: Record<string, string> = {};
    for (const f of fields) {
      const v = row[f.name];
      o[f.name] = v == null ? '' : String(v);
    }
    setForm(o);
    setDialogOpen(true);
  }

  async function save() {
    setPending(true);
    setError(null);
    try {
      const payload: Record<string, unknown> = {};
      for (const f of fields) {
        const v = form[f.name];
        if (v !== '') payload[f.name] = f.type === 'number' ? Number(v) : v;
      }
      if (editing) {
        await apiClient.patch(`${apiPath}/${editing[idKey]}`, payload);
        setSuccess(t('common.saved'));
      } else {
        await apiClient.post(apiPath, payload);
        setSuccess(t('common.created'));
      }
      setDialogOpen(false);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setPending(false);
    }
  }

  async function confirmDelete() {
    if (deleteId == null) return;
    setPending(true);
    try {
      await apiClient.delete(`${apiPath}/${deleteId}`);
      setSuccess(t('common.deleted'));
      setDeleteId(null);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setPending(false);
    }
  }

  function cellValue(row: Record<string, unknown>, col: EntityColumn) {
    if (col.render) return col.render(row);
    const v = row[col.key];
    if (v == null) return '—';
    if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v)) return formatDate(v);
    return String(v);
  }

  return (
    <CrmPageLayout
      title={title}
      description={description}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button type="button" onClick={openCreate}>
          {t('common.add')}
        </Button>
      }
    >
      <PmFilterBar onApply={() => void load()}>
        <Input
          placeholder={searchPlaceholder ?? t('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
      </PmFilterBar>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/50">
                  {columns.map((c) => (
                    <th key={c.key} className="px-4 py-3 text-start font-medium">
                      {c.label}
                    </th>
                  ))}
                  <th className="px-4 py-3 text-end font-medium">{t('common.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-muted-foreground">
                      {t('common.loading')}
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length + 1}>
                      <PmEmptyState title={t('common.noData')} />
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={String(row[idKey])} className="border-b last:border-0">
                      {columns.map((c) => (
                        <td key={c.key} className="px-4 py-3">
                          {cellValue(row, c)}
                        </td>
                      ))}
                      <td className="px-4 py-3 text-end">
                        <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(row)}>
                          {t('common.edit')}
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-destructive"
                          onClick={() => setDeleteId(row[idKey] as string | number)}
                        >
                          {t('common.delete')}
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <PmPagination page={page} lastPage={meta.last_page ?? 1} onPage={setPage} />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('common.edit') : t('common.add')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            {fields.map((f) => (
              <div key={f.name} className="grid gap-1">
                <label className="text-sm font-medium">{f.label}</label>
                {f.type === 'textarea' ? (
                  <Textarea
                    value={form[f.name] ?? ''}
                    onChange={(e) => setForm((s) => ({ ...s, [f.name]: e.target.value }))}
                  />
                ) : (
                  <Input
                    type={f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'}
                    value={form[f.name] ?? ''}
                    required={f.required}
                    onChange={(e) => setForm((s) => ({ ...s, [f.name]: e.target.value }))}
                  />
                )}
              </div>
            ))}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="button" onClick={() => void save()} disabled={pending}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        title={t('common.confirmDelete')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteId(null)}
        pending={pending}
      />
    </CrmPageLayout>
  );
}
