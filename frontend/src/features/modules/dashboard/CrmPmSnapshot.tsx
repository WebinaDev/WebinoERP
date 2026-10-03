'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LocaleBarChart } from '@/components/charts/LocaleCharts';
import { formatChartAxis } from '@/lib/locale/calendar-date';
import { localizeReasons, localizeToken } from '@/features/modules/dashboard/localize';

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

const CRM_CARDS = [
  { key: 'customers', label: 'crmCustomers', href: 'crm/customers' },
  { key: 'deals', label: 'crmDeals', href: 'crm/deals' },
  { key: 'consultations', label: 'crmConsultations', href: 'crm/consultations' },
  { key: 'pipelines', label: 'crmPipelines', href: 'crm/pipelines' },
  { key: 'leads', label: 'crmLeads', href: 'crm/leads' },
] as const;

const PM_CARDS = [
  { key: 'projects', label: 'pmProjects', href: 'pm/projects' },
  { key: 'open_tasks', label: 'pmOpenTasks', href: 'pm/tasks' },
  { key: 'tickets', label: 'pmTickets', href: 'crm/tickets' },
  { key: 'contracts', label: 'pmContracts', href: 'docs/contracts' },
] as const;

export function CrmPmSnapshot() {
  const t = useTranslations('dashboard');
  const { formatNumber, formatDate, formatDateTime, formatDigits, isRtl, locale } = useLocale();
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
  const labelOf = (value: string) => localizeToken(value, (key) => t(key as 'badges.open'), formatDigits);

  const groups: { title: string; cards: { key: string; label: string; value: number; href: string }[] }[] = [
    {
      title: t('ops.finance'),
      cards: [
        { key: 'invoices', label: t('ops.invoices'), value: Number(finance.invoice_count ?? 0), href: 'finance/invoices' },
        { key: 'invoice_total', label: t('ops.invoiceTotal'), value: Number(finance.invoice_total ?? 0), href: 'finance/reports' },
        { key: 'open_invoices', label: t('ops.openInvoices'), value: Number(finance.open_invoices ?? 0), href: 'finance/invoices' },
        { key: 'receipts', label: t('ops.receipts'), value: Number(finance.receipts_count ?? 0), href: 'finance/receipts' },
      ],
    },
    {
      title: t('ops.tickets'),
      cards: [
        { key: 'open', label: t('ops.openTickets'), value: Number(ticketOps.open ?? pm.tickets ?? 0), href: 'crm/tickets' },
        { key: 'sla', label: t('ops.slaBreaches'), value: Number(ticketOps.sla_breaches ?? 0), href: 'crm/tickets' },
        { key: 'mine', label: t('ops.myTickets'), value: Number(ticketOps.mine ?? 0), href: 'crm/tickets' },
      ],
    },
    {
      title: t('ops.sites'),
      cards: [
        { key: 'total', label: t('ops.sitesTotal'), value: Number(siteOps.sites_total ?? 0), href: 'admin/platform/sites' },
        { key: 'ready', label: t('ops.sitesReady'), value: Number(siteOps.sites_ready ?? 0), href: 'admin/platform/sites' },
        { key: 'failed', label: t('ops.sitesFailed'), value: Number(siteOps.sites_failed ?? 0), href: 'admin/platform/sites' },
        { key: 'problems', label: t('ops.sitesProblems'), value: Number(siteOps.sites_problems ?? sites.length), href: 'admin/platform/sites' },
      ],
    },
    {
      title: t('ops.marketing'),
      cards: [
        { key: 'pages', label: t('ops.publishedPages'), value: Number(marketing.published_pages ?? 0), href: 'marketing/pages' },
        { key: 'forms', label: t('ops.formSubmissions'), value: Number(marketing.form_submissions ?? 0), href: 'marketing/forms' },
        { key: 'posts', label: t('ops.publishedPosts'), value: Number(marketing.published_posts ?? 0), href: 'marketing/blog' },
        { key: 'content', label: t('ops.scheduledContent'), value: Number(marketing.scheduled_content ?? 0), href: 'crm/content' },
        { key: 'announcements', label: t('ops.announcements'), value: Number(marketing.announcements ?? 0), href: 'marketing/announcements' },
      ],
    },
  ];

  return (
    <div className="space-y-4" dir={isRtl ? 'rtl' : 'ltr'}>
      <h2 className="text-base font-semibold">{t('crmPmTitle')}</h2>
      {widgetCards.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {widgetCards.map((card) => (
            <Card key={card.key}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{card.label}</CardTitle>
              </CardHeader>
              <CardContent className="flex items-end justify-between gap-2">
                <p className="text-2xl font-semibold tabular-nums">{formatNumber(Number(card.value))}</p>
                <div className="flex flex-col items-end">
                  <Button variant="link" size="sm" className="h-auto px-0" asChild>
                    <Link href={dashboardHref(locale, card.href)}>{t('viewModule')}</Link>
                  </Button>
                  <Button type="button" variant="ghost" size="sm" className="h-auto px-0 text-xs" onClick={() => void toggle(card.key, false)}>
                    {suiteT('hide')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : null}
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {CRM_CARDS.map((card) => (
          <Card key={card.key}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">{t(card.label)}</CardTitle>
            </CardHeader>
            <CardContent className="flex items-end justify-between gap-2">
              <p className="text-2xl font-semibold tabular-nums">{formatNumber(Number(crm[card.key] ?? 0))}</p>
              <Button variant="link" size="sm" className="h-auto px-0" asChild>
                <Link href={dashboardHref(locale, card.href)}>{t('viewModule')}</Link>
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {PM_CARDS.map((card) => (
          <Card key={card.key}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">{t(card.label)}</CardTitle>
            </CardHeader>
            <CardContent className="flex items-end justify-between gap-2">
              <p className="text-2xl font-semibold tabular-nums">{formatNumber(Number(pm[card.key] ?? 0))}</p>
              <Button variant="link" size="sm" className="h-auto px-0" asChild>
                <Link href={dashboardHref(locale, card.href)}>{t('viewModule')}</Link>
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>
      {groups.map((group) => (
        <section key={group.title} className="space-y-2">
          <h3 className="text-sm font-semibold">{group.title}</h3>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {group.cards.map((card) => (
              <Card key={card.key}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">{card.label}</CardTitle>
                </CardHeader>
                <CardContent className="flex items-end justify-between gap-2">
                  <p className="text-2xl font-semibold tabular-nums">{formatNumber(card.value)}</p>
                  <Button variant="link" size="sm" className="h-auto px-0" asChild>
                    <Link href={dashboardHref(locale, card.href)}>{t('viewModule')}</Link>
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>
      ))}

      {(ops.trend?.length ?? 0) > 0 ? (
        <Card>
          <CardHeader className="py-3">
            <CardTitle className="text-base">{t('trendTitle')}</CardTitle>
          </CardHeader>
          <CardContent>
            <LocaleBarChart
              data={(ops.trend ?? []).map((point) => ({
                label: formatChartAxis(point.date, axisLocale),
                value: Number(point.total ?? 0),
              }))}
            />
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <CardTitle className="text-base">{t('todosTitle')}</CardTitle>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'pm/tasks')}>{t('viewModule')}</Link>
          </Button>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 lg:grid-cols-4">
            {buckets.map((bucket) => (
              <div key={bucket.id} className="space-y-2">
                <p className="text-sm font-medium">
                  {bucket.title}{' '}
                  <span className="tabular-nums text-muted-foreground">({formatNumber(bucket.count)})</span>
                </p>
                {bucket.rows.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('todosEmpty')}</p>
                ) : (
                  <ul className="space-y-2 text-sm">
                    {bucket.rows.map((row) => (
                      <li key={`${bucket.id}-${row.kind}-${row.id}`} className="rounded-md border px-2 py-1.5">
                        <div className="mb-1 flex flex-wrap items-center gap-1">
                          <Badge variant="outline">{t(`kinds.${row.kind as 'task'}`)}</Badge>
                          {row.status ? <Badge variant="secondary">{labelOf(String(row.status))}</Badge> : null}
                        </div>
                        <Link href={dashboardHref(locale, row.href || 'pm/tasks')} className="font-medium hover:underline">
                          {row.title || formatNumber(row.id)}
                        </Link>
                        {row.due_at ? (
                          <p className="text-xs text-muted-foreground">{formatDate(row.due_at)}</p>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            ))}
            <div className="space-y-2">
              <p className="text-sm font-medium">
                {t('sitesProblems')}{' '}
                <span className="tabular-nums text-muted-foreground">({formatNumber(todos.counts?.problems ?? sites.length)})</span>
              </p>
              {sites.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('todosEmpty')}</p>
              ) : (
                <ul className="space-y-2 text-sm">
                  {sites.map((site) => (
                    <li key={`site-${site.id}`} className="rounded-md border px-2 py-1.5">
                      <div className="mb-1 flex flex-wrap items-center gap-1">
                        <Badge variant="outline">{t('kinds.site')}</Badge>
                        {site.status ? <Badge variant="secondary">{labelOf(String(site.status))}</Badge> : null}
                      </div>
                      <Link href={dashboardHref(locale, site.href || `admin/platform/sites/${site.id}`)} className="font-medium hover:underline" dir="ltr">
                        {site.domain || site.title}
                      </Link>
                      {site.reasons?.length ? (
                        <p className="text-xs text-muted-foreground">{localizeReasons(site.reasons, (key) => t(key as 'badges.open'))}</p>
                      ) : null}
                      {site.error ? <p className="text-xs text-muted-foreground">{formatDigits(site.error)}</p> : null}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="py-3">
          <CardTitle className="text-base">
            {t('notificationsRecent')}{' '}
            <span className="text-sm font-normal text-muted-foreground">
              ({t('ops.unread')}: {formatNumber(Number(notes.unread ?? 0))})
            </span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          {(notes.recent ?? []).length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('noNotifications')}</p>
          ) : (
            <ul className="space-y-2 text-sm">
              {(notes.recent ?? []).map((row, index) => (
                <li key={`${row.created_at ?? 'note'}-${index}`} className="flex items-start justify-between gap-3 rounded-md border px-2 py-1.5">
                  <div>
                    <p className="font-medium">{row.title ? formatDigits(row.title) : t('ops.notifications')}</p>
                    {row.created_at ? <p className="text-xs text-muted-foreground">{formatDateTime(row.created_at)}</p> : null}
                  </div>
                  <Badge variant={row.is_read ? 'outline' : 'secondary'}>{row.is_read ? t('read') : t('unread')}</Badge>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
