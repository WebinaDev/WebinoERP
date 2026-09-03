'use client';

import { useCallback, useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AiContentShell } from '../components/AiContentShell';
import {
  type AiJob,
  cancelAiJob,
  cancelPendingAiJobs,
  fetchAiJobs,
  fetchAiQueue,
  retryAiJob,
  runDueJobs,
  setAiQueuePaused,
} from '../lib/ai-content-api';

const FILTERS = ['', 'pending', 'running', 'failed', 'cancelled', 'done'] as const;

export function AiJobsPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const status = searchParams.get('status') ?? '';
  const filter = FILTERS.includes(status as (typeof FILTERS)[number]) ? status : '';
  const [jobs, setJobs] = useState<AiJob[]>([]);
  const [paused, setPaused] = useState(false);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [j, q] = await Promise.all([
        fetchAiJobs({ status: filter || undefined, limit: 80 }),
        fetchAiQueue(),
      ]);
      setJobs(j.items ?? []);
      setPaused(!!q.paused);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, filter]);

  useEffect(() => {
    void load();
  }, [load]);

  const setFilter = (next: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (next) params.set('status', next);
    else params.delete('status');
    const qs = params.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname);
  };

  const runAction = async (fn: () => Promise<void>) => {
    setBusy(true);
    try {
      await fn();
      await load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  const costSum = jobs.reduce((s, j) => s + (Number(j.cost_toman) || 0), 0);

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.jobs')} {...layoutProps}>
      <AiContentShell active="jobs">
        <div className="flex flex-wrap items-center gap-2">
          {FILTERS.map((key) => (
            <Button
              key={key || 'all'}
              size="sm"
              variant={filter === key ? 'default' : 'outline'}
              onClick={() => setFilter(key)}
            >
              {t(key ? `jobsFilter.${key}` : 'jobsFilter.all')}
            </Button>
          ))}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            size="sm"
            disabled={busy}
            onClick={() =>
              void runAction(async () => {
                const res = await runDueJobs(5);
                setSuccess(res.paused ? t('queuePausedHint') : t('jobsRunDueDone', { count: res.count }));
              })
            }
          >
            {t('runDueJobs')}
          </Button>
          <Button
            size="sm"
            variant="outline"
            disabled={busy}
            onClick={() =>
              void runAction(async () => {
                const res = await setAiQueuePaused(!paused);
                setPaused(res.paused);
                setSuccess(res.paused ? t('queuePaused') : t('queueResumed'));
              })
            }
          >
            {paused ? t('queueResume') : t('queuePause')}
          </Button>
          <Button
            size="sm"
            variant="outline"
            disabled={busy}
            onClick={() =>
              void runAction(async () => {
                const res = await cancelPendingAiJobs();
                setSuccess(t('jobsCancelledCount', { count: res.count }));
              })
            }
          >
            {t('cancelPending')}
          </Button>
          <span className="text-sm text-muted-foreground">
            {t('stat.spend')}: {costSum.toLocaleString()}
          </span>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('jobsTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {jobs.map((job) => (
              <div key={job.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">
                    #{job.id} · {job.job_type} · {job.status}
                  </div>
                  <div className="text-muted-foreground">
                    {job.result_summary || job.error_message || `${job.provider}/${job.model || '—'}`}
                  </div>
                </div>
                <div className="flex gap-2">
                  {job.status === 'failed' || job.status === 'cancelled' ? (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={() =>
                        void runAction(async () => {
                          await retryAiJob(job.id);
                          setSuccess(t('jobRetried'));
                        })
                      }
                    >
                      {t('retry')}
                    </Button>
                  ) : null}
                  {job.status === 'pending' || job.status === 'running' ? (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={() =>
                        void runAction(async () => {
                          await cancelAiJob(job.id);
                          setSuccess(t('jobCancelled'));
                        })
                      }
                    >
                      {t('cancel')}
                    </Button>
                  ) : null}
                </div>
              </div>
            ))}
            {!jobs.length ? <p className="text-sm text-muted-foreground">{t('noJobs')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
