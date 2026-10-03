'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getDocumentTemplates, getPayrollDecrees, getPayrollPayslips, getPayrollRuns, renderDocumentTemplate, saveDocumentTemplate } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

type TemplateRow = { id: number; slug: string; name: string; html?: string; is_active?: boolean };

const PAYSLIP_VARS = ['employee_name', 'personnel_code', 'period', 'base', 'gross', 'net', 'tax', 'employee_insurance', 'law_year_label'] as const;
const DECREE_VARS = ['decree_no', 'employee_name', 'personnel_code', 'job_title', 'department', 'contract_type', 'engagement_type', 'pay_basis', 'effective_from', 'effective_to', 'base_salary', 'benefits_rows'] as const;

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
  const [runs, setRuns] = useState<Record<string, unknown>[]>([]);
  const [runId, setRunId] = useState('');
  const [items, setItems] = useState<Record<string, unknown>[]>([]);
  const [decrees, setDecrees] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [res, runRes, decreeRes] = await Promise.all([
        getDocumentTemplates(),
        getPayrollRuns({ per_page: 50 }),
        getPayrollDecrees(),
      ]);
      const list = (res as { data?: TemplateRow[] })?.data ?? (Array.isArray(res) ? (res as TemplateRow[]) : []);
      setRows(list);
      setRuns(normalizeListPayload(runRes));
      const decreePayload = decreeRes as { decrees?: Record<string, unknown>[] };
      setDecrees(decreePayload.decrees ?? []);
      const current = list.find((r) => r.slug === 'payslip') ?? list[0];
      if (current) {
        setSlug(current.slug);
        setName(current.name);
        setHtml(current.html ?? '');
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => { void load(); }, [load]);

  useEffect(() => {
    if (!runId) {
      setItems([]);
      return;
    }
    void getPayrollPayslips(runId)
      .then((res) => setItems(normalizeListPayload(res)))
      .catch(() => setItems([]));
  }, [runId]);

  const vars = slug === 'decree' ? DECREE_VARS : PAYSLIP_VARS;
  const active = useMemo(() => rows.find((r) => r.slug === slug), [rows, slug]);

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

  const insertVar = (key: string) => setHtml((value) => `${value}{{${key}}}`);

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.templates')} {...layoutProps}>
      {loading ? <PageLoadingState /> : (
        <div className="grid gap-4 text-start lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('templates.editor')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <p className="text-sm text-muted-foreground">{t('templates.hint')}</p>
              <div className="space-y-1">
                <Label>{t('templates.slug')}</Label>
                <Select value={slug} onValueChange={(v) => {
                  setSlug(v);
                  setRecordId('');
                  setPreview('');
                  const row = rows.find((r) => r.slug === v);
                  setName(row?.name ?? '');
                  setHtml(row?.html ?? '');
                }}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="payslip">{t('templates.payslip')}</SelectItem>
                    <SelectItem value="decree">{t('templates.decree')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label>{t('templates.name')}</Label>
                <Input value={name} onChange={(e) => setName(e.target.value)} />
              </div>
              {active ? <div className="text-xs text-muted-foreground"><HrmStatus value={active.is_active === false ? 'inactive' : 'active'} /></div> : null}
              <div className="flex flex-wrap gap-1">
                {vars.map((key) => (
                  <Button key={key} type="button" size="sm" variant="outline" onClick={() => insertVar(key)}>
                    {t(`templates.vars.${key}`)}
                  </Button>
                ))}
              </div>
              <Textarea dir="ltr" className="min-h-64 text-left font-mono text-xs" value={html} onChange={(e) => setHtml(e.target.value)} placeholder={t('templates.htmlPlaceholder')} />
              <Button onClick={() => void save()} disabled={!html.trim()}>{tNav('common.save')}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t('templates.preview')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {slug === 'decree' ? (
                <div className="space-y-1">
                  <Label>{t('templates.pickDecree')}</Label>
                  <Select value={recordId || undefined} onValueChange={setRecordId}>
                    <SelectTrigger><SelectValue placeholder={t('templates.pickDecree')} /></SelectTrigger>
                    <SelectContent>
                      {decrees.map((d) => (
                        <SelectItem key={String(d.id)} value={String(d.id)}>
                          {String(d.decree_no ?? d.id)} · {String(d.job_title ?? '')}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              ) : (
                <>
                  <div className="space-y-1">
                    <Label>{t('payrollRun')}</Label>
                    <Select value={runId || undefined} onValueChange={setRunId}>
                      <SelectTrigger><SelectValue placeholder={t('payrollRun')} /></SelectTrigger>
                      <SelectContent>
                        {runs.map((r) => (
                          <SelectItem key={String(r.id)} value={String(r.id)}>{String(r.title ?? r.id)}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-1">
                    <Label>{t('templates.pickPayslip')}</Label>
                    <Select value={recordId || undefined} onValueChange={setRecordId}>
                      <SelectTrigger><SelectValue placeholder={t('templates.pickPayslip')} /></SelectTrigger>
                      <SelectContent>
                        {items.map((item) => {
                          const emp = item.employee as { first_name?: string; last_name?: string } | undefined;
                          const nameLabel = emp ? `${emp.first_name ?? ''} ${emp.last_name ?? ''}`.trim() : String(item.employee_id ?? item.id);
                          return <SelectItem key={String(item.id)} value={String(item.id)}>{nameLabel}</SelectItem>;
                        })}
                      </SelectContent>
                    </Select>
                  </div>
                </>
              )}
              <Button variant="outline" onClick={() => void renderPreview()} disabled={!recordId}>{t('templates.preview')}</Button>
              {preview ? (
                <div className="rounded border bg-white p-3 text-black" dangerouslySetInnerHTML={{ __html: preview }} />
              ) : (
                <p className="text-sm text-muted-foreground">{t('templates.placeholders')}</p>
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </HrmPageLayout>
  );
}
