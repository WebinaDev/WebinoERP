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

type Props = { id: string };

function ProjectDetailContent({ data }: { data: Record<string, unknown> }) {
  const t = useTranslations('pm.projects');
  const tasks = (Array.isArray(data.tasks) ? data.tasks : []) as Record<string, unknown>[];
  const contracts = (Array.isArray(data.contracts) ? data.contracts : []) as Record<string, unknown>[];
  const tickets = (Array.isArray(data.tickets) ? data.tickets : []) as Record<string, unknown>[];

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">{String(data.name ?? t('title'))}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            <span className="text-muted-foreground">{t('status')}: </span>
            <Badge variant="secondary">{String(data.status ?? '—')}</Badge>
          </p>
          {data.description ? (
            <p>
              <span className="text-muted-foreground">{t('description')}: </span>
              {String(data.description)}
            </p>
          ) : null}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('tasks')} ({tasks.length})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
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
                  <td className="py-2">{String(row.title ?? '—')}</td>
                  <td className="py-2">{String(row.status ?? '—')}</td>
                  <td className="py-2">{String(row.priority ?? '—')}</td>
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
          <CardTitle className="text-base">{t('contracts')} ({contracts.length})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
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
                  <td className="py-2">{String(row.title ?? '—')}</td>
                  <td className="py-2">{String(row.amount ?? '—')}</td>
                  <td className="py-2">{String(row.status ?? '—')}</td>
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
          <CardTitle className="text-base">{t('tickets')} ({tickets.length})</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
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
                  <td className="py-2">{String(row.subject ?? '—')}</td>
                  <td className="py-2">{String(row.status ?? '—')}</td>
                  <td className="py-2">{String(row.created_at ?? '—')}</td>
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
      title={String(data?.name ?? `${t('title')} #${id}`)}
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
