'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { AiContentShell } from '../components/AiContentShell';
import {
  type AiPageRow,
  createAiPage,
  fetchAiPages,
  generateAi,
  updateAiPage,
} from '../lib/ai-content-api';

export function AiPagesPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [search, setSearch] = useState('');
  const [items, setItems] = useState<AiPageRow[]>([]);
  const [prompts, setPrompts] = useState<Record<number, string>>({});
  const [newTitle, setNewTitle] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await fetchAiPages(1, search);
      setItems(res.items ?? []);
      const next: Record<number, string> = {};
      for (const row of res.items ?? []) next[row.id] = row.page_prompt || '';
      setPrompts(next);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, search]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.pages')} {...layoutProps}>
      <AiContentShell active="pages">
        <div>
          <p className="text-sm text-muted-foreground">{t('pagesLead')}</p>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <Input
            className="max-w-sm"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('pagesSearch')}
          />
          <Input
            className="max-w-sm"
            value={newTitle}
            onChange={(e) => setNewTitle(e.target.value)}
            placeholder={t('field.title')}
          />
          <Button
            size="sm"
            disabled={!newTitle.trim() || busy}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  await createAiPage({ title: newTitle.trim() });
                  setNewTitle('');
                  setSuccess(tNav('common.saved'));
                  await load();
                } catch (err) {
                  applyAxiosError(err);
                } finally {
                  setBusy(false);
                }
              })()
            }
          >
            {tNav('common.add')}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('pagesList')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {items.map((row) => (
              <div key={row.id} className="space-y-2 border-b py-3 last:border-0">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <div className="font-medium">{row.title || `#${row.id}`}</div>
                    <div className="text-xs text-muted-foreground">{row.status}</div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={busy}
                      onClick={() =>
                        void (async () => {
                          setBusy(true);
                          try {
                            await updateAiPage(row.id, { page_prompt: prompts[row.id] ?? '' });
                            setSuccess(tNav('common.saved'));
                          } catch (err) {
                            applyAxiosError(err);
                          } finally {
                            setBusy(false);
                          }
                        })()
                      }
                    >
                      {tNav('common.save')}
                    </Button>
                    <Button
                      size="sm"
                      disabled={busy}
                      onClick={() =>
                        void (async () => {
                          setBusy(true);
                          try {
                            const res = await generateAi({
                              type: 'page',
                              id: row.id,
                              page_prompt: prompts[row.id] ?? '',
                              run_now: false,
                            });
                            if (!res?.job_id) {
                              applyAxiosError(new Error(t('jobQueueFailed')));
                              return;
                            }
                            setSuccess(t('jobQueued'));
                          } catch (err) {
                            applyAxiosError(err);
                          } finally {
                            setBusy(false);
                          }
                        })()
                      }
                    >
                      {t('generate')}
                    </Button>
                  </div>
                </div>
                <Textarea
                  rows={3}
                  value={prompts[row.id] ?? ''}
                  onChange={(e) => setPrompts((p) => ({ ...p, [row.id]: e.target.value }))}
                  placeholder={t('pagePrompt')}
                />
              </div>
            ))}
            {!items.length ? <p className="text-sm text-muted-foreground">{t('noPages')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
