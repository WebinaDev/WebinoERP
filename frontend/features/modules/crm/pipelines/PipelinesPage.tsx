'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
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
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={t('title')} description={t('description')} {...layoutProps}>
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
                <TableHead>Name</TableHead>
                <TableHead>Stages</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.name ?? '')}</TableCell>
                  <TableCell>{String((r.stages as unknown[])?.length ?? 0)}</TableCell>
                  <TableCell>
                    <Button size="sm" variant="outline" onClick={() => setSelectedPipeline(Number(r.id))}>{t('newStage')}</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {selectedPipeline ? (
        <Card>
          <CardContent className="flex gap-2 pt-6">
            <Input placeholder={t('newStage')} value={stageName} onChange={(e) => setStageName(e.target.value)} />
            <Button onClick={() => void addStage()}>{tNav('common.add')}</Button>
          </CardContent>
        </Card>
      ) : null}
    </CrmPageLayout>
  );
}
