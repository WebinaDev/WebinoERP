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
  createAiProduct,
  fetchIncompleteProducts,
  fillProductsBatch,
  generateAi,
} from '../lib/ai-content-api';

export function AiProductsPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<{ id: number; name: string; missing: string[] }[]>([]);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await fetchIncompleteProducts(80);
      setItems(res.items ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.products')} {...layoutProps}>
      <AiContentShell active="products">
        <div className="flex flex-wrap items-end gap-2">
          <Input
            className="max-w-xs"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder={t('field.name')}
          />
          <Button
            size="sm"
            disabled={!name.trim() || busy}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  await createAiProduct({ name: name.trim() });
                  setName('');
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
          <Button
            size="sm"
            variant="outline"
            disabled={busy}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  const res = await fillProductsBatch();
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
            {t('fillIncomplete')}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('incompleteTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {items.map((row) => (
              <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">{row.name}</div>
                  <div className="text-muted-foreground">{(row.missing ?? []).join(', ')}</div>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    void (async () => {
                      setBusy(true);
                      try {
                        const res = await generateAi({ type: 'product', id: row.id, sync: false });
                        if (!res?.job_id) {
                          applyAxiosError(new Error(t('jobQueueFailed')));
                          return;
                        }
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
                  {t('generate')}
                </Button>
              </div>
            ))}
            {!items.length ? <p className="text-sm text-muted-foreground">{t('noIncomplete')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
