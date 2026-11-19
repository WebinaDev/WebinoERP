'use client';

import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { Plus, RefreshCw, Trash2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { formatMoney } from '@/lib/money';
import { formatDate } from '@/lib/locale/format-date';
import { dashboardHref } from '@/lib/route-resolver';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';

/* ------------------------------------------------------------------ */
/* Types                                                               */
/* ------------------------------------------------------------------ */

export type Row = Record<string, unknown>;

export type ColumnType = 'text' | 'money' | 'number' | 'date' | 'datetime' | 'badge' | 'bool';

export type ErpColumn = {
  /** dot path into the row, e.g. "company.name" */
  key: string;
  /** key under erp.fields */
  label: string;
  type?: ColumnType;
  render?: (row: Row) => ReactNode;
};

export type FieldType = 'text' | 'number' | 'date' | 'textarea' | 'select' | 'bool' | 'remote' | 'list' | 'lines';

export type RemoteSource = {
  endpoint: string;
  /** dot paths tried in order for the option label */
  labelKeys?: string[];
  params?: Record<string, unknown>;
};

export type LineField = { name: string; label: string; type: 'number' | 'text' | 'remote' | 'select'; remote?: RemoteSource; options?: string[]; required?: boolean };

export type ErpField = {
  name: string;
  label: string;
  type?: FieldType;
  required?: boolean;
  /** select values; labels come from erp.values.<value> */
  options?: string[];
  remote?: RemoteSource;
  /** for type=lines */
  lineFields?: LineField[];
  defaultValue?: unknown;
};

export type ErpRowAction = {
  label: string;
  endpoint: (row: Row) => string;
  method?: 'post' | 'patch' | 'put';
  fields?: ErpField[];
  visible?: (row: Row) => boolean;
  /** body merged into the form values */
  body?: (row: Row) => Record<string, unknown>;
};

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

export function getPath(row: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object') return (acc as Record<string, unknown>)[part];
    return undefined;
  }, row);
}

/** Accepts plain arrays, {data: []}, Laravel paginators and {data: paginator}. */
export function extractRows(raw: unknown): { rows: Row[]; lastPage: number } {
  let cur: unknown = raw;
  for (let i = 0; i < 3; i++) {
    if (Array.isArray(cur)) return { rows: cur as Row[], lastPage: 1 };
    if (cur && typeof cur === 'object') {
      const o = cur as Record<string, unknown>;
      if (Array.isArray(o.data)) {
        const meta = (o.meta ?? o) as Record<string, unknown>;
        return { rows: o.data as Row[], lastPage: Number(meta.last_page ?? 1) || 1 };
      }
      cur = o.data;
    } else break;
  }
  return { rows: [], lastPage: 1 };
}

function optionLabel(row: Row, keys?: string[]): string {
  for (const k of keys ?? ['name', 'title', 'display_name', 'full_name', 'email']) {
    const v = getPath(row, k);
    if (v !== undefined && v !== null && String(v) !== '') return String(v);
  }
  return `#${String(row.id ?? '')}`;
}

function useRemoteOptions(source?: RemoteSource) {
  const [options, setOptions] = useState<{ value: string; label: string }[]>([]);
  const key = source ? source.endpoint + JSON.stringify(source.params ?? {}) : '';
  useEffect(() => {
    if (!source) return;
    let alive = true;
    apiClient
      .get(source.endpoint, { params: { per_page: 200, ...(source.params ?? {}) } })
      .then((res) => {
        if (!alive) return;
        setOptions(extractRows(res.data).rows.map((r) => ({ value: String(r.id), label: optionLabel(r, source.labelKeys) })));
      })
      .catch(() => alive && setOptions([]));
    return () => {
      alive = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  return options;
}

const selectClass =
  'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

/* ------------------------------------------------------------------ */
/* Cell rendering                                                      */
/* ------------------------------------------------------------------ */

function useCellRenderer() {
  const locale = useLocale();
  const tv = useTranslations('erp.values');
  return useCallback(
    (row: Row, col: ErpColumn): ReactNode => {
      if (col.render) return col.render(row);
      const v = getPath(row, col.key);
      if (v === null || v === undefined || v === '') return <span className="text-muted-foreground">-</span>;
      switch (col.type) {
        case 'money':
          return <span className="tabular-nums">{formatMoney(Number(v), { locale })}</span>;
        case 'number':
          return <span className="tabular-nums">{Number(v).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US')}</span>;
        case 'date':
          return formatDate(String(v), { locale });
        case 'datetime':
          return formatDate(String(v), { locale, includeTime: true });
        case 'bool':
          return <Badge variant={v ? 'default' : 'secondary'}>{tv(v ? 'yes' : 'no')}</Badge>;
        case 'badge': {
          const s = String(v);
          return <Badge variant="outline">{tv.has(s) ? tv(s) : s}</Badge>;
        }
        default:
          return String(v);
      }
    },
    [locale, tv],
  );
}

/* ------------------------------------------------------------------ */
/* Form                                                                */
/* ------------------------------------------------------------------ */

function FieldInput({ field, value, onChange }: { field: ErpField; value: unknown; onChange: (v: unknown) => void }) {
  const tf = useTranslations('erp.fields');
  const tv = useTranslations('erp.values');
  const tr = useTranslations('erp.resource');
  const remote = useRemoteOptions(field.type === 'remote' ? field.remote : undefined);
  const id = `f-${field.name}`;
  const label = (
    <Label htmlFor={id} className="text-xs">
      {tf(field.label)}
      {field.required ? <span className="text-destructive ms-0.5">*</span> : null}
    </Label>
  );

  switch (field.type) {
    case 'textarea':
      return (
        <div className="space-y-1.5 sm:col-span-2">
          {label}
          <Textarea id={id} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} rows={3} />
        </div>
      );
    case 'date':
      return (
        <div className="space-y-1.5">
          {label}
          <LocaleDatePicker value={value ? String(value) : undefined} onChange={(iso) => onChange(iso)} />
        </div>
      );
    case 'bool':
      return (
        <label className="flex items-center gap-2 text-sm sm:pt-6">
          <input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} className="size-4" />
          {tf(field.label)}
        </label>
      );
    case 'select':
      return (
        <div className="space-y-1.5">
          {label}
          <select id={id} className={selectClass} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
            <option value="">{tr('choose')}</option>
            {(field.options ?? []).map((o) => (
              <option key={o} value={o}>
                {tv.has(o) ? tv(o) : o}
              </option>
            ))}
          </select>
        </div>
      );
    case 'remote':
      return (
        <div className="space-y-1.5">
          {label}
          <select id={id} className={selectClass} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
            <option value="">{tr('choose')}</option>
            {remote.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
      );
    case 'list':
      return (
        <div className="space-y-1.5 sm:col-span-2">
          {label}
          <Textarea
            id={id}
            rows={3}
            value={Array.isArray(value) ? (value as unknown[]).join('\n') : String(value ?? '')}
            onChange={(e) => onChange(e.target.value)}
            placeholder={tr('onePerLine')}
          />
        </div>
      );
    case 'lines':
      return <LinesInput field={field} value={value} onChange={onChange} />;
    default:
      return (
        <div className="space-y-1.5">
          {label}
          <Input
            id={id}
            type={field.type === 'number' ? 'number' : 'text'}
            inputMode={field.type === 'number' ? 'decimal' : undefined}
            value={String(value ?? '')}
            onChange={(e) => onChange(e.target.value)}
          />
        </div>
      );
  }
}

function LineCell({ lf, value, onChange }: { lf: LineField; value: unknown; onChange: (v: unknown) => void }) {
  const tf = useTranslations('erp.fields');
  const tv = useTranslations('erp.values');
  const tr = useTranslations('erp.resource');
  const remote = useRemoteOptions(lf.type === 'remote' ? lf.remote : undefined);
  if (lf.type === 'select') {
    return (
      <select aria-label={tf(lf.label)} className={selectClass} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
        <option value="">{tf(lf.label)}</option>
        {(lf.options ?? []).map((o) => (
          <option key={o} value={o}>
            {tv.has(o) ? tv(o) : o}
          </option>
        ))}
      </select>
    );
  }
  if (lf.type === 'remote') {
    return (
      <select aria-label={tf(lf.label)} className={selectClass} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
        <option value="">{tr('choose')}</option>
        {remote.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    );
  }
  return (
    <Input
      aria-label={tf(lf.label)}
      placeholder={tf(lf.label)}
      type={lf.type === 'number' ? 'number' : 'text'}
      value={String(value ?? '')}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

function LinesInput({ field, value, onChange }: { field: ErpField; value: unknown; onChange: (v: unknown) => void }) {
  const tf = useTranslations('erp.fields');
  const tr = useTranslations('erp.resource');
  const rows = (Array.isArray(value) ? value : [{}]) as Row[];
  const set = (i: number, name: string, v: unknown) => onChange(rows.map((r, j) => (j === i ? { ...r, [name]: v } : r)));
  return (
    <div className="space-y-2 sm:col-span-2">
      <Label className="text-xs">{tf(field.label)}</Label>
      {rows.map((r, i) => (
        <div key={i} className="grid grid-cols-1 gap-2 rounded-lg border p-2 sm:grid-cols-[repeat(var(--n),minmax(0,1fr))_auto]" style={{ ['--n' as string]: field.lineFields?.length ?? 1 }}>
          {(field.lineFields ?? []).map((lf) => (
            <LineCell key={lf.name} lf={lf} value={r[lf.name]} onChange={(v) => set(i, lf.name, v)} />
          ))}
          <Button type="button" variant="ghost" size="icon" aria-label={tr('removeLine')} onClick={() => onChange(rows.filter((_, j) => j !== i))} disabled={rows.length < 2}>
            <Trash2 className="size-4" />
          </Button>
        </div>
      ))}
      <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rows, {}])}>
        <Plus className="size-4 me-1" />
        {tr('addLine')}
      </Button>
    </div>
  );
}

function normalizePayload(fields: ErpField[], values: Row): Row {
  const out: Row = {};
  for (const f of fields) {
    let v = values[f.name];
    if (v === '' || v === undefined) {
      if (f.type === 'bool') out[f.name] = false;
      continue;
    }
    if (f.type === 'number') v = Number(v);
    if (f.type === 'remote') v = Number(v);
    if (f.type === 'list' && typeof v === 'string') v = v.split('\n').map((s) => s.trim()).filter(Boolean);
    if (f.type === 'lines' && Array.isArray(v)) {
      v = (v as Row[])
        .filter((r) => Object.values(r).some((x) => x !== '' && x !== undefined))
        .map((r) => {
          const line: Row = {};
          for (const lf of f.lineFields ?? []) {
            const x = r[lf.name];
            if (x === '' || x === undefined) continue;
            line[lf.name] = lf.type === 'text' || lf.type === 'select' ? x : Number(x);
          }
          return line;
        });
    }
    out[f.name] = v;
  }
  return out;
}

export function ErpFormDialog({
  open,
  onOpenChange,
  title,
  fields,
  submit,
}: {
  open: boolean;
  onOpenChange: (o: boolean) => void;
  title: string;
  fields: ErpField[];
  submit: (payload: Row) => Promise<void>;
}) {
  const tr = useTranslations('erp.resource');
  const initial = useMemo(() => Object.fromEntries(fields.map((f) => [f.name, f.defaultValue ?? (f.type === 'lines' ? [{}] : '')])), [fields]);
  const [values, setValues] = useState<Row>(initial);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setValues(initial);
      setError(null);
    }
  }, [open, initial]);

  const onSubmit = async () => {
    setSaving(true);
    setError(null);
    try {
      await submit(normalizePayload(fields, values));
      onOpenChange(false);
    } catch (e) {
      setError(getAxiosMessage(e) || tr('saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {fields.map((f) => (
            <FieldInput key={f.name} field={f} value={values[f.name]} onChange={(v) => setValues((s) => ({ ...s, [f.name]: v }))} />
          ))}
        </div>
        {error ? <p className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p> : null}
        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
            {tr('cancel')}
          </Button>
          <Button onClick={onSubmit} disabled={saving}>
            {saving ? tr('saving') : tr('save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/* ------------------------------------------------------------------ */
/* Page                                                                */
/* ------------------------------------------------------------------ */

export function ErpResourcePage({
  ns,
  descriptionKey,
  endpoint,
  params,
  columns,
  createFields,
  createEndpoint,
  createLabel,
  rowActions,
  children,
}: {
  /** i18n namespace of the module, e.g. "erp.org" */
  ns: string;
  descriptionKey?: string;
  endpoint: string;
  params?: Record<string, unknown>;
  columns: ErpColumn[];
  createFields?: ErpField[];
  createEndpoint?: string;
  /** key under the module namespace for the create button / dialog title */
  createLabel?: string;
  rowActions?: ErpRowAction[];
  children?: ReactNode;
}) {
  const t = useTranslations(ns);
  const tf = useTranslations('erp.fields');
  const tr = useTranslations('erp.resource');
  const renderCell = useCellRenderer();
  const [rows, setRows] = useState<Row[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [action, setAction] = useState<{ action: ErpRowAction; row: Row } | null>(null);
  const paramsKey = JSON.stringify(params ?? {});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(endpoint, { params: { page, ...(params ?? {}) } });
      const { rows: r, lastPage: lp } = extractRows(res.data);
      setRows(r);
      setLastPage(lp);
    } catch (e) {
      setError(getAxiosMessage(e) || tr('loadFailed'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint, page, paramsKey, tr]);

  useEffect(() => {
    void load();
  }, [load]);

  const runAction = async (a: ErpRowAction, row: Row, payload: Row) => {
    const method = a.method ?? 'post';
    const res = await apiClient[method](a.endpoint(row), { ...(a.body ? a.body(row) : {}), ...payload });
    const msg = (res.data as { message?: unknown } | undefined)?.message;
    setNotice(typeof msg === 'string' && msg ? msg : tr('saved'));
    await load();
  };

  return (
    <div className="space-y-4 text-start">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {descriptionKey ? <p className="text-sm text-muted-foreground">{t(descriptionKey)}</p> : <span />}
        <div className="flex shrink-0 flex-wrap gap-2">
          <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
            <RefreshCw className={`size-4 me-1 ${loading ? 'animate-spin' : ''}`} />
            {tr('refresh')}
          </Button>
          {createFields ? (
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              <Plus className="size-4 me-1" />
              {createLabel ? t(createLabel) : tr('create')}
            </Button>
          ) : null}
        </div>
      </div>

      {children}

      {error ? <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">{error}</p> : null}
      {notice ? (
        <p className="rounded-lg border border-green-500/40 bg-green-500/10 px-4 py-3 text-sm text-green-700 dark:text-green-400">
          {notice}
          <button type="button" className="ms-2 underline" onClick={() => setNotice(null)}>
            ×
          </button>
        </p>
      ) : null}

      <Card className="overflow-hidden">
        <CardContent className="p-0">
          <div className="w-full overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead className="bg-muted/50 text-xs text-muted-foreground">
                <tr>
                  {columns.map((c) => (
                    <th key={c.key} className="px-3 py-2.5 text-start font-medium whitespace-nowrap">
                      {tf(c.label)}
                    </th>
                  ))}
                  {rowActions?.length ? <th className="px-3 py-2.5 text-start font-medium">{tr('actions')}</th> : null}
                </tr>
              </thead>
              <tbody>
                {loading && rows.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length + 1} className="px-3 py-8 text-center text-muted-foreground">
                      {tr('loading')}
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={columns.length + 1} className="px-3 py-8 text-center text-muted-foreground">
                      {tr('empty')}
                    </td>
                  </tr>
                ) : (
                  rows.map((row, i) => (
                    <tr key={String(row.id ?? i)} className="border-t hover:bg-muted/30">
                      {columns.map((c) => (
                        <td key={c.key} className="px-3 py-2.5 align-middle">
                          {renderCell(row, c)}
                        </td>
                      ))}
                      {rowActions?.length ? (
                        <td className="px-3 py-2 whitespace-nowrap">
                          <div className="flex flex-wrap gap-1.5">
                            {rowActions
                              .filter((a) => !a.visible || a.visible(row))
                              .map((a) => (
                                <Button
                                  key={a.label}
                                  size="sm"
                                  variant="outline"
                                  onClick={() => {
                                    if (a.fields?.length) setAction({ action: a, row });
                                    else void runAction(a, row, {}).catch((e) => setError(getAxiosMessage(e) || tr('saveFailed')));
                                  }}
                                >
                                  {t(a.label)}
                                </Button>
                              ))}
                          </div>
                        </td>
                      ) : null}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      {lastPage > 1 ? (
        <div className="flex items-center justify-center gap-2 text-sm">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            {tr('prev')}
          </Button>
          <span className="tabular-nums text-muted-foreground">
            {page} / {lastPage}
          </span>
          <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            {tr('next')}
          </Button>
        </div>
      ) : null}

      {createFields ? (
        <ErpFormDialog
          open={createOpen}
          onOpenChange={setCreateOpen}
          title={createLabel ? t(createLabel) : tr('create')}
          fields={createFields}
          submit={async (payload) => {
            await apiClient.post(createEndpoint ?? endpoint, payload);
            setNotice(tr('saved'));
            await load();
          }}
        />
      ) : null}

      {action ? (
        <ErpFormDialog
          open
          onOpenChange={(o) => !o && setAction(null)}
          title={t(action.action.label)}
          fields={action.action.fields ?? []}
          submit={(payload) => runAction(action.action, action.row, payload)}
        />
      ) : null}
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Overview helpers                                                    */
/* ------------------------------------------------------------------ */

export type HubLink = { route: string; titleKey: string; descKey: string; icon?: ReactNode };

/** Module hub: links to each sub page plus optional live KPI cards. */
export function ErpModuleHub({ ns, links, stats, descriptionKey = 'description' }: { ns: string; links: HubLink[]; stats?: ReactNode; descriptionKey?: string }) {
  const t = useTranslations(ns);
  const locale = useLocale();
  return (
    <div className="space-y-5 text-start">
      <p className="text-sm text-muted-foreground">{t(descriptionKey)}</p>
      {stats}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {links.map((l) => (
          <Link
            key={l.route}
            href={dashboardHref(locale, l.route)}
            className="group flex items-start gap-3 rounded-xl border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:bg-primary/5"
          >
            {l.icon ? <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">{l.icon}</span> : null}
            <span className="min-w-0">
              <span className="block font-semibold group-hover:text-primary">{t(l.titleKey)}</span>
              <span className="mt-1 block text-xs text-muted-foreground">{t(l.descKey)}</span>
            </span>
          </Link>
        ))}
      </div>
    </div>
  );
}

export type StatItem = { label: string; value: ReactNode; icon?: ReactNode };

export function ErpStatGrid({ items }: { items: StatItem[] }) {
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {items.map((s) => (
        <Card key={s.label} className="shadow-sm">
          <CardContent className="flex items-center gap-3 p-4">
            {s.icon ? <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">{s.icon}</span> : null}
            <span className="min-w-0">
              <span className="block truncate text-xs text-muted-foreground">{s.label}</span>
              <span className="block text-lg font-bold tabular-nums">{s.value}</span>
            </span>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

/** GET an endpoint once and expose its unwrapped data. */
export function useErpData<T = Row>(endpoint: string | null, params?: Record<string, unknown>) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(Boolean(endpoint));
  const key = JSON.stringify(params ?? {});
  const reload = useCallback(async () => {
    if (!endpoint) return;
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(endpoint, { params });
      const body = res.data as { data?: unknown };
      setData((body && typeof body === 'object' && 'data' in body && !Array.isArray(body) && body.data !== undefined ? body.data : body) as T);
    } catch (e) {
      setError(getAxiosMessage(e) || 'error');
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint, key]);
  useEffect(() => {
    void reload();
  }, [reload]);
  return { data, error, loading, reload };
}
