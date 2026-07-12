'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { approveLeaveRequest, getLeaveRequests, rejectLeaveRequest } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

export function LeavePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await getLeaveRequests();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.leave')} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>{t('employee')}</TableHead><TableHead>{t('leaveType')}</TableHead><TableHead>{t('status')}</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.employee_id ?? '')}</TableCell>
                  <TableCell>{String(r.leave_type_id ?? '')}</TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell className="flex gap-2">
                    <Button size="sm" onClick={() => void approveLeaveRequest(r.id as number).then(() => { setSuccess(tNav('common.saved')); void load(); })}>{t('approve')}</Button>
                    <Button size="sm" variant="outline" onClick={() => void rejectLeaveRequest(r.id as number).then(() => void load())}>{t('reject')}</Button>
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
