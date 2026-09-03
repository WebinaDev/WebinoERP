'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  DndContext,
  DragEndEvent,
  PointerSensor,
  closestCorners,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { getPipelineKanban, listPipelines, moveDeal, saveDeal } from '@/lib/api/crm-deals';

type Stage = { id: number; name: string; color?: string | null };
type Deal = { id: number; title: string; stage_id?: number; amount?: number };

function DealCard({ deal }: { deal: Deal }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: `deal-${deal.id}` });
  const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
  return (
    <div ref={setNodeRef} style={style} className={cn('rounded-md border bg-background p-2 text-sm shadow-sm', isDragging && 'opacity-60')} {...listeners} {...attributes}>
      <p className="font-medium">{deal.title}</p>
      {deal.amount != null ? <p className="text-xs text-muted-foreground">{deal.amount}</p> : null}
    </div>
  );
}

function StageColumn({ stage, children }: { stage: Stage; children: React.ReactNode }) {
  const { setNodeRef, isOver } = useDroppable({ id: `stage-${stage.id}` });
  return (
    <div ref={setNodeRef} className={cn('flex min-h-[280px] min-w-[220px] flex-1 flex-col gap-2 rounded-lg border bg-muted/30 p-2', isOver && 'ring-2 ring-primary/40')}>
      <p className="text-xs font-semibold text-muted-foreground">{stage.name}</p>
      {children}
    </div>
  );
}

export function DealsKanbanPage() {
  const t = useTranslations('crm.deals');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [pipelines, setPipelines] = useState<Record<string, unknown>[]>([]);
  const [pipelineId, setPipelineId] = useState<string>('');
  const [stages, setStages] = useState<Stage[]>([]);
  const [dealsByStage, setDealsByStage] = useState<Record<number, Deal[]>>({});
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const loadPipelines = useCallback(async () => {
    try {
      const res = await listPipelines();
      const rows = (res as { data?: Record<string, unknown>[] })?.data ?? (Array.isArray(res) ? res : []);
      setPipelines(rows as Record<string, unknown>[]);
      if (!pipelineId && rows[0]) setPipelineId(String((rows[0] as { id: number }).id));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [pipelineId, applyAxiosError]);

  const loadKanban = useCallback(async () => {
    if (!pipelineId) return;
    try {
      const res = await getPipelineKanban(pipelineId);
      const data = res as { stages?: Stage[]; deals?: Deal[] };
      const st = data.stages ?? [];
      setStages(st);
      const map: Record<number, Deal[]> = {};
      for (const s of st) map[s.id] = [];
      for (const d of data.deals ?? []) {
        const sid = d.stage_id ?? st[0]?.id;
        if (sid) (map[sid] ??= []).push(d);
      }
      setDealsByStage(map);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [pipelineId, applyAxiosError]);

  useEffect(() => {
    void loadPipelines();
  }, [loadPipelines]);

  useEffect(() => {
    void loadKanban();
  }, [loadKanban]);

  const onDragEnd = async (event: DragEndEvent) => {
    const dealId = String(event.active.id).replace('deal-', '');
    const over = event.over?.id ? String(event.over.id).replace('stage-', '') : '';
    if (!dealId || !over) return;
    try {
      await moveDeal(dealId, Number(over));
      setSuccess(t('moveSuccess'));
      void loadKanban();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const createDeal = async () => {
    if (!pipelineId || !stages[0]) return;
    try {
      await saveDeal({ title, pipeline_id: Number(pipelineId), stage_id: stages[0].id });
      setOpen(false);
      setTitle('');
      void loadKanban();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const columns = useMemo(() => stages, [stages]);

  return (
    <CrmPageLayout title={t('title')} description={t('description')} actions={<Button onClick={() => setOpen(true)}>{t('newDeal')}</Button>} {...layoutProps}>
      <div className="flex flex-wrap items-center gap-3">
        <Select value={pipelineId} onValueChange={setPipelineId}>
          <SelectTrigger className="w-[220px]"><SelectValue placeholder={t('pipeline')} /></SelectTrigger>
          <SelectContent>
            {pipelines.map((p) => (
              <SelectItem key={String(p.id)} value={String(p.id)}>{String(p.name ?? p.id)}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {!pipelineId ? <p className="text-sm text-muted-foreground">{t('noPipeline')}</p> : (
        <DndContext sensors={sensors} collisionDetection={closestCorners} onDragEnd={(e) => void onDragEnd(e)}>
          <div className="flex gap-3 overflow-x-auto pb-2">
            {columns.map((stage) => (
              <StageColumn key={stage.id} stage={stage}>
                {(dealsByStage[stage.id] ?? []).map((deal) => (
                  <DealCard key={deal.id} deal={deal} />
                ))}
              </StageColumn>
            ))}
          </div>
        </DndContext>
      )}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newDeal')}</DialogTitle></DialogHeader>
          <Input value={title} onChange={(e) => setTitle(e.target.value)} />
          <DialogFooter><Button onClick={() => void createDeal()}>{tNav('common.save')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
