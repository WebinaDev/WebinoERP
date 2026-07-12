'use client';

import { ReactNode } from 'react';

type Column = {
  header: string;
  cell: (row: Record<string, unknown>) => ReactNode;
  className?: string;
};

type Props = {
  title: string;
  description?: string;
  columns: Column[];
  rows: Record<string, unknown>[];
  emptyText?: string;
  loading?: boolean;
  error?: string | null;
  footer?: ReactNode;
};

export function ResourceListCard({
  title,
  description,
  columns,
  rows,
  emptyText = 'داده‌ای یافت نشد.',
  loading,
  error,
  footer,
}: Props) {
  return (
    <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div className="border-b px-4 py-3">
        <h2 className="text-sm font-medium">{title}</h2>
        {description ? <p className="text-xs text-muted-foreground mt-1">{description}</p> : null}
      </div>
      <div className="p-4">
        {error ? (
          <p className="text-sm text-destructive">{error}</p>
        ) : loading ? (
          <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>
        ) : rows.length === 0 ? (
          <p className="text-sm text-muted-foreground">{emptyText}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-right">
                  {columns.map((c) => (
                    <th key={c.header} className={`pb-2 pr-2 font-medium text-muted-foreground ${c.className ?? ''}`}>
                      {c.header}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row, i) => (
                  <tr key={i} className="border-b border-border/60 last:border-0">
                    {columns.map((c) => (
                      <td key={c.header} className={`py-2 pr-2 align-top ${c.className ?? ''}`}>
                        {c.cell(row)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {footer ? <div className="mt-4">{footer}</div> : null}
      </div>
    </div>
  );
}
