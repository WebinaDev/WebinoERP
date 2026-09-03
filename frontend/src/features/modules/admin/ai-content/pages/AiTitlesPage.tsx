'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { AiContentShell } from '../components/AiContentShell';
import {
  type AiProposal,
  applyAiProposal,
  enqueueAiProposals,
  fetchAiProposals,
  requeueAiProposal,
  skipAiProposal,
} from '../lib/ai-content-api';

export function AiTitlesPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<AiProposal[]>([]);
  const [edits, setEdits] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await fetchAiProposals('title', 'pending', 500);
      setItems(res.items ?? []);
      const next: Record<number, string> = {};
      for (const row of res.items ?? []) {
        next[row.id] = String((row.proposed as { name?: string }).name ?? '');
      }
      setEdits(next);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.titles')} {...layoutProps}>
      <AiContentShell active="titles">
        <Button
          size="sm"
          disabled={busy}
          onClick={() =>
            void (async () => {
              setBusy(true);
              try {
                const res = await enqueueAiProposals('title');
                setSuccess(t('batchQueued', { count: res.count }));
                await load();
              } catch (err) {
                applyAxiosError(err);
              } finally {
                setBusy(false);
              }
            })()
          }
        >
          {t('suggestTitles')}
        </Button>

        <Card>
          <CardHeader>
            <CardTitle>{t('titlesTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {items.map((row) => (
              <div key={row.id} className="space-y-2 border-b py-3 last:border-0">
                <div className="text-sm text-muted-foreground">{row.product_name}</div>
                <Input
                  value={edits[row.id] ?? ''}
                  onChange={(e) => setEdits((prev) => ({ ...prev, [row.id]: e.target.value }))}
                />
                <div className="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    disabled={busy}
                    onClick={() =>
                      void (async () => {
                        setBusy(true);
                        try {
                          await applyAiProposal(row.id, { name: edits[row.id] ?? '' });
                          setSuccess(t('proposalApplied'));
                          await load();
                        } catch (err) {
                          applyAxiosError(err);
                        } finally {
                          setBusy(false);
                        }
                      })()
                    }
                  >
                    {t('apply')}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() =>
                      void (async () => {
                        setBusy(true);
                        try {
                          await skipAiProposal(row.id);
                          await load();
                        } catch (err) {
                          applyAxiosError(err);
                        } finally {
                          setBusy(false);
                        }
                      })()
                    }
                  >
                    {t('skip')}
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={busy}
                    onClick={() =>
                      void (async () => {
                        setBusy(true);
                        try {
                          await requeueAiProposal('title', row.product_id);
                          setSuccess(t('jobQueued'));
                          await load();
                        } catch (err) {
                          applyAxiosError(err);
                        } finally {
                          setBusy(false);
                        }
                      })()
                    }
                  >
                    {t('retry')}
                  </Button>
                </div>
              </div>
            ))}
            {!items.length ? <p className="text-sm text-muted-foreground">{t('noProposals')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
