'use client';

import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { LocaleBarChart } from '@/components/charts/LocaleCharts';
import { useLocale } from '@/hooks/use-locale-next';
import { formatChartAxis } from '@/lib/locale/calendar-date';
import { localizeToken } from '@/features/modules/dashboard/localize';
import type { ReportsPayload } from './types';

type Props = {
  tab: string;
  payload: ReportsPayload;
};

function looksLikeIsoDate(value: unknown): boolean {
  if (typeof value !== 'string' || !value.trim()) return false;
  return /^\d{4}-\d{2}(-\d{2})?/.test(value.trim());
}

function isDateKey(key: string): boolean {
  const k = key.toLowerCase();
  return k.includes('date') || k.endsWith('_at') || k === 'month';
}

function StatGrid({ stats, keys }: { stats: Record<string, unknown>; keys: { key: string; label: string }[] }) {
  const { formatNumber, formatDate, formatDigits, locale } = useLocale();
  const tDash = useTranslations('dashboard');
  const tCommon = useTranslations('common');
  const percent = locale === 'fa' ? '٪' : '%';

  const formatStat = (key: string, value: unknown): string => {
    if (value == null || value === '') return tCommon('emptyValue');
    if (isDateKey(key) && looksLikeIsoDate(value)) {
      return formatDate(String(value)) || formatDigits(String(value));
    }
    if (typeof value === 'number') {
      const text = formatNumber(value);
      return key.includes('rate') ? `${text}${percent}` : text;
    }
    if (typeof value === 'string' && value.trim() !== '' && !Number.isNaN(Number(value)) && /^-?\d+(\.\d+)?$/.test(value.trim())) {
      return formatNumber(Number(value));
    }
    return localizeToken(String(value), (token) => tDash(token as 'badges.open'), formatDigits);
  };

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {keys.map(({ key, label }) =>
        key in stats ? (
          <Card key={key}>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {formatStat(key, stats[key])}
            </CardContent>
          </Card>
        ) : null,
      )}
    </div>
  );
}

function DataTable({
  title,
  rows,
  columns,
}: {
  title: string;
  rows: Record<string, unknown>[];
  columns: { key: string; label: string }[];
}) {
  const { formatNumber, formatDate, formatDateTime, formatDigits, locale } = useLocale();
  const tDash = useTranslations('dashboard');
  const tCommon = useTranslations('common');

  const formatCell = (key: string, value: unknown): string => {
    if (value == null || value === '') return tCommon('emptyValue');
    if (key === 'is_read') return value === true || value === 1 || value === '1' ? tDash('read') : tDash('unread');
    if (key === 'month' && typeof value === 'string') return formatChartAxis(value, locale === 'fa' ? 'fa' : 'en');
    if (
      (key.toLowerCase().includes('date') || key.toLowerCase().endsWith('_at') || key === 'month') &&
      looksLikeIsoDate(value)
    ) {
      return key.endsWith('_at') ? formatDateTime(String(value)) || formatDigits(String(value)) : formatDate(String(value)) || formatDigits(String(value));
    }
    if (typeof value === 'number') return formatNumber(value);
    if (key === 'reason') {
      return String(value)
        .split(',')
        .map((part) => localizeToken(part.trim(), (token) => tDash(token as 'badges.open'), formatDigits))
        .join(locale === 'fa' ? '، ' : ', ');
    }
    return localizeToken(String(value), (token) => tDash(token as 'badges.open'), formatDigits);
  };

  return (
    <Card>
      <CardHeader className="py-3">
        <CardTitle className="text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b text-muted-foreground">
              {columns.map((c) => (
                <th key={c.key} className="px-2 py-2 text-start">
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-2 py-4 text-center text-muted-foreground">
                  {tCommon('emptyValue')}
                </td>
              </tr>
            ) : (
              rows.map((row, i) => (
                <tr key={i} className="border-b border-border/60">
                  {columns.map((c) => (
                    <td key={c.key} className="px-2 py-2 tabular-nums" dir={c.key === 'domain' ? 'ltr' : undefined}>
                      {formatCell(c.key, row[c.key])}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </CardContent>
    </Card>
  );
}

function ChartBars({ rows }: { rows: { label: string; value: number }[] }) {
  const { locale } = useLocale();
  const axis = locale === 'fa' ? 'fa' : 'en';
  return (
    <LocaleBarChart
      data={rows.map((row) => ({
        label: formatChartAxis(row.label, axis),
        value: row.value,
      }))}
    />
  );
}

export function ReportsTabPanel({ tab, payload }: Props) {
  const t = useTranslations('reports');
  const tPages = useTranslations('pages.reports');
  const tDash = useTranslations('dashboard');
  const stats = payload.stats ?? {};
  const charts = payload.charts;
  const tables = payload.tables ?? {};

  const cols = {
    domain: t('columns.domain'),
    reason: t('columns.reason'),
    title: t('columns.title'),
    read: t('columns.read'),
    when: t('columns.when'),
    month: t('columns.month'),
    count: t('columns.count'),
    total: t('columns.total'),
    customer: t('columns.customer'),
    contract: t('columns.contract'),
    value: t('columns.value'),
    member: t('columns.member'),
    all: t('columns.all'),
    completed: t('columns.completed'),
    score: t('columns.score'),
    minutes: t('columns.minutes'),
    billable: t('columns.billable'),
    revenue: t('columns.revenue'),
    entry_count: t('columns.entry_count'),
    status: t('columns.status'),
  };

  if (tab === 'sales') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_contracts', label: t('metrics.contracts_total') },
            { key: 'total_revenue', label: t('statLabels.total_revenue') },
            { key: 'total_leads', label: t('statLabels.total_leads') },
          ]}
        />
        <DataTable
          title={tPages('sales_by_month')}
          rows={(tables.sales_by_month as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'month', label: cols.month },
            { key: 'count', label: cols.count },
            { key: 'total', label: cols.total },
          ]}
        />
        <DataTable
          title={tPages('top_customers')}
          rows={(tables.top_customers as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'customer_name', label: cols.customer },
            { key: 'contract_count', label: cols.contract },
            { key: 'total_value', label: cols.value },
          ]}
        />
      </div>
    );
  }

  if (tab === 'team') {
    return (
      <div className="space-y-4">
        <DataTable
          title={tPages('tasks_by_member')}
          rows={(tables.tasks_by_member as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'user_name', label: cols.member },
            { key: 'total_tasks', label: cols.all },
            { key: 'completed_tasks', label: cols.completed },
            { key: 'activity_score', label: cols.score },
          ]}
        />
        <DataTable
          title={tPages('time_by_member')}
          rows={(tables.time_by_member as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'user_name', label: cols.member },
            { key: 'total_minutes', label: cols.minutes },
            { key: 'billable_minutes', label: cols.billable },
            { key: 'total_revenue', label: cols.revenue },
          ]}
        />
      </div>
    );
  }

  if (tab === 'customers') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'new_customers', label: tPages('new_customers') },
            { key: 'retention_rate', label: tPages('retention') },
            { key: 'avg_customer_value', label: tPages('avg_ltv') },
          ]}
        />
        <DataTable
          title={t('ltvTable')}
          rows={(tables.customer_ltv as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'customer_name', label: cols.customer },
            { key: 'total_contracts', label: cols.contract },
            { key: 'lifetime_value', label: cols.value },
          ]}
        />
      </div>
    );
  }

  if (tab === 'finance') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_revenue', label: t('statLabels.total_revenue') },
            { key: 'total_contracts', label: t('metrics.contracts_total') },
            { key: 'invoice_count', label: tDash('ops.invoices') },
            { key: 'invoice_total', label: tDash('ops.invoiceTotal') },
            { key: 'open_invoices', label: tDash('ops.openInvoices') },
            { key: 'receipts_count', label: tDash('ops.receipts') },
          ]}
        />
        {(charts?.monthly?.length ?? 0) > 0 ? (
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-base">{tPages('chart_monthly')}</CardTitle>
            </CardHeader>
            <CardContent>
              <ChartBars rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? m.contracts ?? 0) }))} />
            </CardContent>
          </Card>
        ) : null}
      </div>
    );
  }

  if (tab === 'tasks') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_tasks', label: t('statLabels.total_tasks') },
            { key: 'completed_tasks', label: t('metrics.tasks_completed') },
            { key: 'task_completion_rate', label: tPages('completion_rate') },
            { key: 'total_time_hours', label: tPages('total_hours') },
          ]}
        />
        <DataTable
          title={tPages('time_by_member')}
          rows={(tables.time_by_member as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'user_name', label: cols.member },
            { key: 'total_minutes', label: cols.minutes },
            { key: 'entry_count', label: cols.entry_count },
          ]}
        />
      </div>
    );
  }

  if (tab === 'tickets') {
    return (
      <StatGrid
        stats={stats}
        keys={[
          { key: 'total_tickets', label: t('statLabels.total_tickets') },
          { key: 'tickets_closed', label: t('metrics.tickets_closed') },
          { key: 'avg_response_time', label: tPages('avg_response') },
        ]}
      />
    );
  }

  if (tab === 'agile') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_projects', label: t('statLabels.total_projects') },
            { key: 'active_projects', label: tPages('active_projects') },
            { key: 'task_completion_rate', label: tPages('completion_rate') },
            { key: 'sprints_started', label: t('metrics.sprints_started') },
          ]}
        />
        {(charts?.monthly?.length ?? 0) > 0 ? (
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-base">{tPages('chart_monthly')}</CardTitle>
            </CardHeader>
            <CardContent>
              <ChartBars rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? 0) }))} />
            </CardContent>
          </Card>
        ) : null}
      </div>
    );
  }

  if (tab === 'sites') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'sites_total', label: tDash('ops.sitesTotal') },
            { key: 'sites_ready', label: tDash('ops.sitesReady') },
            { key: 'sites_failed', label: tDash('ops.sitesFailed') },
            { key: 'sites_problems', label: tDash('ops.sitesProblems') },
            { key: 'sites_created', label: t('statLabels.sites_created') },
          ]}
        />
        <DataTable
          title={tPages('problem_sites')}
          rows={(tables.problem_sites as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'domain', label: cols.domain },
            { key: 'status', label: cols.status },
            { key: 'reason', label: cols.reason },
          ]}
        />
      </div>
    );
  }

  if (tab === 'marketing') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'published_pages', label: tDash('ops.publishedPages') },
            { key: 'form_submissions', label: tDash('ops.formSubmissions') },
            { key: 'published_posts', label: tDash('ops.publishedPosts') },
            { key: 'announcements', label: tDash('ops.announcements') },
            { key: 'scheduled_content', label: tDash('ops.scheduledContent') },
            { key: 'leads_in_range', label: t('statLabels.total_leads') },
          ]}
        />
        {(charts?.daily?.length ?? 0) > 0 ? (
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-base">{tPages('chart_submissions')}</CardTitle>
            </CardHeader>
            <CardContent>
              <ChartBars rows={(charts?.daily ?? []).map((row) => ({ label: String(row.date ?? ''), value: Number(row.total ?? 0) }))} />
            </CardContent>
          </Card>
        ) : null}
      </div>
    );
  }

  if (tab === 'notifications') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'notifications_total', label: tDash('ops.notifications') },
            { key: 'unread_notifications', label: tDash('ops.unread') },
          ]}
        />
        <DataTable
          title={tPages('recent_notifications')}
          rows={(tables.recent_notifications as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'title', label: cols.title },
            { key: 'created_at', label: cols.when },
            { key: 'is_read', label: cols.read },
          ]}
        />
      </div>
    );
  }

  // overview
  return (
    <div className="space-y-6">
      <StatGrid
        stats={stats}
        keys={[
          { key: 'total_projects', label: t('statLabels.total_projects') },
          { key: 'total_tasks', label: t('statLabels.total_tasks') },
          { key: 'total_leads', label: t('statLabels.total_leads') },
          { key: 'conversion_rate', label: tPages('conversion_rate') },
          { key: 'total_revenue', label: t('statLabels.total_revenue') },
          { key: 'total_tickets', label: t('statLabels.total_tickets') },
          { key: 'sites_problems', label: tDash('ops.sitesProblems') },
          { key: 'form_submissions', label: tDash('ops.formSubmissions') },
          { key: 'unread_notifications', label: tDash('ops.unread') },
          { key: 'invoice_total', label: tDash('ops.invoiceTotal') },
        ]}
      />
      {(charts?.daily?.length ?? 0) > 0 ? (
        <Card>
          <CardHeader className="py-3">
            <CardTitle className="text-base">{tPages('chart_daily')}</CardTitle>
          </CardHeader>
          <CardContent>
            <ChartBars rows={(charts?.daily ?? []).slice(-14).map((d) => ({ label: String(d.date ?? d.day ?? ''), value: Number(d.total ?? 0) }))} />
          </CardContent>
        </Card>
      ) : null}
      <DataTable
        title={tPages('leads_by_status')}
        rows={(tables.leads_by_status as Record<string, unknown>[]) ?? []}
        columns={[
          { key: 'status', label: cols.status },
          { key: 'count', label: cols.count },
        ]}
      />
    </div>
  );
}
