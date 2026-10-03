'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';

type Props = { id: string };

function rowsOf(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? (value as Record<string, unknown>[]) : [];
}

function ProjectDetailContent({ data }: { data: Record<string, unknown> }) {
  const t = useTranslations('pm.projects');
  const tTasks = useTranslations('pm.tasks');
  const tCommon = useTranslations('common');
  const { formatDateTime, formatNumber, formatDigits, isRtl, locale } = useLocale();
  const none = tCommon('none');
  const tasks = rowsOf(data.tasks);
  const contracts = rowsOf(data.contracts);
  const tickets = rowsOf(data.tickets);
  const sites = rowsOf(data.sites);

  const projectStatus = (status: unknown) => {
    const key = String(status ?? '');
    const map: Record<string, string> = {
      active: t('statusActive'),
      on_hold: t('statusOnHold'),
      completed: t('statusCompleted'),
      draft: t('statusDraft'),
      cancelled: t('statusCancelled'),
      open: t('statusOpen'),
    };
    return map[key] ?? (key || none);
  };

  const taskStatus = (status: unknown) => {
    const key = String(status ?? '');
    return key && tTasks.has(key as 'todo') ? tTasks(key as 'todo') : key || none;
  };

  const priorityLabel = (priority: unknown) => {
    const key = String(priority ?? '');
    const map: Record<string, 'priorityLow' | 'priorityNormal' | 'priorityHigh' | 'priorityUrgent'> = {
      low: 'priorityLow',
      normal: 'priorityNormal',
      high: 'priorityHigh',
      urgent: 'priorityUrgent',
    };
    const message = map[key];
    return message ? tTasks(message) : key || none;
  };

  return (
    <div className="space-y-4" dir={isRtl ? 'rtl' : 'ltr'}>
      <Card>
        <CardHeader>
          <CardTitle className="text-start text-lg">{String(data.name ?? t('title'))}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            <span className="text-muted-foreground">{t('status')}: </span>
            <Badge variant="secondary">{projectStatus(data.status)}</Badge>
          </p>
          {data.description ? (
            <p>
              <span className="text-muted-foreground">{t('description')}: </span>
              {String(data.description)}
            </p>
          ) : null}
          {sites.length ? (
            <div className="flex flex-wrap gap-2 pt-1">
              {sites.map((site) => (
                <Button key={String(site.id)} variant="outline" size="sm" asChild>
                  <Link href={dashboardHref(locale, String(site.builder_path ?? `admin/platform/sites/${String(site.id)}`))}>
                    {t('openBuilder')}
                  </Link>
                </Button>
              ))}
              {sites.filter((site) => site.domain && ['ready', 'active', 'launched'].includes(String(site.status))).map((site) => (
                <Button key={`live-${String(site.id)}`} variant="secondary" size="sm" asChild>
                  <a href={`https://${String(site.domain)}`} target="_blank" rel="noopener noreferrer">
                    {t('openSite')}
                  </a>
                </Button>
              ))}
            </div>
          ) : null}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">{t('tasks')} ({formatNumber(tasks.length)})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('taskTitle')}</th>
                <th className="py-2 text-start">{t('status')}</th>
                <th className="py-2 text-start">{t('priority')}</th>
              </tr>
            </thead>
            <tbody>
              {tasks.map((row) => (
                <tr key={String(row.id)} className="border-b border-border/60">
                  <td className="py-2 text-start">{String(row.title ?? none)}</td>
                  <td className="py-2 text-start">{taskStatus(row.status)}</td>
                  <td className="py-2 text-start">{priorityLabel(row.priority)}</td>
                </tr>
              ))}
              {!tasks.length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('noTasks')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">{t('contracts')} ({formatNumber(contracts.length)})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('taskTitle')}</th>
                <th className="py-2 text-start">{t('amount')}</th>
                <th className="py-2 text-start">{t('status')}</th>
              </tr>
            </thead>
            <tbody>
              {contracts.map((row) => (
                <tr key={String(row.id)} className="border-b border-border/60">
                  <td className="py-2 text-start">
                    <Link
                      className="text-primary hover:underline"
                      href={`${dashboardHref(locale, 'docs/contracts')}?contract_id=${String(row.id)}`}
                    >
                      {String(row.title ?? none)}
                    </Link>
                  </td>
                  <td className="py-2 text-start">
                    {row.amount != null && row.amount !== '' ? formatNumber(Number(row.amount)) : none}
                  </td>
                  <td className="py-2 text-start">{projectStatus(row.status)}</td>
                </tr>
              ))}
              {!contracts.length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('noContracts')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-start text-base">{t('tickets')} ({formatNumber(tickets.length)})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('subject')}</th>
                <th className="py-2 text-start">{t('status')}</th>
                <th className="py-2 text-start">{t('createdAt')}</th>
              </tr>
            </thead>
            <tbody>
              {tickets.map((row) => (
                <tr key={String(row.id)} className="border-b border-border/60">
                  <td className="py-2 text-start">{String(row.subject ?? none)}</td>
                  <td className="py-2 text-start">{taskStatus(row.status)}</td>
                  <td className="py-2 text-start">
                    {row.created_at ? formatDateTime(String(row.created_at)) || formatDigits(String(row.created_at)) : none}
                  </td>
                </tr>
              ))}
              {!tickets.length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('noTickets')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  );
}

export function ProjectDetailPage({ id }: Props) {
  const t = useTranslations('pm.projects');
  const tNav = useTranslations();
  const { formatDigits } = useLocale();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(`/v1/projects/projects/${id}/details`);
      setData(unwrapData(res) as Record<string, unknown>);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout
      title={String(data?.name ?? `${t('title')} #${formatDigits(id)}`)}
      actions={
        <Button variant="outline" size="sm" asChild>
          <Link href={dashboardHref(locale, 'pm/projects')}>{t('backToList')}</Link>
        </Button>
      }
      {...layoutProps}
    >
      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-48 w-full" />
        </div>
      ) : data ? (
        <ProjectDetailContent data={data} />
      ) : (
        <p className="text-sm text-muted-foreground">{tNav('errors.notFoundBody')}</p>
      )}
    </CrmPageLayout>
  );
}
