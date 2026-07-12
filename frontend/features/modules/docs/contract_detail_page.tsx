'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { dashboardHref } from '@/lib/route-resolver';

type Props = { id: string };

export function ContractDetailPage({ id }: Props) {
  const t = useTranslations('docs.contracts');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [projects, setProjects] = useState<Record<string, unknown>[]>([]);
  const [linkOpen, setLinkOpen] = useState(false);
  const [projectId, setProjectId] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get(`/v1/docs/contracts/${id}`);
      setData(unwrapData(res) as Record<string, unknown>);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openLink = async () => {
    try {
      const res = await apiClient.get('/v1/projects/projects', { params: { per_page: 50 } });
      setProjects(normalizeListPayload(unwrapData(res)));
      setLinkOpen(true);
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const linkProject = async () => {
    if (!projectId) return;
    try {
      await apiClient.post(`/v1/docs/contracts/${id}/projects`, { project_id: Number(projectId) });
      setLinkOpen(false);
      setSuccess(t('projectLinked'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const installments = (Array.isArray(data?.installments) ? data.installments : []) as Record<string, unknown>[];
  const lead = data?.lead as Record<string, unknown> | undefined;

  return (
    <CrmPageLayout
      title={String(data?.title ?? data?.name ?? tNav('nav.erp.docs.contracts'))}
      actions={
        <>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'docs/contracts')}>{tNav('common.back')}</Link>
          </Button>
          <Button onClick={() => void openLink()}>{t('linkProject')}</Button>
        </>
      }
      {...layoutProps}
    >
      <Card>
        <CardHeader><CardTitle className="text-lg">{String(data?.title ?? '—')}</CardTitle></CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            {t('amount')}: <strong>{String(data?.amount ?? '—')}</strong> — {t('status')}:{' '}
            <Badge variant="secondary">{String(data?.status ?? '—')}</Badge>
          </p>
          {data?.description ? <p>{String(data.description)}</p> : null}
        </CardContent>
      </Card>
      {installments.length > 0 ? (
        <Card className="mt-4">
          <CardHeader><CardTitle className="text-base">{t('installments')}</CardTitle></CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="py-2 text-start">{t('amount')}</th>
                  <th className="py-2 text-start">{t('dueDate')}</th>
                  <th className="py-2 text-start">{t('paid')}</th>
                </tr>
              </thead>
              <tbody>
                {installments.map((row) => (
                  <tr key={String(row.id ?? Math.random())} className="border-b border-border/60">
                    <td className="py-2">{String(row.amount ?? '—')}</td>
                    <td className="py-2">{String(row.due_date ?? '—')}</td>
                    <td className="py-2">{row.paid_at ? String(row.paid_at) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      ) : null}
      {lead ? (
        <Card className="mt-4">
          <CardHeader><CardTitle className="text-base">{t('leadInfo')}</CardTitle></CardHeader>
          <CardContent className="space-y-2 text-sm">
            <p><span className="text-muted-foreground">{t('topic')}: </span>{String(lead.topic ?? '—')}</p>
            <p><span className="text-muted-foreground">{t('email')}: </span>{String(lead.email ?? '—')}</p>
            <p><span className="text-muted-foreground">{t('mobile')}: </span>{String(lead.mobile ?? '—')}</p>
          </CardContent>
        </Card>
      ) : null}

      <Dialog open={linkOpen} onOpenChange={setLinkOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('selectProject')}</DialogTitle></DialogHeader>
          <Select value={projectId} onValueChange={setProjectId}>
            <SelectTrigger><SelectValue placeholder={t('selectProject')} /></SelectTrigger>
            <SelectContent>
              {projects.map((p) => (
                <SelectItem key={String(p.id)} value={String(p.id)}>{String(p.name ?? p.id)}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DialogFooter>
            <Button variant="secondary" onClick={() => setLinkOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void linkProject()}>{t('linkProject')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
