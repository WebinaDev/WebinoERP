'use client';

import { useEffect, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getApiErrorMessage } from '@/lib/api-helpers';

export type Field = {
  name: string;
  label: ReactNode;
  type?: 'text' | 'number' | 'textarea' | 'select' | 'date' | 'checkbox' | 'password';
  options?: [string, string][];
  required?: boolean;
  placeholder?: string;
  full?: boolean;
};

export type FormValues = Record<string, string | boolean>;

type Props = {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  title: ReactNode;
  fields: Field[];
  initial?: FormValues;
  onSubmit: (values: FormValues) => Promise<unknown>;
  onDone?: (result: unknown) => void;
  danger?: boolean;
  submitLabel?: ReactNode;
};

const selectCls =
  'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

export function FormDialog({ open, onOpenChange, title, fields, initial, onSubmit, onDone, danger, submitLabel }: Props) {
  const t = useTranslations('crm.hub');
  const [values, setValues] = useState<FormValues>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      const base: FormValues = {};
      fields.forEach((f) => {
        base[f.name] = f.type === 'checkbox' ? false : f.type === 'select' && f.options?.length && f.required ? f.options[0][0] : '';
      });
      setValues({ ...base, ...(initial ?? {}) });
      setError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const set = (k: string, v: string | boolean) => setValues((p) => ({ ...p, [k]: v }));

  async function submit() {
    const missing = fields.find((f) => f.required && f.type !== 'checkbox' && !String(values[f.name] ?? '').trim());
    if (missing) {
      setError(`${typeof missing.label === 'string' ? missing.label : missing.name} *`);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await onSubmit(values);
      onOpenChange(false);
      onDone?.(res);
    } catch (e) {
      setError(getApiErrorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {fields.map((f) => (
            <div key={f.name} className={f.full || f.type === 'textarea' ? 'space-y-1 sm:col-span-2' : 'space-y-1'}>
              {f.type === 'checkbox' ? (
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={Boolean(values[f.name])} onChange={(e) => set(f.name, e.target.checked)} />
                  {f.label}
                </label>
              ) : (
                <>
                  <Label>
                    {f.label}
                    {f.required ? ' *' : ''}
                  </Label>
                  {f.type === 'textarea' ? (
                    <Textarea rows={4} value={String(values[f.name] ?? '')} placeholder={f.placeholder} onChange={(e) => set(f.name, e.target.value)} />
                  ) : f.type === 'select' ? (
                    <select className={selectCls} value={String(values[f.name] ?? '')} onChange={(e) => set(f.name, e.target.value)}>
                      {!f.required ? <option value="">—</option> : null}
                      {(f.options ?? []).map(([v, l]) => (
                        <option key={v} value={v}>{l}</option>
                      ))}
                    </select>
                  ) : (
                    <Input
                      type={f.type ?? 'text'}
                      dir={f.type === 'number' || f.type === 'date' ? 'ltr' : undefined}
                      value={String(values[f.name] ?? '')}
                      placeholder={f.placeholder}
                      onChange={(e) => set(f.name, e.target.value)}
                    />
                  )}
                </>
              )}
            </div>
          ))}
        </div>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={busy}>{t('cancel')}</Button>
          <Button variant={danger ? 'destructive' : 'default'} onClick={() => void submit()} disabled={busy}>
            {submitLabel ?? t('save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Strip empty strings; convert numeric fields. */
export function clean(values: FormValues, numeric: string[] = []): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  Object.entries(values).forEach(([k, v]) => {
    if (v === '') return;
    out[k] = numeric.includes(k) && typeof v === 'string' ? Number(v) : v;
  });
  return out;
}
