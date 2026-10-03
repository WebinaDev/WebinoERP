'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageEmptyState } from '@/features/shared/ui/PageStates';
import { getStaffDocuments, saveStaffDocument, signStaffDocument } from '@/lib/api/hrm';

const CATEGORIES = ['national_card', 'contract', 'decree', 'certificate', 'other'] as const;

export function DocumentsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [employeeId, setEmployeeId] = useState('');
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('contract');
  const [file, setFile] = useState<File | null>(null);
  const [rows, setRows] = useState<Array<{ id: number; title: string; category?: string; original_name?: string; document_status?: string; signer_name?: string }>>([]);
  const [signer, setSigner] = useState('');

  const load = useCallback(async () => {
    if (!employeeId) {
      setRows([]);
      return;
    }
    try {
      const res = await getStaffDocuments(employeeId);
      setRows(res?.documents ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, employeeId]);

  useEffect(() => {
    void load();
  }, [load]);

  const upload = async () => {
    if (!employeeId || !title.trim() || !file) return;
    const body = new FormData();
    body.append('title', title.trim());
    body.append('category', category);
    body.append('file', file);
    try {
      await saveStaffDocument(employeeId, body);
      setTitle('');
      setFile(null);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.documents')} {...layoutProps}>
      <Card>
        <CardContent className="space-y-4 pt-6 text-start">
          <p className="text-sm text-muted-foreground">{t('documents.hint')}</p>
          <div className="max-w-md space-y-1">
            <Label>{t('documents.pickEmployee')}</Label>
            <HrmEmployeeSelect value={employeeId} onChange={setEmployeeId} />
          </div>
          {!employeeId ? <PageEmptyState description={t('documents.pickFirst')} /> : (
            <>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label>{t('documents.title')}</Label>
                  <Input value={title} onChange={(e) => setTitle(e.target.value)} />
                </div>
                <div className="space-y-1">
                  <Label>{t('documents.category')}</Label>
                  <Select value={category} onValueChange={setCategory}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {CATEGORIES.map((code) => (
                        <SelectItem key={code} value={code}>{t(`wizard.docCategories.${code}`)}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label>{t('suite.sign.name')}</Label>
                  <Input value={signer} onChange={(e) => setSigner(e.target.value)} />
                </div>
                <div className="space-y-1">
                  <Label>{t('documents.file')}</Label>
                  <Input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                </div>
              </div>
              <Button onClick={() => void upload()} disabled={!file || !title.trim()}>{t('documents.upload')}</Button>
              <div className="space-y-2 text-sm">
                {rows.length === 0 ? <div className="text-muted-foreground">{tNav('common.empty')}</div> : rows.map((d) => (
                  <div key={d.id} className="flex flex-wrap items-center justify-between gap-2 rounded border px-3 py-2">
                    <span>{d.title} · {t.has(`wizard.docCategories.${d.category ?? ''}`) ? t(`wizard.docCategories.${d.category ?? 'other'}`) : d.category} · <HrmStatus value={d.document_status ?? 'pending_signature'} /></span>
                    <span className="text-muted-foreground">{d.signer_name || d.original_name}</span>
                    <Button size="sm" variant="outline" onClick={() => void signStaffDocument(employeeId, d.id, signer || t('documents.defaultSigner')).then(() => { setSuccess(t('suite.sign.done')); void load(); }).catch(applyAxiosError)}>{t('suite.sign.action')}</Button>
                  </div>
                ))}
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </HrmPageLayout>
  );
}
