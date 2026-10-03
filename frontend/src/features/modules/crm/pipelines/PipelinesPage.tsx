'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { listPipelines, savePipeline, savePipelineStage } from '@/lib/api/crm-deals';
import { normalizeListPayload } from '@/lib/list-utils';

export function PipelinesPage() {
  const t = useTranslations('crm.pipelines');
  const tNav = useTranslations();
  const { formatNumber, isRtl } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [name, setName] = useState('');
  const [stageName, setStageName] = useState('');
  const [selectedPipeline, setSelectedPipeline] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await listPipelines();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const createPipeline = async () => {
    try {
      await savePipeline({ name });
      setName('');
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const addStage = async () => {
    if (!selectedPipeline) return;
    try {
      await savePipelineStage(selectedPipeline, { name: stageName });
      setStageName('');
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={t('title')} description={t('description')} {...layoutProps}>
      <div dir={isRtl ? 'rtl' : 'ltr'} className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap gap-2 pt-6">
          <Input placeholder={t('newPipeline')} value={name} onChange={(e) => setName(e.target.value)} />
          <Button onClick={() => void createPipeline()}>{tNav('common.create')}</Button>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('colName')}</TableHead>
                <TableHead>{t('colStages')}</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-center text-muted-foreground">{t('empty')}</TableCell>
                </TableRow>
              ) : null}
              {rows.map((r) => {
                const stages = (Array.isArray(r.stages) ? r.stages : []) as { name?: string }[];
                return (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.name ?? '')}</TableCell>
                  <TableCell className="tabular-nums">
                    {formatNumber(stages.length)}
                    {stages.length > 0 ? ` (${stages.map((s) => s.name).filter(Boolean).join('، ')})` : ''}
                  </TableCell>
                  <TableCell>
                    <Button size="sm" variant="outline" onClick={() => setSelectedPipeline(Number(r.id))}>{t('newStage')}</Button>
                  </TableCell>
                </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {selectedPipeline ? (
        <Card>
          <CardContent className="space-y-2 pt-6">
            <p className="text-sm text-muted-foreground">{t('stagesHint')}</p>
            <div className="flex gap-2">
            <Input placeholder={t('newStage')} value={stageName} onChange={(e) => setStageName(e.target.value)} />
            <Button onClick={() => void addStage()}>{tNav('common.add')}</Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
      </div>
    </CrmPageLayout>
  );
}
