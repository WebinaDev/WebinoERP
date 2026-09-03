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
  confirmAttrTemplate,
  deleteAttrTemplate,
  fetchAttrDraft,
  fetchAttrTemplates,
  suggestAttrTemplate,
} from '../lib/ai-content-api';

export function AiAttributesPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [catId, setCatId] = useState('1');
  const [templates, setTemplates] = useState<
    { id: number; product_cat_id: number; category_name: string; labels: { label: string }[] }[]
  >([]);
  const [draftAttrs, setDraftAttrs] = useState<{ label: string; slug: string; options: string[] }[]>([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await fetchAttrTemplates();
      setTemplates(res.items ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadDraft = async (id: number) => {
    setBusy(true);
    try {
      const res = await fetchAttrDraft(id);
      setDraftAttrs(res.draft?.attributes ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.attributes')} {...layoutProps}>
      <AiContentShell active="attributes">
        <p className="text-sm text-muted-foreground">{t('attrPageLead')}</p>
        <div className="flex flex-wrap items-end gap-2">
          <Input
            className="max-w-[10rem]"
            value={catId}
            onChange={(e) => setCatId(e.target.value)}
            placeholder="category id"
          />
          <Button
            size="sm"
            disabled={busy || !Number(catId)}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  await suggestAttrTemplate(Number(catId));
                  setSuccess(t('jobQueued'));
                  await loadDraft(Number(catId));
                  await load();
                } catch (err) {
                  applyAxiosError(err);
                } finally {
                  setBusy(false);
                }
              })()
            }
          >
            {t('attrSuggestTitle')}
          </Button>
          <Button
            size="sm"
            variant="outline"
            disabled={busy || !Number(catId) || !draftAttrs.length}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  await confirmAttrTemplate(Number(catId), { attributes: draftAttrs });
                  setSuccess(t('attrMapped'));
                  await load();
                } catch (err) {
                  applyAxiosError(err);
                } finally {
                  setBusy(false);
                }
              })()
            }
          >
            {t('confirmTemplate')}
          </Button>
        </div>

        {draftAttrs.length ? (
          <Card>
            <CardHeader>
              <CardTitle>{t('attrForCategory', { name: `#${catId}` })}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {draftAttrs.map((a, i) => (
                <div key={i}>
                  <span className="font-medium">{a.label}</span>
                  <span className="text-muted-foreground"> · {(a.options ?? []).join(', ')}</span>
                </div>
              ))}
            </CardContent>
          </Card>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle>{t('attrTemplates')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {templates.map((row) => (
              <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">
                    {row.category_name || `#${row.product_cat_id}`}
                  </div>
                  <div className="text-muted-foreground">
                    {(row.labels ?? []).map((l) => l.label).join(', ') || '—'}
                  </div>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    void (async () => {
                      setBusy(true);
                      try {
                        await deleteAttrTemplate(row.product_cat_id);
                        setSuccess(tNav('common.deleted'));
                        await load();
                      } catch (err) {
                        applyAxiosError(err);
                      } finally {
                        setBusy(false);
                      }
                    })()
                  }
                >
                  {tNav('common.delete')}
                </Button>
              </div>
            ))}
            {!templates.length ? <p className="text-sm text-muted-foreground">{t('noTemplates')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
