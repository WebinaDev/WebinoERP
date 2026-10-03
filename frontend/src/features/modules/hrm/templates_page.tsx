'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getDocumentTemplates, renderDocumentTemplate, saveDocumentTemplate } from '@/lib/api/hrm';

type TemplateRow = { id: number; slug: string; name: string; html?: string; is_active?: boolean };

export function TemplatesPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<TemplateRow[]>([]);
  const [slug, setSlug] = useState('payslip');
  const [name, setName] = useState('');
  const [html, setHtml] = useState('');
  const [preview, setPreview] = useState('');
  const [recordId, setRecordId] = useState('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getDocumentTemplates();
      const list = (res as { data?: TemplateRow[] })?.data ?? (Array.isArray(res) ? (res as TemplateRow[]) : []);
      setRows(list);
      const current = list.find((r) => r.slug === slug) ?? list[0];
      if (current) {
        setSlug(current.slug);
        setName(current.name);
        if (current.html) setHtml(current.html);
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, slug]);

  useEffect(() => {
    void load();
    // initial only
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const save = async () => {
    try {
      await saveDocumentTemplate({ slug, name: name || slug, html, is_active: true });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const renderPreview = async () => {
    if (!recordId) return;
    try {
      const res = await renderDocumentTemplate(
        slug,
        slug === 'decree' ? { decree_id: Number(recordId) } : { payroll_item_id: Number(recordId) },
      );
      setPreview(res?.html ?? '');
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.templates')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="grid gap-4 text-start lg:grid-cols-2">
          <Card>
            <CardContent className="space-y-3 pt-6">
              <p className="text-sm text-muted-foreground">{t('templates.hint')}</p>
              <div className="space-y-1">
                <Label>{t('templates.slug')}</Label>
                <Select value={slug} onValueChange={(v) => {
                  setSlug(v);
                  const row = rows.find((r) => r.slug === v);
                  if (row) {
                    setName(row.name);
                    setHtml(row.html ?? '');
                  }
                }}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="payslip">{t('templates.payslip')}</SelectItem>
                    <SelectItem value="decree">{t('templates.decree')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder={t('templates.name')} />
              <Textarea className="min-h-64 font-mono text-xs" value={html} onChange={(e) => setHtml(e.target.value)} placeholder={t('templates.htmlPlaceholder')} />
              <Button onClick={() => void save()} disabled={!html.trim()}>{tNav('common.save')}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="space-y-3 pt-6">
              <Label>{slug === 'decree' ? t('templates.decreeId') : t('templates.payslipId')}</Label>
              <div className="flex gap-2">
                <Input value={recordId} onChange={(e) => setRecordId(e.target.value)} />
                <Button variant="outline" onClick={() => void renderPreview()}>{t('templates.preview')}</Button>
              </div>
              {preview ? (
                <div className="rounded border bg-white p-3 text-black" dangerouslySetInnerHTML={{ __html: preview }} />
              ) : (
                <p className="text-sm text-muted-foreground">{t('templates.placeholders')}</p>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
