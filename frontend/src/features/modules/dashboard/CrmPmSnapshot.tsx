'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

type TodoRow = {
  id: number;
  title?: string;
  status?: string;
  due_at?: string | null;
  project_id?: number | null;
};

type Snapshot = {
  crm?: Record<string, number>;
  pm?: Record<string, number>;
  suite?: Record<string, number>;
  todos?: {
    counts?: { overdue?: number; today?: number; mine?: number };
    overdue?: TodoRow[];
    today?: TodoRow[];
    mine?: TodoRow[];
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
  const { formatNumber, formatDate, isRtl } = useLocale();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
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
  const todos = data.todos ?? {};
  const buckets = [
    { id: 'overdue', title: t('todosOverdue'), rows: todos.overdue ?? [], count: todos.counts?.overdue ?? 0 },
    { id: 'today', title: t('todosToday'), rows: todos.today ?? [], count: todos.counts?.today ?? 0 },
    { id: 'mine', title: t('todosMine'), rows: todos.mine ?? [], count: todos.counts?.mine ?? 0 },
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
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <CardTitle className="text-base">{t('todosTitle')}</CardTitle>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'pm/tasks')}>{t('viewModule')}</Link>
          </Button>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 lg:grid-cols-3">
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
                      <li key={`${bucket.id}-${row.id}`} className="rounded-md border px-2 py-1.5">
                        <Link href={dashboardHref(locale, 'pm/tasks')} className="font-medium hover:underline">
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
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
