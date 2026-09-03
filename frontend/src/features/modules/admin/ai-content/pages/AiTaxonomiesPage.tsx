'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AiContentShell } from '../components/AiContentShell';
import {
  type AiProposal,
  applyAiProposal,
  applyCategorySuggestions,
  enqueueAiProposals,
  fetchAiProposals,
  fillTermsBatch,
  getCategorySuggestions,
  skipAiProposal,
  suggestCategories,
} from '../lib/ai-content-api';

export function AiTaxonomiesPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [kind, setKind] = useState<'blog' | 'product'>('blog');
  const [suggestions, setSuggestions] = useState<{ categories?: { name?: string }[] } | null>(null);
  const [catalog, setCatalog] = useState<AiProposal[]>([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [sug, cat] = await Promise.all([
        getCategorySuggestions(kind),
        fetchAiProposals('catalog', 'pending', 200),
      ]);
      setSuggestions(sug.suggestions ?? null);
      setCatalog(cat.items ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, kind]);

  useEffect(() => {
    void load();
  }, [load]);

  const run = async (fn: () => Promise<void>) => {
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

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.taxonomies')} {...layoutProps}>
      <AiContentShell active="taxonomies">
        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant={kind === 'blog' ? 'default' : 'outline'} onClick={() => setKind('blog')}>
            {t('typeBlog')}
          </Button>
          <Button size="sm" variant={kind === 'product' ? 'default' : 'outline'} onClick={() => setKind('product')}>
            {t('typeProduct')}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('suggestCategories')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex flex-wrap gap-2">
              <Button
                size="sm"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    await suggestCategories(kind);
                    setSuccess(t('jobQueued'));
                  })
                }
              >
                {t('suggest')}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    const res = await applyCategorySuggestions(kind);
                    setSuccess(t('catsApplied', { count: res.count }));
                  })
                }
              >
                {t('applySuggestions')}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    const res = await fillTermsBatch('product_cat');
                    setSuccess(t('batchQueued', { count: res.count }));
                  })
                }
              >
                {t('fillCats')}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={busy}
                onClick={() =>
                  void run(async () => {
                    const res = await fillTermsBatch('product_brand');
                    setSuccess(t('batchQueued', { count: res.count }));
                  })
                }
              >
                {t('fillBrands')}
              </Button>
            </div>
            <ul className="list-inside list-disc text-sm">
              {(suggestions?.categories ?? []).map((c, i) => (
                <li key={i}>{c.name ?? JSON.stringify(c)}</li>
              ))}
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle>{t('catalogAssignTitle')}</CardTitle>
            <Button
              size="sm"
              disabled={busy}
              onClick={() =>
                void run(async () => {
                  const res = await enqueueAiProposals('catalog');
                  setSuccess(t('batchQueued', { count: res.count }));
                })
              }
            >
              {t('catalogSuggestProducts')}
            </Button>
          </CardHeader>
          <CardContent className="space-y-2">
            {catalog.map((row) => (
              <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">{row.product_name}</div>
                  <div className="text-muted-foreground">{JSON.stringify(row.proposed)}</div>
                </div>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    disabled={busy}
                    onClick={() =>
                      void run(async () => {
                        await applyAiProposal(row.id);
                        setSuccess(t('proposalApplied'));
                      })
                    }
                  >
                    {t('apply')}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() => void run(async () => { await skipAiProposal(row.id); })}
                  >
                    {t('skip')}
                  </Button>
                </div>
              </div>
            ))}
            {!catalog.length ? <p className="text-sm text-muted-foreground">{t('noProposals')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
