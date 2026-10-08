'use client';

import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { normalizeListPayload } from '@/lib/list-utils';

export type HrmRow = Record<string, unknown>;

/** Loads a list endpoint and normalizes paginated / plain payloads. */
export function useHrmRows(loader: () => Promise<unknown>, onError?: (err: unknown) => void) {
  const fallback = useCrmFeedback();
  const report = onError ?? fallback.applyAxiosError;
  const [rows, setRows] = useState<HrmRow[]>([]);
  const [loading, setLoading] = useState(true);
  const load = useCallback(async () => {
    setLoading(true);
    try {
      setRows(normalizeListPayload(await loader()));
    } catch (err) {
      report(err);
    } finally {
      setLoading(false);
    }
  }, [loader, report]);
  useEffect(() => {
    void load();
  }, [load]);
  return { rows, loading, load };
}

export function employeeLabel(row: unknown): string {
  if (!row || typeof row !== 'object') return '';
  const r = row as HrmRow;
  const name = `${String(r.first_name ?? '')} ${String(r.last_name ?? '')}`.trim();
  return name || String(r.employee_code ?? r.id ?? '');
}

export function HrmField({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) {
  return (
    <div className="space-y-1">
      <Label>{label}</Label>
      {children}
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

export async function copyText(value: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(value);
    return true;
  } catch {
    return false;
  }
}
