'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';

type Row = { id: number; action: string; user_id?: number | null; created_at?: string; subject_type?: string };

export function AuditLogPage({ module }: { module: 'crm' | 'projects' }) {
  const t = useTranslations('suite');
  const { formatDateTime, formatNumber, isRtl } = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Row[]>([]);

  useEffect(() => {
    const path = module === 'crm' ? '/v1/crm/audit' : '/v1/projects/audit';
    void apiClient.get(path).then((res) => setRows(unwrapData<Row[]>(res) ?? [])).catch(applyAxiosError);
  }, [module, applyAxiosError]);

  return (
    <CrmPageLayout title={t('auditTitle')} description={t('auditHint')} {...layoutProps}>
      {rows.length === 0 ? <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{t('empty')}</p> : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="px-3 py-2 text-start">{t('when')}</th>
                <th className="px-3 py-2 text-start">{t('action')}</th>
                <th className="px-3 py-2 text-start">{t('actor')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-border/60">
                  <td className="px-3 py-2">{row.created_at ? formatDateTime(row.created_at) : ''}</td>
                  <td className="px-3 py-2">{row.action}</td>
                  <td className="px-3 py-2">{row.user_id ? formatNumber(row.user_id) : ''}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </CrmPageLayout>
  );
}
