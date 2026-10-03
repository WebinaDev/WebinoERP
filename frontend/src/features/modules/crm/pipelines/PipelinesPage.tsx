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
import { deletePipelineStage, listPipelines, savePipeline, savePipelineStage, updatePipelineStage } from '@/lib/api/crm-deals';
import { normalizeListPayload } from '@/lib/list-utils';

type Stage = { id: number; name?: string; color?: string | null; sort_order?: number | null };

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

  const selected = rows.find((row) => Number(row.id) === selectedPipeline) ?? null;
  const stages = ((selected && Array.isArray(selected.stages) ? selected.stages : []) as Stage[]).slice().sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));

  const addStage = async () => {
    if (!selectedPipeline || !stageName.trim()) return;
    try {
      await savePipelineStage(selectedPipeline, {
        name: stageName.trim(),
        sort_order: stages.length,
        color: '#64748b',
        probability: 0,
      });
      setStageName('');
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const moveStage = async (index: number, direction: -1 | 1) => {
    if (!selectedPipeline) return;
    const next = stages.slice();
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    const current = next[index];
    next[index] = next[target]!;
    next[target] = current!;
    try {
      await Promise.all(
        next.map((stage, order) => updatePipelineStage(selectedPipeline, stage.id, { sort_order: order })),
      );
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const changeColor = async (stage: Stage, color: string) => {
    if (!selectedPipeline) return;
    try {
      await updatePipelineStage(selectedPipeline, stage.id, { color });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const removeStage = async (stage: Stage) => {
    if (!selectedPipeline) return;
    if (!window.confirm(t('confirmDeleteStage'))) return;
    try {
      await deletePipelineStage(selectedPipeline, stage.id);
      setSuccess(tNav('common.deleted'));
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
            <ul className="space-y-2">
              {stages.map((stage, index) => (
                <li key={stage.id} className="flex flex-wrap items-center gap-2 rounded-md border p-2">
                  <span className="h-4 w-4 rounded-full border" style={{ backgroundColor: stage.color || '#64748b' }} />
                  <span className="min-w-[8rem] flex-1 text-sm">{stage.name}</span>
                  <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    {t('color')}
                    <input
                      key={`${stage.id}-${stage.color ?? ''}`}
                      type="color"
                      aria-label={t('color')}
                      defaultValue={stage.color && /^#[0-9a-fA-F]{6}$/.test(stage.color) ? stage.color : '#64748b'}
                      onBlur={(e) => void changeColor(stage, e.target.value)}
                    />
                  </label>
                  <Button type="button" size="sm" variant="outline" disabled={index === 0} onClick={() => void moveStage(index, -1)}>
                    {t('moveUp')}
                  </Button>
                  <Button type="button" size="sm" variant="outline" disabled={index === stages.length - 1} onClick={() => void moveStage(index, 1)}>
                    {t('moveDown')}
                  </Button>
                  <Button type="button" size="sm" variant="ghost" onClick={() => void removeStage(stage)}>
                    {tNav('common.delete')}
                  </Button>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ) : null}
      </div>
    </CrmPageLayout>
  );
}
