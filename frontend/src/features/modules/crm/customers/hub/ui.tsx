'use client';

import type { ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLocale } from '@/hooks/use-locale-next';
import type { PageMeta, Row } from './hub-api';

export function HubCard({ title, actions, children }: { title: ReactNode; actions?: ReactNode; children: ReactNode }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0 pb-3">
        <CardTitle className="text-base">{title}</CardTitle>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </CardHeader>
      <CardContent className="text-sm">{children}</CardContent>
    </Card>
  );
}

export function KV({ items }: { items: [ReactNode, ReactNode][] }) {
  return (
    <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
      {items.map(([k, v], i) => (
        <div key={i} className="flex flex-col">
          <dt className="text-xs text-muted-foreground">{k}</dt>
          <dd className="break-words font-medium">{v === null || v === undefined || v === '' ? '—' : v}</dd>
        </div>
      ))}
    </dl>
  );
}

export function Empty({ text }: { text?: string }) {
  const t = useTranslations('crm.hub');
  return <p className="py-4 text-center text-sm text-muted-foreground">{text ?? t('noData')}</p>;
}

export function useFmt() {
  const { formatNumber, formatDate } = useLocale();
  return {
    money: (v: unknown) => (v === null || v === undefined ? '—' : `${formatNumber(Number(v) || 0)}`),
    num: (v: unknown) => (v === null || v === undefined ? '—' : formatNumber(Number(v) || 0)),
    date: (v: unknown) => {
      if (!v) return '—';
      try {
        return formatDate(String(v));
      } catch {
        return String(v);
      }
    },
    dateTime: (v: unknown) => {
      if (!v) return '—';
      try {
        return formatDate(String(v), { includeTime: true });
      } catch {
        return String(v);
      }
    },
  };
}

const TONE: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  paid: 'default', active: 'default', verified: 'default', approved: 'default', ready: 'default', good: 'default', done: 'default',
  unpaid: 'secondary', pending: 'secondary', open: 'secondary', expiring: 'secondary', warning: 'secondary', fair: 'secondary', draft: 'outline',
  overdue: 'destructive', expired: 'destructive', blocked: 'destructive', rejected: 'destructive', failed: 'destructive', critical: 'destructive', at_risk: 'destructive', debtor: 'destructive',
};

export function StatusBadge({ value, label }: { value: unknown; label?: string }) {
  if (value === null || value === undefined || value === '') return <span>—</span>;
  const v = String(value);
  return <Badge variant={TONE[v] ?? 'outline'}>{label ?? v}</Badge>;
}

export type Col = { key: string; label: ReactNode; render?: (r: Row) => ReactNode };

export function DataTable({ cols, rows, empty }: { cols: Col[]; rows: Row[]; empty?: string }) {
  if (!rows.length) return <Empty text={empty} />;
  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            {cols.map((c) => (
              <TableHead key={c.key} className="text-start whitespace-nowrap">{c.label}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((r, i) => (
            <TableRow key={String(r.id ?? i)}>
              {cols.map((c) => (
                <TableCell key={c.key} className="whitespace-nowrap">
                  {c.render ? c.render(r) : r[c.key] === null || r[c.key] === undefined ? '—' : String(r[c.key])}
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

export function Pager({ meta, onPage }: { meta: PageMeta | null; onPage: (p: number) => void }) {
  const t = useTranslations('crm.hub');
  if (!meta || meta.last_page <= 1) return null;
  return (
    <div className="mt-3 flex items-center justify-center gap-3 text-xs">
      <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>{t('prev')}</Button>
      <span>{t('page', { page: meta.current_page, last: meta.last_page })}</span>
      <Button size="sm" variant="outline" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>{t('next')}</Button>
    </div>
  );
}

export function Forbidden() {
  const t = useTranslations('crm.hub');
  return <Empty text={t('forbidden')} />;
}

export function Loading() {
  const t = useTranslations('crm.hub');
  return <p className="py-4 text-sm text-muted-foreground">{t('loading')}</p>;
}
