'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyDecrees, getMyDocuments, getMyNotices, saveMyDocument } from '@/lib/api/hrm';

export function MyDocsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [decrees, setDecrees] = useState<
    Array<{ id: number; decree_no?: string; status?: string; effective_from?: string }>
  >([]);
  const [notices, setNotices] = useState<Array<{ id: number; title: string; body?: string }>>([]);
  const [docs, setDocs] = useState<Array<{ id: number; title: string; category?: string }>>([]);
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('national_card');
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [dRes, nRes, docRes] = await Promise.all([getMyDecrees(), getMyNotices(), getMyDocuments()]);
      setDecrees(dRes?.decrees ?? []);
      setNotices(nRes?.notices ?? []);
      setDocs(docRes?.documents ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myDocs')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.decrees')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {decrees.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                decrees.map((d) => (
                  <div key={d.id} className="flex justify-between rounded border px-3 py-2">
                    <span>
                      {d.decree_no || `#${d.id}`} · {d.effective_from || '—'}
                    </span>
                    <span className="text-muted-foreground">{d.status}</span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('documents.mine')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              {docs.length === 0 ? <div className="text-muted-foreground">{tNav('common.empty')}</div> : docs.map((d) => (
                <div key={d.id} className="rounded border px-3 py-2">{d.title} · {d.category}</div>
              ))}
              <div className="flex flex-wrap gap-2">
                <Input className="max-w-[12rem]" placeholder={t('documents.title')} value={title} onChange={(e) => setTitle(e.target.value)} />
                <Input className="max-w-[10rem]" placeholder={t('documents.category')} value={category} onChange={(e) => setCategory(e.target.value)} />
                <Input type="file" className="max-w-[14rem]" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                <Button disabled={!file || !title.trim()} onClick={() => void (async () => {
                  if (!file) return;
                  const body = new FormData();
                  body.append('title', title.trim());
                  body.append('category', category);
                  body.append('file', file);
                  try {
                    await saveMyDocument(body);
                    setTitle('');
                    setFile(null);
                    setSuccess(tNav('common.saved'));
                    void load();
                  } catch (err) {
                    applyAxiosError(err);
                  }
                })()}>{t('documents.upload')}</Button>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.notices')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              {notices.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                notices.map((n) => (
                  <div key={n.id} className="rounded border p-3">
                    <div className="font-medium">{n.title}</div>
                    {n.body ? <div className="text-muted-foreground whitespace-pre-wrap">{n.body}</div> : null}
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
