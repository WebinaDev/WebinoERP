'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { completeAudit, createAudit, postAudit } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';

export function AuditPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/scm/audit');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.scm.audit')} actions={<Button onClick={() => void createAudit({ warehouse_id: 1 }).then(() => void load())}>{t('createAudit')}</Button>} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>ID</TableHead><TableHead>Warehouse</TableHead><TableHead>Status</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.id ?? '')}</TableCell>
                  <TableCell>{String(r.warehouse_id ?? '')}</TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={() => void completeAudit(r.id as number).then(load)}>{t('completeAudit')}</Button>
                    <Button size="sm" onClick={() => void postAudit(r.id as number).then(() => { setSuccess(t('postAudit')); void load(); })}>{t('postAudit')}</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
