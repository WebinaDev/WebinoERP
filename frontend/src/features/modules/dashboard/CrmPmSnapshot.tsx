'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale';
import {
  AlarmClock,
  Bell,
  BellDot,
  CalendarClock,
  CalendarDays,
  CircleAlert,
  FileText,
  FolderKanban,
  Globe,
  Handshake,
  LayoutDashboard,
  ListTodo,
  Megaphone,
  MessagesSquare,
  ShieldAlert,
  Target,
  Ticket,
  TrendingUp,
  UserPlus,
  UsersRound,
  Wallet,
  X,
  type LucideIcon,
} from 'lucide-react';
import {
  Columns,
  Donut,
  HBars,
  HomeCard,
  KpiTile,
  Ring,
  Segments,
  Spark,
  TrendArea,
  WD_CHART,
  compactNumber,
} from '@/components/charts/HomeCharts';
import { formatChartAxis } from '@/lib/locale/calendar-date';

type TodoRow = {
  id: number;
  title?: string;
  status?: string;
  due_at?: string | null;
  project_id?: number | null;
  kind?: string;
  href?: string;
  bucket?: string;
  domain?: string;
  reasons?: string[];
  error?: string;
};

type WorkItem = TodoRow & { kind: string; href: string; bucket: string };

type Ops = {
  finance?: Record<string, number>;
  tickets?: Record<string, number>;
  sites?: Record<string, number>;
  marketing?: Record<string, number>;
  notifications?: { unread?: number; recent?: { title?: string; created_at?: string; is_read?: boolean }[] };
  trend?: { date: string; total: number }[];
};

type Snapshot = {
  crm?: Record<string, number>;
  pm?: Record<string, number>;
  suite?: Record<string, number>;
  ops?: Ops;
  todos?: {
    counts?: { overdue?: number; today?: number; mine?: number; problems?: number };
    overdue?: TodoRow[];
    today?: TodoRow[];
    mine?: TodoRow[];
    work?: WorkItem[];
    sites?: TodoRow[];
  };
};

export function CrmPmSnapshot() {
  const t = useTranslations('dashboard');
  const { formatNumber, formatDate, formatDigits, isRtl, locale } = useLocale();
  const [data, setData] = useState<Snapshot | null>(null);
  const [hidden, setHidden] = useState<Set<string>>(new Set());
  const suiteT = useTranslations('suite');

  useEffect(() => {
    let cancelled = false;
    apiClient
      .get('/v1/core/dashboard/crm-pm')
      .then((res) => {
        if (!cancelled) setData(unwrapData<Snapshot>(res));
      })
    apiClient
      .get('/v1/core/dashboard/widgets')
      .then((res) => {
        const prefs = unwrapData<{ widgets?: { key: string; visible: boolean }[] }>(res);
        if (!cancelled) {
          setHidden(new Set((prefs?.widgets ?? []).filter((item) => !item.visible).map((item) => item.key)));
        }
      })
      .catch(() => {
        if (!cancelled) setData(null);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (!data) return null;

  const suite = data.suite ?? {};
  const widgetCards = [
    { key: 'forecast', label: suiteT('weighted'), value: suite.weighted_forecast ?? 0, href: 'crm/forecast' },
    { key: 'reminders', label: suiteT('reminder'), value: suite.open_reminders ?? 0, href: 'crm/customers' },
    { key: 'delays', label: suiteT('delays'), value: suite.delayed_tasks ?? 0, href: 'pm/workload' },
    { key: 'sla', label: suiteT('sla'), value: suite.sla_breaches ?? 0, href: 'crm/tickets' },
  ].filter((card) => !hidden.has(card.key));

  async function toggle(key: string, visible: boolean) {
    const next = new Set(hidden);
    if (visible) next.delete(key);
    else next.add(key);
    setHidden(next);
    await apiClient.put('/v1/core/dashboard/widgets', {
      widgets: ['forecast', 'reminders', 'delays', 'sla'].map((item, index) => ({
        key: item,
        visible: item === key ? visible : !next.has(item),
        sort_order: index,
      })),
    }).catch(() => undefined);
  }

  const crm = data.crm ?? {};
  const pm = data.pm ?? {};
  const ops = data.ops ?? {};
  const todos = data.todos ?? {};
  const work = todos.work ?? [];
  const useWork = work.length > 0;
  const bucketRows = (id: string) =>
    useWork
      ? work.filter((row) => row.bucket === id)
      : id === 'overdue'
        ? (todos.overdue ?? []).map((row) => ({ ...row, kind: 'task', href: 'pm/tasks', bucket: id }))
        : id === 'today'
          ? (todos.today ?? []).map((row) => ({ ...row, kind: 'task', href: 'pm/tasks', bucket: id }))
          : (todos.mine ?? []).map((row) => ({ ...row, kind: 'task', href: 'pm/tasks', bucket: id }));
  const buckets = [
    { id: 'overdue', title: t('todosOverdue'), rows: bucketRows('overdue'), count: useWork ? bucketRows('overdue').length : todos.counts?.overdue ?? 0 },
    { id: 'today', title: t('todosToday'), rows: bucketRows('today'), count: useWork ? bucketRows('today').length : todos.counts?.today ?? 0 },
    { id: 'upcoming', title: t('todosUpcoming'), rows: bucketRows('upcoming'), count: bucketRows('upcoming').length },
  ];
  const sites = todos.sites ?? [];
  const finance = ops.finance ?? {};
  const ticketOps = ops.tickets ?? {};
  const siteOps = ops.sites ?? {};
  const marketing = ops.marketing ?? {};
  const notes = ops.notifications ?? {};
  const axisLocale = locale === 'fa' ? 'fa' : 'en';

  const h = (key: string) => t(`home.${key}` as 'home.overview');
  const href = (p: string) => dashboardHref(locale, p);
  const fmt = (n: number) => formatNumber(n);
  const cmp = (n: number) => compactNumber(n, locale);
  const trend = (ops.trend ?? []).map((point) => ({
    label: formatChartAxis(point.date, axisLocale),
    value: Number(point.total ?? 0),
  }));
  const trendVals = trend.map((p) => p.value);
  const trendSum = trendVals.reduce((a, b) => a + b, 0);
  const n = (v: unknown) => Number(v ?? 0);

  const kpis: { key: string; icon: LucideIcon; label: string; value: number; href: string; tone: keyof typeof WD_CHART; viz?: 'spark' | 'ring' }[] = [
    { key: 'customers', icon: UsersRound, label: t('crmCustomers'), value: n(crm.customers), href: 'crm/customers', tone: 'blue', viz: 'spark' },
    { key: 'leads', icon: UserPlus, label: t('crmLeads'), value: n(crm.leads), href: 'crm/leads', tone: 'violet' },
    { key: 'deals', icon: Handshake, label: t('crmDeals'), value: n(crm.deals), href: 'crm/deals', tone: 'teal', viz: 'ring' },
    { key: 'consultations', icon: MessagesSquare, label: t('crmConsultations'), value: n(crm.consultations), href: 'crm/consultations', tone: 'amber' },
    { key: 'projects', icon: FolderKanban, label: t('pmProjects'), value: n(pm.projects), href: 'pm/projects', tone: 'blue' },
    { key: 'tasks', icon: ListTodo, label: t('pmOpenTasks'), value: n(pm.open_tasks), href: 'pm/tasks', tone: 'amber', viz: 'spark' },
    { key: 'tickets', icon: Ticket, label: t('pmTickets'), value: n(pm.tickets), href: 'crm/tickets', tone: 'rose' },
    { key: 'contracts', icon: FileText, label: t('pmContracts'), value: n(pm.contracts), href: 'docs/contracts', tone: 'ink' },
  ];

  const invoices = n(finance.invoice_count);
  const openInv = Math.min(invoices, n(finance.open_invoices));
  const ticketsOpen = n(ticketOps.open ?? pm.tickets);
  const ticketRings = [
    { label: t('ops.openTickets'), value: ticketsOpen, color: WD_CHART.blue },
    { label: t('ops.myTickets'), value: n(ticketOps.mine), color: WD_CHART.teal },
    { label: h('sla'), value: n(ticketOps.sla_breaches), color: WD_CHART.rose },
  ];
  const ticketMax = Math.max(1, ...ticketRings.map((r) => r.value));
  const sitesTotal = n(siteOps.sites_total);
  const sitesFailed = n(siteOps.sites_failed);
  const sitesProblems = n(siteOps.sites_problems ?? sites.length);
  const sitesReady = n(siteOps.sites_ready);
  const suiteIcons: Record<string, LucideIcon> = { forecast: Target, reminders: AlarmClock, delays: CalendarClock, sla: ShieldAlert };
  const bucketIcons: Record<string, LucideIcon> = { overdue: CircleAlert, today: CalendarDays, upcoming: CalendarClock };
  const bucketTone: Record<string, string> = { overdue: WD_CHART.rose, today: WD_CHART.blue, upcoming: WD_CHART.teal };
  const topWork = buckets.flatMap((b) => b.rows.slice(0, 2).map((row) => ({ ...row, bucket: b.id }))).slice(0, 5);

  return (
    <div className="wd-home space-y-4" dir={isRtl ? 'rtl' : 'ltr'}>
      <h2 className="wd-home-h2">
        <LayoutDashboard className="size-4 text-primary" strokeWidth={1.75} />
        {h('overview')}
      </h2>

      <div className="wd-kpi-grid">
        {kpis.map((k) => (
          <KpiTile
            key={k.key}
            icon={k.icon}
            label={k.label}
            value={cmp(k.value)}
            tone={k.tone}
            href={href(k.href)}
            viz={
              k.viz === 'spark' && trendVals.length > 1 ? (
                <Spark data={trendVals} color={WD_CHART[k.tone]} />
              ) : k.viz === 'ring' ? (
                <Ring value={n(crm.deals)} max={Math.max(1, n(crm.leads) + n(crm.deals))} size={40} color={WD_CHART.teal} label=" " />
              ) : undefined
            }
          />
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <HomeCard
          icon={TrendingUp}
          title={t('trendTitle')}
          className="lg:col-span-2"
          isRtl={isRtl}
          aside={<span className="wd-hc-chip">{cmp(trendSum)}</span>}
        >
          {trend.length > 0 ? (
            <TrendArea data={trend} isRtl={isRtl} format={fmt} height={190} />
          ) : (
            <p className="py-10 text-center text-sm text-muted-foreground">{t('empty')}</p>
          )}
        </HomeCard>
        <HomeCard icon={Target} title={h('funnel')} tone="violet" href={href('crm/deals')} isRtl={isRtl}>
          <HBars
            format={fmt}
            data={[
              { label: t('crmLeads'), value: n(crm.leads), color: WD_CHART.violet },
              { label: t('crmConsultations'), value: n(crm.consultations), color: WD_CHART.sky },
              { label: t('crmDeals'), value: n(crm.deals), color: WD_CHART.blue },
              { label: t('crmCustomers'), value: n(crm.customers), color: WD_CHART.teal },
            ]}
          />
        </HomeCard>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <HomeCard icon={Wallet} title={t('ops.finance')} tone="teal" href={href('finance/invoices')} isRtl={isRtl}>
          <Donut
            format={fmt}
            center={cmp(n(finance.invoice_total))}
            data={[
              { label: h('settled'), value: invoices - openInv, color: WD_CHART.teal },
              { label: t('ops.openInvoices'), value: openInv, color: WD_CHART.amber },
              { label: t('ops.receipts'), value: n(finance.receipts_count), color: WD_CHART.blue },
            ]}
          />
        </HomeCard>
        <HomeCard icon={Ticket} title={t('ops.tickets')} tone="rose" href={href('crm/tickets')} isRtl={isRtl}>
          <div className="grid grid-cols-3 gap-2">
            {ticketRings.map((r) => (
              <div key={r.label} className="flex flex-col items-center gap-1.5 text-center">
                <Ring value={r.value} max={ticketMax} color={r.color} size={72} label={cmp(r.value)} />
                <span className="text-[11px] leading-tight text-muted-foreground">{r.label}</span>
              </div>
            ))}
          </div>
        </HomeCard>
        <HomeCard icon={Globe} title={t('ops.sites')} tone="blue" href={href('admin/platform/sites')} isRtl={isRtl}
          aside={<span className="wd-hc-chip">{cmp(sitesTotal)}</span>}>
          <Segments
            format={fmt}
            data={[
              { label: t('ops.sitesReady'), value: sitesReady, color: WD_CHART.teal },
              { label: t('ops.sitesProblems'), value: sitesProblems, color: WD_CHART.amber },
              { label: t('ops.sitesFailed'), value: sitesFailed, color: WD_CHART.rose },
            ]}
          />
        </HomeCard>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <HomeCard icon={Megaphone} title={t('ops.marketing')} tone="amber" href={href('marketing/pages')} className="lg:col-span-2" isRtl={isRtl}>
          <Columns
            isRtl={isRtl}
            format={fmt}
            data={[
              { label: h('pages'), value: n(marketing.published_pages) },
              { label: h('forms'), value: n(marketing.form_submissions) },
              { label: h('posts'), value: n(marketing.published_posts) },
              { label: h('scheduled'), value: n(marketing.scheduled_content) },
              { label: h('notices'), value: n(marketing.announcements) },
            ]}
          />
        </HomeCard>
        {widgetCards.length > 0 ? (
          <HomeCard icon={Target} title={h('suite')} tone="violet" isRtl={isRtl}>
            <ul className="space-y-2">
              {widgetCards.map((card) => {
                const Icon = suiteIcons[card.key] ?? Target;
                return (
                  <li key={card.key} className="wd-row">
                    <span className="wd-row-ic"><Icon strokeWidth={1.75} /></span>
                    <Link href={href(card.href)} className="min-w-0 flex-1 truncate text-sm">{card.label}</Link>
                    <span className="text-sm font-semibold tabular-nums">{cmp(Number(card.value))}</span>
                    <button type="button" className="wd-row-x" aria-label={suiteT('hide')} title={suiteT('hide')} onClick={() => void toggle(card.key, false)}>
                      <X className="size-3.5" />
                    </button>
                  </li>
                );
              })}
            </ul>
          </HomeCard>
        ) : null}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <HomeCard icon={ListTodo} title={t('todosTitle')} tone="blue" href={href('pm/tasks')} isRtl={isRtl}>
          <div className="mb-3 grid grid-cols-4 gap-2">
            {[...buckets.map((b) => ({ id: b.id, title: b.title, count: b.count })), { id: 'sites', title: h('sitesShort'), count: todos.counts?.problems ?? sites.length }].map((b) => {
              const Icon = bucketIcons[b.id] ?? Globe;
              const color = bucketTone[b.id] ?? WD_CHART.amber;
              return (
                <div key={b.id} className="wd-bucket">
                  <Icon className="size-4" style={{ color }} strokeWidth={1.75} />
                  <span className="text-base font-semibold tabular-nums">{fmt(b.count)}</span>
                  <span className="truncate text-[11px] text-muted-foreground">{b.title}</span>
                </div>
              );
            })}
          </div>
          {topWork.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('todosEmpty')}</p>
          ) : (
            <ul className="space-y-1.5">
              {topWork.map((row) => {
                const Icon = bucketIcons[row.bucket] ?? ListTodo;
                return (
                  <li key={`${row.bucket}-${row.kind}-${row.id}`} className="wd-row">
                    <span className="wd-row-ic" style={{ color: bucketTone[row.bucket] }}><Icon strokeWidth={1.75} /></span>
                    <Link href={href(row.href || 'pm/tasks')} className="min-w-0 flex-1 truncate text-sm">
                      {row.title || fmt(row.id)}
                    </Link>
                    {row.due_at ? <span className="shrink-0 text-[11px] text-muted-foreground">{formatDate(row.due_at)}</span> : null}
                  </li>
                );
              })}
            </ul>
          )}
        </HomeCard>
        <HomeCard
          icon={Bell}
          title={t('ops.notifications')}
          tone="amber"
          href={href('notifications')}
          isRtl={isRtl}
          aside={n(notes.unread) > 0 ? <span className="wd-hc-chip wd-hc-chip--hot">{fmt(n(notes.unread))}</span> : null}
        >
          {(notes.recent ?? []).length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('noNotifications')}</p>
          ) : (
            <ul className="space-y-1.5">
              {(notes.recent ?? []).slice(0, 5).map((row, index) => (
                <li key={`${row.created_at ?? 'note'}-${index}`} className="wd-row">
                  <span className="wd-row-ic" style={{ color: row.is_read ? WD_CHART.slate : WD_CHART.blue }}>
                    {row.is_read ? <Bell strokeWidth={1.75} /> : <BellDot strokeWidth={1.75} />}
                  </span>
                  <span className={`min-w-0 flex-1 truncate text-sm ${row.is_read ? 'text-muted-foreground' : 'font-medium'}`}>
                    {row.title ? formatDigits(row.title) : t('ops.notifications')}
                  </span>
                  {row.created_at ? <span className="shrink-0 text-[11px] text-muted-foreground">{formatDate(row.created_at)}</span> : null}
                </li>
              ))}
            </ul>
          )}
        </HomeCard>
      </div>
    </div>
  );
}
