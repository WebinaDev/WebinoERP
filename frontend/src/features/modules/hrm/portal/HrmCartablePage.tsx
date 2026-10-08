'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { useLocale } from '@/hooks/use-locale-next';
import {
  approveHrmRequest,
  approveLeaveFromCartable,
  getApprovalInbox,
  rejectHrmRequest,
  rejectLeaveFromCartable,
} from '@/lib/api/hrm';

type Item = Record<string, unknown> & { id: number; kind: 'request' | 'leave' };

function PayloadSummary({ payload }: { payload: unknown }) {
  const t = useTranslations('hrm');
  const { formatNumber, formatDate } = useLocale();
  if (!payload || typeof payload !== 'object') return null;
  const p = payload as Record<string, unknown>;
  const parts: string[] = [];
  if (p.hours != null) parts.push(`${t('requests.hours')}: ${formatNumber(Number(p.hours))}`);
  if (p.days != null) parts.push(`${t('requests.days')}: ${formatNumber(Number(p.days))}`);
  if (p.amount != null) parts.push(`${t('requests.amount')}: ${formatNumber(Number(p.amount))}`);
  if (typeof p.date === 'string') parts.push(formatDate(p.date));
  if (typeof p.start_date === 'string') parts.push(`${t('dateFrom')}: ${formatDate(p.start_date)}`);
  if (typeof p.end_date === 'string') parts.push(`${t('dateTo')}: ${formatDate(p.end_date)}`);
  if (parts.length === 0) return null;
  return <div className="text-xs text-muted-foreground">{parts.join(' · ')}</div>;
}

/** Approval inbox: HR cartable (manager menu) or "my approvals" for designated approvers (portal). */
export function HrmCartablePage({ mode = 'hr' }: { mode?: 'hr' | 'portal' }) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate, formatDateTime } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<Item[]>([]);
  const [loading, setLoading] = useState(true);
  const [reasons, setReasons] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getApprovalInbox();
      const requests = (res?.requests ?? []).map((r): Item => ({ ...r, kind: 'request', id: Number(r.id) }));
      const leaves = (res?.leaves ?? []).map((r): Item => ({ ...r, kind: 'leave', id: Number(r.id) }));
      setItems([...leaves, ...requests].sort((a, b) => String(b.created_at ?? '').localeCompare(String(a.created_at ?? ''))));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const decide = async (item: Item, approve: boolean) => {
    try {
      if (item.kind === 'leave') {
        if (approve) await approveLeaveFromCartable(item.id);
        else await rejectLeaveFromCartable(item.id, reasons[`leave-${item.id}`] || undefined);
      } else if (approve) {
        await approveHrmRequest(item.id);
      } else {
        await rejectHrmRequest(item.id);
      }
      setSuccess(approve ? t('cartable.approved') : t('cartable.rejected'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const typeLabel = (item: Item) => {
    const type = String(item.type ?? '');
    return t.has(`cartable.types.${type}`) ? t(`cartable.types.${type}`) : type;
  };
  const leaveTypeLabel = (code: unknown) => {
    const c = String(code ?? '');
    return t.has(`cartable.leaveTypes.${c}`) ? t(`cartable.leaveTypes.${c}`) : c;
  };

  return (
    <HrmPageLayout
      title={tNav(mode === 'portal' ? 'nav.erp.hrm.myApprovals' : 'nav.erp.hrm.cartable')}
      description={t('cartable.hint')}
      {...layoutProps}
    >
      {loading ? (
        <PageLoadingState />
      ) : items.length === 0 ? (
        <PageEmptyState description={t('cartable.empty')} />
      ) : (
        <div className="space-y-3 text-start">
          {items.map((r) => {
            const key = `${r.kind}-${r.id}`;
            return (
              <Card key={key}>
                <CardContent className="space-y-2 pt-6 text-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="flex flex-wrap items-center gap-2 font-medium">
                      <Badge variant="outline">{typeLabel(r)}</Badge>
                      {String(r.employee_name || r.user_name || '')}
                    </span>
                    <span className="text-muted-foreground">
                      <HrmStatus value={r.status} />
                      {r.approval_step ? <> · {t('cartable.step')} <HrmDigits value={r.approval_step} /></> : null}
                    </span>
                  </div>
                  {r.kind === 'leave' ? (
                    <div className="text-xs text-muted-foreground">
                      {leaveTypeLabel(r.leave_type)} · {t('dateFrom')}: {formatDate(String(r.start_date ?? ''))} · {t('dateTo')}: {formatDate(String(r.end_date ?? ''))}
                    </div>
                  ) : (
                    <PayloadSummary payload={r.payload} />
                  )}
                  {r.reason || r.notes ? <div className="text-xs">{String(r.reason ?? r.notes)}</div> : null}
                  {r.created_at ? <div className="text-xs text-muted-foreground">{t('cartable.submittedAt')}: {formatDateTime(String(r.created_at))}</div> : null}
                  {r.kind === 'leave' ? (
                    <Textarea
                      rows={2}
                      placeholder={t('cartable.rejectReason')}
                      value={reasons[key] ?? ''}
                      onChange={(e) => setReasons((s) => ({ ...s, [key]: e.target.value }))}
                    />
                  ) : null}
                  <div className="flex gap-2">
                    <Button size="sm" onClick={() => void decide(r, true)}>{t('approve')}</Button>
                    <Button size="sm" variant="outline" onClick={() => void decide(r, false)}>{t('reject')}</Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </HrmPageLayout>
  );
}
