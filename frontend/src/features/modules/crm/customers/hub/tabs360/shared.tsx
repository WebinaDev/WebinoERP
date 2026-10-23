'use client';

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { UploadCloud } from 'lucide-react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { useLocale } from '@/hooks/use-locale-next';
import type { PageMeta, Row } from '../hub-api';

/** Customer 360 phase 1 limits (PLAN §5.1). */
export const DOC360_MAX_BYTES = 20 * 1024 * 1024;
export const DOC360_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.zip';
export const DOC_CATEGORIES = ['national_card', 'gazette', 'enamad', 'permit', 'contract', 'proposal', 'invoice', 'brief', 'deliverable', 'other'] as const;

/** List meta returned by the phase-1 endpoints (extra keys beyond PageMeta). */
export type TabMeta = PageMeta & { can?: { create?: boolean }; kpis?: Row; status_counts?: Record<string, number> };

export const selectCls =
  'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

export type TField = {
  name: string;
  label: ReactNode;
  type?: 'text' | 'number' | 'textarea' | 'select' | 'date' | 'file';
  options?: [string, string][];
  required?: boolean;
  full?: boolean;
  hint?: ReactNode;
};

export type TValues = Record<string, string | File | null>;

/** Pull Laravel 422 field errors out of an axios error. */
export function fieldErrors(e: unknown): Record<string, string> {
  const errs = (e as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
  if (!errs || typeof errs !== 'object') return {};
  return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k.split('.')[0], Array.isArray(v) ? v[0] : String(v)]));
}

/** Drag & drop + click file picker with client-side size / type checks. */
export function FileDrop({ value, onChange, maxBytes = DOC360_MAX_BYTES, accept = DOC360_ACCEPT }: { value: File | null; onChange: (f: File | null) => void; maxBytes?: number; accept?: string }) {
  const t = useTranslations('crm.hub.t360');
  const ref = useRef<HTMLInputElement>(null);
  const [over, setOver] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const pick = (f: File | null | undefined) => {
    if (!f) return;
    const ext = `.${f.name.split('.').pop()?.toLowerCase() ?? ''}`;
    if (!accept.split(',').includes(ext)) return setErr(t('badType'));
    if (f.size > maxBytes) return setErr(t('tooLarge'));
    setErr(null);
    onChange(f);
  };
  return (
    <div>
      <div
        role="button"
        tabIndex={0}
        data-testid="file-drop"
        onClick={() => ref.current?.click()}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') ref.current?.click(); }}
        onDragOver={(e) => { e.preventDefault(); setOver(true); }}
        onDragLeave={() => setOver(false)}
        onDrop={(e) => { e.preventDefault(); setOver(false); pick(e.dataTransfer.files?.[0]); }}
        className={`flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed p-4 text-center text-xs ${over ? 'border-primary bg-primary/5' : 'border-input'}`}
      >
        <UploadCloud className="h-6 w-6 text-muted-foreground" />
        {value ? <span className="font-medium" dir="auto">{value.name}</span> : <span>{t('dropHint')}</span>}
        <span className="text-muted-foreground">{t('fileHint')}</span>
      </div>
      <input ref={ref} type="file" className="hidden" accept={accept} data-testid="file-input" onChange={(e) => pick(e.target.files?.[0])} />
      {err ? <p className="mt-1 text-xs text-destructive">{err}</p> : null}
    </div>
  );
}

/** Dialog form used by the phase-1 tabs: Jalali date pickers, file drop, server-side 422 errors per field. */
export function TabForm({ open, onOpenChange, title, fields, initial, onSubmit, submitLabel, children }: {
  open: boolean; onOpenChange: (v: boolean) => void; title: ReactNode; fields: TField[]; initial?: TValues;
  onSubmit: (v: TValues) => Promise<unknown>; submitLabel?: ReactNode; children?: (v: TValues, set: (k: string, val: string | File | null) => void) => ReactNode;
}) {
  const t = useTranslations('crm.hub');
  const [v, setV] = useState<TValues>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [msg, setMsg] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (open) { setV(initial ?? {}); setErrors({}); setMsg(null); }
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const set = (k: string, val: string | File | null) => setV((p) => ({ ...p, [k]: val }));
  const submit = async () => {
    const missing = fields.filter((f) => f.required && (v[f.name] === undefined || v[f.name] === null || v[f.name] === ''));
    if (missing.length) {
      setErrors(Object.fromEntries(missing.map((f) => [f.name, t('t360.required')])));
      return;
    }
    setBusy(true);
    try {
      await onSubmit(v);
      onOpenChange(false);
    } catch (e) {
      setErrors(fieldErrors(e));
      setMsg(getApiErrorMessage(e));
    } finally {
      setBusy(false);
    }
  };
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader><DialogTitle>{title}</DialogTitle></DialogHeader>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {fields.map((f) => (
            <div key={f.name} className={f.full || f.type === 'textarea' || f.type === 'file' ? 'sm:col-span-2' : ''}>
              <Label htmlFor={`f-${f.name}`} className="mb-1 block text-xs">{f.label}{f.required ? ' *' : ''}</Label>
              {f.type === 'textarea' ? (
                <Textarea id={`f-${f.name}`} value={String(v[f.name] ?? '')} onChange={(e) => set(f.name, e.target.value)} />
              ) : f.type === 'select' ? (
                <select id={`f-${f.name}`} className={selectCls} value={String(v[f.name] ?? '')} onChange={(e) => set(f.name, e.target.value)}>
                  {!f.required || !v[f.name] ? <option value="">—</option> : null}
                  {(f.options ?? []).map(([val, label]) => <option key={val} value={val}>{label}</option>)}
                </select>
              ) : f.type === 'date' ? (
                <LocaleDatePicker value={(v[f.name] as string) || null} onChange={(iso) => set(f.name, iso)} />
              ) : f.type === 'file' ? (
                <FileDrop value={(v[f.name] as File) ?? null} onChange={(file) => set(f.name, file)} />
              ) : (
                <Input id={`f-${f.name}`} type={f.type === 'number' ? 'number' : 'text'} dir={f.type === 'number' ? 'ltr' : 'auto'} value={String(v[f.name] ?? '')} onChange={(e) => set(f.name, e.target.value)} />
              )}
              {f.hint ? <p className="mt-1 text-xs text-muted-foreground">{f.hint}</p> : null}
              {errors[f.name] ? <p className="mt-1 text-xs text-destructive" data-testid={`err-${f.name}`}>{errors[f.name]}</p> : null}
            </div>
          ))}
        </div>
        {children ? children(v, set) : null}
        {msg ? <p className="text-sm text-destructive">{msg}</p> : null}
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>{t('cancel')}</Button>
          <Button onClick={() => void submit()} disabled={busy}>{submitLabel ?? t('save')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Side sheet for record details (opens from the inline-end side, i.e. left in RTL). */
export function DetailSheet({ open, onOpenChange, title, children }: { open: boolean; onOpenChange: (v: boolean) => void; title: ReactNode; children: ReactNode }) {
  const { locale } = useLocale();
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side={locale === 'fa' ? 'left' : 'right'} className="w-full overflow-y-auto sm:max-w-2xl">
        <SheetHeader><SheetTitle>{title}</SheetTitle></SheetHeader>
        <div className="mt-4 space-y-4 text-sm">{children}</div>
      </SheetContent>
    </Sheet>
  );
}

/** Build a body from form values: empty strings dropped, numeric keys converted, Files kept only for multipart. */
export function body(v: TValues, numeric: string[] = []): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  Object.entries(v).forEach(([k, val]) => {
    if (val === null || val === undefined || val === '' || val instanceof File) return;
    out[k] = numeric.includes(k) ? Number(val) : val;
  });
  return out;
}

export function toFormData(v: TValues, extra: Record<string, string> = {}): FormData {
  const fd = new FormData();
  Object.entries({ ...v, ...extra }).forEach(([k, val]) => {
    if (val === null || val === undefined || val === '') return;
    fd.append(k, val);
  });
  return fd;
}

export function FilterBar({ children }: { children: ReactNode }) {
  return <div className="mb-3 flex flex-wrap items-end gap-2">{children}</div>;
}

export function Kpi({ label, value, tone }: { label: ReactNode; value: ReactNode; tone?: 'danger' }) {
  return (
    <div className="rounded-lg border p-3">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className={`mt-1 text-lg font-semibold ${tone === 'danger' ? 'text-destructive' : ''}`}>{value}</div>
    </div>
  );
}

export function fmtSize(bytes: unknown, num: (v: unknown) => string): string {
  const n = Number(bytes);
  if (!n) return '—';
  if (n < 1024 * 1024) return `${num(Math.round(n / 1024))} KB`;
  return `${num(Math.round((n / 1024 / 1024) * 10) / 10)} MB`;
}
