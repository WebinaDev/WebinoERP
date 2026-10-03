'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { getStaffDocuments, saveStaffDocument } from '@/lib/api/hrm';

export function DocumentsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [employeeId, setEmployeeId] = useState('');
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('contract');
  const [file, setFile] = useState<File | null>(null);
  const [rows, setRows] = useState<Array<{ id: number; title: string; category?: string; original_name?: string }>>([]);

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
    <CrmPageLayout title={tNav('nav.erp.hrm.documents')} {...layoutProps}>
      <Card>
        <CardContent className="space-y-3 pt-6 text-start">
          <p className="text-sm text-muted-foreground">{t('documents.hint')}</p>
          <div className="flex flex-wrap gap-2">
            <Input className="max-w-[10rem]" placeholder={t('loans.employeeId')} value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} />
            <Input className="max-w-[14rem]" placeholder={t('documents.title')} value={title} onChange={(e) => setTitle(e.target.value)} />
            <Input className="max-w-[10rem]" placeholder={t('documents.category')} value={category} onChange={(e) => setCategory(e.target.value)} />
            <Input type="file" className="max-w-[16rem]" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            <Button onClick={() => void upload()} disabled={!file || !title.trim() || !employeeId}>{t('documents.upload')}</Button>
          </div>
          <div className="space-y-2 text-sm">
            {rows.length === 0 ? <div className="text-muted-foreground">{tNav('common.empty')}</div> : rows.map((d) => (
              <div key={d.id} className="flex justify-between rounded border px-3 py-2">
                <span>{d.title} · {d.category}</span>
                <span className="text-muted-foreground">{d.original_name}</span>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
