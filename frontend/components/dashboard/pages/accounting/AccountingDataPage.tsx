'use client';

import { useMemo, type ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useAccountingGet } from './useAccountingGet';

type Props = {
  title: string;
  apiPath: string;
  description?: string;
};

export function AccountingDataPage({ title, apiPath, description }: Props) {
  const pathname = usePathname();
  const locale = pathname?.match(/^\/(fa|en)/)?.[1] ?? 'fa';
  const { data, error, loading, reload } = useAccountingGet(apiPath);

  const { cards, rows, raw } = useMemo(() => {
    if (data === null || data === undefined) {
      return { cards: null as Record<string, unknown> | null, rows: null as Record<string, unknown>[] | null, raw: data };
    }
    if (Array.isArray(data)) {
      return { cards: null, rows: data as Record<string, unknown>[], raw: data };
    }
    if (typeof data === 'object') {
      const o = data as Record<string, unknown>;
      const arr = normalizeListPayload(data);
      if (arr.length) {
        return { cards: null, rows: arr, raw: data };
      }
      const numericEntries = Object.entries(o).filter(([, v]) => typeof v === 'number' || typeof v === 'string');
      if (numericEntries.length && numericEntries.length <= 12) {
        return { cards: o, rows: null, raw: data };
      }
      return { cards: null, rows: null, raw: data };
    }
    return { cards: null, rows: null, raw: data };
  }, [data]);

  const columns = rows?.[0] ? Object.keys(rows[0]) : [];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold">{title}</h2>
          {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
          <p className="text-xs text-muted-foreground" dir="ltr">
            GET {apiPath}
          </p>
          <p className="text-xs text-muted-foreground">
            <a className="underline" href={`/dashboard/accounting`}>
              ← داشبورد حسابداری
            </a>
          </p>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={() => void reload()} disabled={loading}>
          بروزرسانی
        </Button>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">در حال بارگذاری…</p> : null}

      {cards && !loading ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {Object.entries(cards).map(([k, v]) => (
            <Card key={k}>
              <CardHeader className="py-3">
                <CardDescription className="text-xs">{k}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{String(v)}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>
      ) : null}

      {rows && rows.length > 0 ? (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full min-w-[600px] text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                {columns.map((c) => (
                  <th key={c} className="px-2 py-2 text-start font-medium">
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.slice(0, 200).map((row, i) => (
                <tr key={i} className="border-b border-border/60">
                  {columns.map((c) => (
                    <td key={c} className="max-w-[280px] truncate px-2 py-1.5">
                      {renderCell(row[c])}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {!loading && !error && !rows?.length && !cards && raw !== null && raw !== undefined ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">داده خام</CardTitle>
          </CardHeader>
          <CardContent>
            <pre className="max-h-[420px] overflow-auto rounded-md bg-muted p-3 text-xs" dir="ltr">
              {typeof raw === 'string' ? raw : JSON.stringify(raw, null, 2)}
            </pre>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}

function renderCell(v: unknown): ReactNode {
  if (v === null || v === undefined) {
    return '';
  }
  if (typeof v === 'boolean') {
    return <Badge variant="outline">{v ? 'true' : 'false'}</Badge>;
  }
  if (typeof v === 'object') {
    return JSON.stringify(v).slice(0, 160);
  }
  return String(v);
}
