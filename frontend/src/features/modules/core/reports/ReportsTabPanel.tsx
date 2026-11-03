'use client';

import { useTranslations } from 'next-intl';
import {
  Activity, BadgeDollarSign, Bell, CheckCircle2, Clock, FileText, FolderKanban, Globe, ListChecks,
  Megaphone, Percent, Receipt, Ticket, TrendingUp, Users, Wallet, type LucideIcon,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { LocaleBarChart, LocaleDonutChart } from '@/components/charts/LocaleCharts';
import { useLocale } from '@/hooks/use-locale-next';
import { formatChartAxis } from '@/lib/locale/calendar-date';
import { formatMoney } from '@/lib/money';
import { localizeToken } from '@/features/modules/dashboard/localize';
import { isMoneyKey, type ReportsPayload } from './types';

type Props = {
  tab: string;
  payload: ReportsPayload;
};

const DONUT_COLORS = [
  'var(--color-primary)',
  'oklch(0.65 0.15 160)',
  'oklch(0.7 0.14 50)',
  'oklch(0.62 0.18 25)',
  'oklch(0.58 0.14 280)',
  'oklch(0.68 0.12 220)',
  'oklch(0.72 0.1 100)',
  'oklch(0.55 0.08 30)',
];

function looksLikeIsoDate(value: unknown): boolean {
  if (typeof value !== 'string' || !value.trim()) return false;
  return /^\d{4}-\d{2}(-\d{2})?/.test(value.trim());
}

function isDateKey(key: string): boolean {
  const k = key.toLowerCase();
  return k.includes('date') || k.endsWith('_at') || k === 'month';
}

const STAT_ICONS: { match: RegExp; icon: LucideIcon; tone: string }[] = [
  { match: /rate|retention/, icon: Percent, tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400' },
  { match: /revenue|value|invoice_total|paid_invoice|receipts_total|amount/, icon: BadgeDollarSign, tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
  { match: /invoice|receipt/, icon: Receipt, tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
  { match: /contract/, icon: FileText, tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' },
  { match: /lead|customer|member/, icon: Users, tone: 'bg-primary/10 text-primary' },
  { match: /completed|closed|ready|read_/, icon: CheckCircle2, tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
  { match: /task|overdue|open_/, icon: ListChecks, tone: 'bg-primary/10 text-primary' },
  { match: /hour|minute|time|response/, icon: Clock, tone: 'bg-orange-500/10 text-orange-600 dark:text-orange-400' },
  { match: /ticket|sla/, icon: Ticket, tone: 'bg-rose-500/10 text-rose-600 dark:text-rose-400' },
  { match: /project|sprint/, icon: FolderKanban, tone: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' },
  { match: /site/, icon: Globe, tone: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400' },
  { match: /notification|unread/, icon: Bell, tone: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400' },
  { match: /page|post|form|announce|content/, icon: Megaphone, tone: 'bg-pink-500/10 text-pink-600 dark:text-pink-400' },
  { match: /wallet|balance/, icon: Wallet, tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
  { match: /trend|growth/, icon: TrendingUp, tone: 'bg-primary/10 text-primary' },
];

function statIcon(key: string) {
  return STAT_ICONS.find((s) => s.match.test(key)) ?? { icon: Activity, tone: 'bg-muted text-muted-foreground', match: /./ };
}

function moneyText(value: unknown, locale: string): string {
  return formatMoney(typeof value === 'number' ? value : Number(value), {
    locale,
    alreadyDisplay: true,
  });
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
    if (typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim()))) {
      const num = typeof value === 'number' ? value : Number(value);
      if (isMoneyKey(key)) return moneyText(num, locale);
      const text = formatNumber(num);
      return key.includes('rate') ? `${text}${percent}` : text;
    }
    return localizeToken(String(value), (token) => tDash(token as 'badges.open'), formatDigits);
  };

  const visible = keys.filter(({ key }) => key in stats);
  if (visible.length === 0) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{tCommon('emptyValue')}</p>;
  }

  return (
    <div className="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
      {visible.map(({ key, label }) => {
        const { icon: Icon, tone } = statIcon(key);
        return (
          <div key={key} className="flex min-w-0 items-center gap-3 rounded-xl border bg-card p-3 sm:p-4">
            <span className={`grid size-9 shrink-0 place-items-center rounded-lg sm:size-10 ${tone}`}>
              <Icon className="size-4 sm:size-5" />
            </span>
            <div className="min-w-0">
              <p className="truncate text-[11px] text-muted-foreground sm:text-xs">{label}</p>
              <p className="truncate text-base font-bold tabular-nums text-foreground sm:text-xl">
                {formatStat(key, stats[key])}
              </p>
            </div>
          </div>
        );
      })}
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
    if (typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(String(value).trim()))) {
      const num = typeof value === 'number' ? value : Number(value);
      if (isMoneyKey(key) || key === 'total') return moneyText(num, locale);
      return formatNumber(num);
    }
    if (key === 'reason') {
      return String(value)
        .split(',')
        .map((part) => localizeToken(part.trim(), (token) => tDash(token as 'badges.open'), formatDigits))
        .join(locale === 'fa' ? '، ' : ', ');
    }
    return localizeToken(String(value), (token) => tDash(token as 'badges.open'), formatDigits);
  };

  return (
    <Card className="min-w-0 overflow-hidden rounded-xl">
      <CardHeader className="py-3">
        <CardTitle className="text-sm font-bold sm:text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent className="max-w-full overflow-x-auto p-0 sm:px-4 sm:pb-4">
        <table className="w-full min-w-[20rem] text-xs sm:text-sm">
          <thead>
            <tr className="border-b bg-muted/40 text-muted-foreground">
              {columns.map((c) => (
                <th key={c.key} className="whitespace-nowrap px-3 py-2 text-start font-medium">
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
                    <td key={c.key} className="px-3 py-2 tabular-nums" dir={c.key === 'domain' ? 'ltr' : undefined}>
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

function ChartBars({ title, rows }: { title: string; rows: { label: string; value: number }[] }) {
  const { locale } = useLocale();
  const tCommon = useTranslations('common');
  const axis = locale === 'fa' ? 'fa' : 'en';
  if (!rows.length) {
    return (
      <Card>
        <CardHeader className="py-3">
          <CardTitle className="text-base">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="py-6 text-center text-sm text-muted-foreground">{tCommon('emptyValue')}</p>
        </CardContent>
      </Card>
    );
  }
  return (
    <Card>
      <CardHeader className="py-3">
        <CardTitle className="text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <LocaleBarChart
          data={rows.map((row) => ({
            label: formatChartAxis(row.label, axis),
            value: row.value,
          }))}
        />
      </CardContent>
    </Card>
  );
}

function ChartDonut({ title, rows }: { title: string; rows: { label: string; count: number }[] }) {
  const { formatDigits } = useLocale();
  const tDash = useTranslations('dashboard');
  const tCommon = useTranslations('common');
  if (!rows.length) {
    return (
      <Card>
        <CardHeader className="py-3">
          <CardTitle className="text-base">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="py-6 text-center text-sm text-muted-foreground">{tCommon('emptyValue')}</p>
        </CardContent>
      </Card>
    );
  }
  return (
    <Card>
      <CardHeader className="py-3">
        <CardTitle className="text-base">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <LocaleDonutChart
          segments={rows.map((row, i) => ({
            label: localizeToken(row.label, (token) => tDash(token as 'badges.open'), formatDigits),
            value: row.count,
            color: DONUT_COLORS[i % DONUT_COLORS.length],
          }))}
        />
      </CardContent>
    </Card>
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
    priority: tPages('col_priority'),
    number: tPages('col_number'),
    starts: tPages('col_starts'),
    ends: tPages('col_ends'),
  };

  if (tab === 'sales') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_contracts', label: t('metrics.contracts_total') },
            { key: 'total_revenue', label: t('statLabels.total_revenue') },
            { key: 'avg_deal_size', label: tPages('avg_deal') },
            { key: 'total_leads', label: t('statLabels.total_leads') },
            { key: 'conversion_rate', label: tPages('conversion_rate') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartBars
            title={tPages('chart_monthly')}
            rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? m.contracts ?? 0) }))}
          />
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
        </div>
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
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_tasks', label: t('statLabels.total_tasks') },
            { key: 'completed_tasks', label: t('metrics.tasks_completed') },
            { key: 'task_completion_rate', label: tPages('completion_rate') },
            { key: 'active_members', label: tPages('active_members') },
            { key: 'total_time_hours', label: tPages('total_hours') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartBars
            title={tPages('tasks_by_member')}
            rows={(charts?.member_activity ?? []).map((r) => ({ label: r.label, value: r.count }))}
          />
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
        </div>
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
            { key: 'unique_customers', label: tPages('unique_customers') },
            { key: 'total_leads', label: t('statLabels.total_leads') },
            { key: 'retention_rate', label: tPages('retention') },
            { key: 'avg_customer_value', label: tPages('avg_ltv') },
            { key: 'total_revenue', label: t('statLabels.total_revenue') },
          ]}
        />
        <ChartBars
          title={tPages('chart_monthly')}
          rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? m.contracts ?? 0) }))}
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

  if (tab === 'finance') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_revenue', label: t('statLabels.total_revenue') },
            { key: 'total_contracts', label: t('metrics.contracts_total') },
            { key: 'avg_contract_value', label: tPages('avg_deal') },
            { key: 'invoice_count', label: tDash('ops.invoices') },
            { key: 'invoice_total', label: tDash('ops.invoiceTotal') },
            { key: 'open_invoices', label: tDash('ops.openInvoices') },
            { key: 'paid_invoice_total', label: tPages('paid_total') },
            { key: 'receipts_count', label: tDash('ops.receipts') },
            { key: 'receipts_total', label: tPages('receipts_total') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartBars
            title={tPages('chart_monthly')}
            rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? m.contracts ?? 0) }))}
          />
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
        </div>
        <DataTable
          title={tPages('invoices_by_status')}
          rows={(tables.invoices_by_status as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'status', label: cols.status },
            { key: 'count', label: cols.count },
            { key: 'total', label: cols.total },
          ]}
        />
        <DataTable
          title={tPages('recent_invoices')}
          rows={(tables.recent_invoices as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'number', label: cols.number },
            { key: 'status', label: cols.status },
            { key: 'total', label: cols.total },
            { key: 'created_at', label: cols.when },
          ]}
        />
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
            { key: 'open_tasks', label: tPages('open_tasks') },
            { key: 'overdue_tasks', label: tPages('overdue_tasks') },
            { key: 'task_completion_rate', label: tPages('completion_rate') },
            { key: 'total_time_hours', label: tPages('total_hours') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
          <ChartBars
            title={tPages('chart_daily')}
            rows={(charts?.daily ?? []).slice(-14).map((d) => ({ label: String(d.date ?? d.day ?? ''), value: Number(d.total ?? 0) }))}
          />
        </div>
        <DataTable
          title={tPages('tasks_by_status')}
          rows={(tables.tasks_by_status as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'status', label: cols.status },
            { key: 'count', label: cols.count },
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
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'total_tickets', label: t('statLabels.total_tickets') },
            { key: 'tickets_closed', label: t('metrics.tickets_closed') },
            { key: 'open_tickets', label: tPages('open_tickets') },
            { key: 'sla_breaches', label: tPages('sla_breaches') },
            { key: 'avg_response_time', label: tPages('avg_response') },
            { key: 'close_rate', label: tPages('close_rate') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
          <ChartDonut
            title={tPages('by_priority')}
            rows={(charts?.priority_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
        </div>
        <ChartBars
          title={tPages('chart_daily')}
          rows={(charts?.daily ?? []).slice(-14).map((d) => ({ label: String(d.date ?? ''), value: Number(d.total ?? 0) }))}
        />
        <DataTable
          title={tPages('tickets_by_assignee')}
          rows={(tables.tickets_by_assignee as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'user_name', label: cols.member },
            { key: 'count', label: cols.count },
          ]}
        />
        <DataTable
          title={tPages('recent_tickets')}
          rows={(tables.recent_tickets as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'title', label: cols.title },
            { key: 'status', label: cols.status },
            { key: 'priority', label: cols.priority },
            { key: 'created_at', label: cols.when },
          ]}
        />
      </div>
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
            { key: 'sprints_active', label: tPages('sprints_active') },
            { key: 'completed_tasks', label: t('metrics.tasks_completed') },
          ]}
        />
        <div className="grid gap-4 lg:grid-cols-2">
          <ChartBars
            title={tPages('chart_monthly')}
            rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? 0) }))}
          />
          <ChartDonut
            title={tPages('by_status')}
            rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
          />
        </div>
        <DataTable
          title={tPages('projects_by_status')}
          rows={(tables.projects_by_status as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'status', label: cols.status },
            { key: 'count', label: cols.count },
          ]}
        />
        <DataTable
          title={tPages('recent_sprints')}
          rows={(tables.recent_sprints as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'title', label: cols.title },
            { key: 'status', label: cols.status },
            { key: 'starts_at', label: cols.starts },
            { key: 'ends_at', label: cols.ends },
          ]}
        />
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
        <ChartDonut
          title={tPages('by_status')}
          rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
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
        <DataTable
          title={tPages('recent_sites')}
          rows={(tables.recent_sites as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'domain', label: cols.domain },
            { key: 'status', label: cols.status },
            { key: 'created_at', label: cols.when },
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
        <ChartBars
          title={tPages('chart_submissions')}
          rows={(charts?.daily ?? []).map((row) => ({ label: String(row.date ?? ''), value: Number(row.total ?? 0) }))}
        />
        <DataTable
          title={tPages('submissions_by_form')}
          rows={(tables.submissions_by_form as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'title', label: cols.title },
            { key: 'count', label: cols.count },
          ]}
        />
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

  if (tab === 'notifications') {
    return (
      <div className="space-y-4">
        <StatGrid
          stats={stats}
          keys={[
            { key: 'notifications_total', label: tDash('ops.notifications') },
            { key: 'unread_notifications', label: tDash('ops.unread') },
            { key: 'read_notifications', label: tPages('read_count') },
            { key: 'read_rate', label: tPages('read_rate') },
          ]}
        />
        <ChartBars
          title={tPages('chart_daily')}
          rows={(charts?.daily ?? []).slice(-14).map((d) => ({ label: String(d.date ?? ''), value: Number(d.total ?? 0) }))}
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
      <div className="grid gap-4 lg:grid-cols-2">
        <ChartBars
          title={tPages('chart_daily')}
          rows={(charts?.daily ?? []).slice(-14).map((d) => ({ label: String(d.date ?? d.day ?? ''), value: Number(d.total ?? 0) }))}
        />
        <ChartBars
          title={tPages('chart_monthly')}
          rows={(charts?.monthly ?? []).map((m) => ({ label: String(m.month ?? ''), value: Number(m.total ?? 0) }))}
        />
      </div>
      {(charts?.status_distribution?.length ?? 0) > 0 ? (
        <ChartDonut
          title={tPages('by_status')}
          rows={(charts?.status_distribution ?? []).map((r) => ({ label: r.label, count: r.count }))}
        />
      ) : null}
      <DataTable
        title={tPages('leads_by_status')}
        rows={(tables.leads_by_status as Record<string, unknown>[]) ?? []}
        columns={[
          { key: 'status', label: cols.status },
          { key: 'count', label: cols.count },
        ]}
      />
      {(tables.problem_sites?.length ?? 0) > 0 ? (
        <DataTable
          title={tPages('problem_sites')}
          rows={(tables.problem_sites as Record<string, unknown>[]) ?? []}
          columns={[
            { key: 'domain', label: cols.domain },
            { key: 'status', label: cols.status },
            { key: 'reason', label: cols.reason },
          ]}
        />
      ) : null}
    </div>
  );
}
