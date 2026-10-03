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
import { useLocale } from '@/hooks/use-locale';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { cn } from '@/lib/utils';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { getPipelineKanban, listPipelines, moveDeal, saveDeal } from '@/lib/api/crm-deals';

type Stage = { id: number; name: string; color?: string | null; is_closed?: boolean; is_won?: boolean };
type Deal = {
  id: number;
  name: string;
  title?: string;
  stage_id?: number;
  amount?: number | string | null;
  probability?: number | string | null;
  close_date?: string | null;
};
type AccountOpt = { id: number; name?: string; company_name?: string };

function isoDay(value: unknown): string {
  const match = /^(\d{4}-\d{2}-\d{2})/.exec(String(value ?? ''));
  return match ? match[1] : '';
}

function DealCard({
  deal,
  amountLabel,
  onSave,
}: {
  deal: Deal;
  amountLabel?: string;
  onSave: (payload: { amount: number | null; probability: number | null; close_date: string | null }) => Promise<void>;
}) {
  const t = useTranslations('crm.deals');
  const tCommon = useTranslations('common');
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: `deal-${deal.id}` });
  const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
  const label = deal.name ?? deal.title ?? `#${deal.id}`;
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState(deal.amount != null && deal.amount !== '' ? String(deal.amount) : '');
  const [probability, setProbability] = useState(deal.probability != null && deal.probability !== '' ? String(deal.probability) : '');
  const [closeDate, setCloseDate] = useState(isoDay(deal.close_date));
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setAmount(deal.amount != null && deal.amount !== '' ? String(deal.amount) : '');
    setProbability(deal.probability != null && deal.probability !== '' ? String(deal.probability) : '');
    setCloseDate(isoDay(deal.close_date));
  }, [deal.amount, deal.probability, deal.close_date]);

  return (
    <div ref={setNodeRef} style={style} className={cn('rounded-md border bg-background p-2 text-start text-sm shadow-sm', isDragging && 'opacity-60')}>
      <div {...listeners} {...attributes} className="cursor-grab">
        <p className="font-medium">{label}</p>
        {amountLabel ? <p className="text-xs tabular-nums text-muted-foreground">{amountLabel}</p> : null}
        {deal.probability != null && deal.probability !== '' ? (
          <p className="text-xs text-muted-foreground">{t('probability')}: {deal.probability}</p>
        ) : null}
      </div>
      <div className="mt-2" onPointerDown={(event) => event.stopPropagation()}>
        <Button type="button" variant="ghost" size="sm" className="h-7 px-2" onClick={() => setOpen((value) => !value)}>
          {t('quickEdit')}
        </Button>
        {open ? (
          <div className="mt-2 space-y-2">
            <Input
              type="number"
              min={0}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder={t('amount')}
            />
            <Input
              type="number"
              min={0}
              max={100}
              value={probability}
              onChange={(e) => setProbability(e.target.value)}
              placeholder={t('probability')}
            />
            <LocaleDatePicker value={closeDate} onChange={setCloseDate} placeholder={t('closeDate')} />
            <div className="flex gap-2">
              <Button
                type="button"
                size="sm"
                disabled={saving}
                onClick={() => {
                  setSaving(true);
                  void onSave({
                    amount: amount.trim() === '' ? null : Number(amount),
                    probability: probability.trim() === '' ? null : Number(probability),
                    close_date: closeDate || null,
                  }).finally(() => setSaving(false));
                }}
              >
                {tCommon('save')}
              </Button>
              {closeDate ? (
                <Button type="button" size="sm" variant="ghost" onClick={() => setCloseDate('')}>
                  {tCommon('none')}
                </Button>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
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
  const { formatNumber, formatDigits, isRtl } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [pipelines, setPipelines] = useState<Record<string, unknown>[]>([]);
  const [pipelineId, setPipelineId] = useState<string>('');
  const [stages, setStages] = useState<Stage[]>([]);
  const [dealsByStage, setDealsByStage] = useState<Record<number, Deal[]>>({});
  const [accounts, setAccounts] = useState<AccountOpt[]>([]);
  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');
  const [accountId, setAccountId] = useState<string>('');
  const [lossOpen, setLossOpen] = useState(false);
  const [lossReason, setLossReason] = useState('');
  const [pendingMove, setPendingMove] = useState<{ dealId: string; stageId: string } | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const loadAccounts = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/crm/accounts', { params: { per_page: 100 } });
      setAccounts(normalizeListPayload(res.data) as AccountOpt[]);
    } catch {
      setAccounts([]);
    }
  }, []);

  const loadPipelines = useCallback(async () => {
    try {
      const res = await listPipelines();
      const rows = normalizeListPayload(res) as Record<string, unknown>[];
      setPipelines(rows);
      if (!pipelineId && rows[0]) setPipelineId(String((rows[0] as { id: number }).id));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [pipelineId, applyAxiosError]);

  const loadKanban = useCallback(async () => {
    if (!pipelineId) return;
    try {
      const res = await getPipelineKanban(pipelineId);
      const data = res as {
        columns?: { stage: Stage; deals: Deal[] }[];
        stages?: Stage[];
        deals?: Deal[];
      };
      const cols = data.columns ?? [];
      const st = cols.length > 0 ? cols.map((c) => c.stage) : (data.stages ?? []);
      setStages(st);
      const map: Record<number, Deal[]> = {};
      for (const s of st) map[s.id] = [];
      if (cols.length > 0) {
        for (const col of cols) {
          map[col.stage.id] = col.deals ?? [];
        }
      } else {
        for (const d of data.deals ?? []) {
          const sid = d.stage_id ?? st[0]?.id;
          if (sid) (map[sid] ??= []).push(d);
        }
      }
      setDealsByStage(map);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [pipelineId, applyAxiosError]);

  useEffect(() => {
    void loadPipelines();
    void loadAccounts();
  }, [loadPipelines, loadAccounts]);

  useEffect(() => {
    void loadKanban();
  }, [loadKanban]);

  const commitMove = async (dealId: string, stageId: string, reason?: string) => {
    try {
      await moveDeal(dealId, Number(stageId), reason);
      setSuccess(t('moveSuccess'));
      setLossOpen(false);
      setLossReason('');
      setPendingMove(null);
      void loadKanban();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const onDragEnd = async (event: DragEndEvent) => {
    const dealId = String(event.active.id).replace('deal-', '');
    const over = event.over?.id ? String(event.over.id).replace('stage-', '') : '';
    if (!dealId || !over) return;
    const stage = stages.find((item) => String(item.id) === over);
    if (stage && Boolean(stage.is_closed) && !stage.is_won) {
      setPendingMove({ dealId, stageId: over });
      setLossReason('');
      setLossOpen(true);
      return;
    }
    await commitMove(dealId, over);
  };

  const createDeal = async () => {
    if (!pipelineId || !stages[0] || !name.trim() || !accountId) return;
    try {
      await saveDeal({
        name: name.trim(),
        account_id: Number(accountId),
        pipeline_id: Number(pipelineId),
        stage_id: stages[0].id,
      });
      setOpen(false);
      setName('');
      setAccountId('');
      setSuccess(t('created'));
      void loadKanban();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const columns = useMemo(() => stages, [stages]);

  return (
    <CrmPageLayout title={t('title')} description={t('description')} actions={<Button onClick={() => setOpen(true)}>{t('newDeal')}</Button>} {...layoutProps}>
      <div dir={isRtl ? 'rtl' : 'ltr'} className="space-y-4">
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
                {(dealsByStage[stage.id] ?? []).length === 0 ? (
                  <p className="px-1 text-xs text-muted-foreground">{t('emptyColumn')}</p>
                ) : null}
                {(dealsByStage[stage.id] ?? []).map((deal) => (
                  <DealCard
                    key={deal.id}
                    deal={{ ...deal, name: deal.name ?? deal.title ?? `#${formatDigits(deal.id)}` }}
                    amountLabel={deal.amount != null && deal.amount !== '' ? formatNumber(Number(deal.amount)) : undefined}
                    onSave={async (payload) => {
                      try {
                        await saveDeal(payload, deal.id);
                        setSuccess(t('updated'));
                        void loadKanban();
                      } catch (err) {
                        applyAxiosError(err);
                      }
                    }}
                  />
                ))}
              </StageColumn>
            ))}
          </div>
        </DndContext>
      )}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newDeal')}</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <Input value={name} onChange={(e) => setName(e.target.value)} placeholder={t('name')} />
            <Select value={accountId} onValueChange={setAccountId}>
              <SelectTrigger><SelectValue placeholder={t('account')} /></SelectTrigger>
              <SelectContent>
                {accounts.map((a) => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {String(a.company_name ?? a.name ?? a.id)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter><Button onClick={() => void createDeal()} disabled={!name.trim() || !accountId}>{tNav('common.save')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={lossOpen} onOpenChange={setLossOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('lossReason')}</DialogTitle></DialogHeader>
          <Input value={lossReason} onChange={(e) => setLossReason(e.target.value)} placeholder={t('lossReason')} />
          <DialogFooter>
            <Button
              onClick={() => pendingMove && void commitMove(pendingMove.dealId, pendingMove.stageId, lossReason.trim())}
              disabled={!lossReason.trim()}
            >
              {tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      </div>
    </CrmPageLayout>
  );
}
