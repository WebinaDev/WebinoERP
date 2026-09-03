'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { attendanceCheckIn, attendanceCheckOut } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';
import { PageEmptyState } from '@/features/shared/ui/PageStates';

export function AttendancePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/hrm/attendance');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const checkIn = async () => {
    try {
      await attendanceCheckIn();
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const checkOut = async () => {
    try {
      await attendanceCheckOut();
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.hrm.attendance')}
      actions={<><Button onClick={() => void checkIn()}>{t('checkIn')}</Button><Button variant="outline" onClick={() => void checkOut()}>{t('checkOut')}</Button></>}
      {...layoutProps}
    >
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>{t('employee')}</TableHead><TableHead>{t('attendanceDate')}</TableHead><TableHead>{t('checkInTime')}</TableHead><TableHead>{t('checkOutTime')}</TableHead></TableRow></TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4}>
                    <PageEmptyState description={tNav('common.noData')} />
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.employee_id ?? '')}</TableCell>
                    <TableCell>{String(r.date ?? '')}</TableCell>
                    <TableCell>{String(r.check_in_at ?? '')}</TableCell>
                    <TableCell>{String(r.check_out_at ?? '')}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
