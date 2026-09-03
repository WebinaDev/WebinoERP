'use client';

export const REPORT_TAB_IDS = [
  'overview',
  'sales',
  'team',
  'customers',
  'finance',
  'tasks',
  'tickets',
  'agile',
] as const;

export type ReportTabId = (typeof REPORT_TAB_IDS)[number];

export type ReportsPayload = {
  tab?: string;
  date_from?: string;
  date_to?: string;
  range?: { from?: string; to?: string };
  stats?: Record<string, unknown>;
  charts?: {
    daily?: { date?: string; day?: string; total?: number; contracts?: number; tasks?: number; tickets?: number; projects?: number }[];
    monthly?: { month?: string; total?: number; contracts?: number; tasks?: number; tickets?: number; projects?: number }[];
    status_distribution?: { label: string; count: number }[];
  };
  tables?: Record<string, Record<string, unknown>[]>;
  contracts_total?: number;
  tasks_completed?: number;
  leads_new?: number;
  tickets_closed?: number;
  sprints_started?: number;
  series?: Record<string, { day: string; count: number }[]>;
};

export function downloadBlob(blob: Blob, filename: string) {
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  window.URL.revokeObjectURL(url);
}

export function clientCsvFromPayload(tab: string, payload: ReportsPayload): string {
  const lines: string[] = ['metric,value'];
  const stats = payload.stats ?? {};
  for (const [k, v] of Object.entries(stats)) {
    if (v != null && typeof v !== 'object') {
      lines.push(`"${k}","${String(v).replace(/"/g, '""')}"`);
    }
  }
  const tables = payload.tables ?? {};
  for (const [name, rows] of Object.entries(tables)) {
    if (!rows?.length) continue;
    lines.push('');
    lines.push(`table,${name}`);
    const headers = Object.keys(rows[0] ?? {});
    lines.push(headers.join(','));
    for (const row of rows) {
      lines.push(headers.map((h) => `"${String(row[h] ?? '').replace(/"/g, '""')}"`).join(','));
    }
  }
  if (lines.length === 1) {
    lines.push(`contracts_total,${payload.contracts_total ?? 0}`);
    lines.push(`tasks_completed,${payload.tasks_completed ?? 0}`);
    lines.push(`leads_new,${payload.leads_new ?? 0}`);
    lines.push(`tickets_closed,${payload.tickets_closed ?? 0}`);
    lines.push(`sprints_started,${payload.sprints_started ?? 0}`);
  }
  lines.push(`tab,${tab}`);
  return lines.join('\n');
}
