'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardHref } from '@/lib/route-resolver';
import { AiContentShell } from '../components/AiContentShell';
import {
  type AiJob,
  type AiOverview,
  fetchAiJobs,
  fetchAiOverview,
  retryAiJob,
} from '../lib/ai-content-api';

export function AiOverviewPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [overview, setOverview] = useState<AiOverview | null>(null);
  const [jobs, setJobs] = useState<AiJob[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [o, j] = await Promise.all([fetchAiOverview(), fetchAiJobs({ limit: 8 })]);
      setOverview(o);
      setJobs(j.items ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const retry = async (id: number) => {
    try {
      await retryAiJob(id);
      setSuccess(t('jobRetried'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const stats = [
    { key: 'pending', value: overview?.jobs_pending ?? 0, path: 'ai-content/jobs' },
    { key: 'failed', value: overview?.jobs_failed ?? 0, path: 'ai-content/jobs' },
    { key: 'done', value: overview?.jobs_done ?? 0, path: 'ai-content/jobs' },
    { key: 'calendar', value: overview?.calendar_upcoming ?? 0, path: 'ai-content/calendar' },
  ] as const;

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.overview')} {...layoutProps}>
      <AiContentShell active="overview">
        {loading ? (
          <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {stats.map((s) => (
              <Link key={s.key} href={dashboardHref(locale, s.path)} className="block">
                <Card className="h-full transition-colors hover:bg-muted/40">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                      {t(`stat.${s.key}`)}
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-semibold">{s.value}</div>
                  </CardContent>
                </Card>
              </Link>
            ))}
          </div>
        )}

        <Card>
          <CardHeader>
            <CardTitle>{t('incompleteTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p className="text-sm text-muted-foreground">
              {t('incompleteCount', { count: overview?.incomplete_products ?? 0 })}
            </p>
            {(overview?.sample_incomplete ?? []).map((row) => (
              <div key={row.id} className="flex items-center justify-between gap-2 text-sm">
                <span>{row.name}</span>
                <span className="text-muted-foreground">{(row.missing ?? []).join(', ')}</span>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle>{t('jobsTitle')}</CardTitle>
            <Button asChild variant="ghost" size="sm">
              <Link href={dashboardHref(locale, 'ai-content/jobs')}>{t('jobsViewAll')}</Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-2">
            {jobs.map((job) => (
              <div key={job.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">
                    #{job.id} · {job.job_type} · {job.status}
                  </div>
                  <div className="text-muted-foreground">{job.result_summary || job.error_message || '—'}</div>
                </div>
                {job.status === 'failed' ? (
                  <Button size="sm" variant="outline" onClick={() => void retry(job.id)}>
                    {t('retry')}
                  </Button>
                ) : null}
              </div>
            ))}
            {!jobs.length ? <p className="text-sm text-muted-foreground">{t('noJobs')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
