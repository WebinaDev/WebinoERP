'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { useLocale } from '@/hooks/use-locale-next';
import { attendanceCheckIn, attendanceCheckOut } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';
import { PageEmptyState } from '@/features/shared/ui/PageStates';

function clock(value: unknown): string {
  const raw = String(value ?? '');
  if (!raw) return '—';
  const match = raw.match(/(\d{2}:\d{2})/);
  return match ? match[1] : raw;
}

export function AttendancePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [employeeId, setEmployeeId] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/hrm/attendance', { params: { per_page: 100 } });
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const punch = async (kind: 'in' | 'out') => {
    if (!employeeId) return;
    try {
      if (kind === 'in') await attendanceCheckIn({ employee_id: Number(employeeId) });
      else await attendanceCheckOut({ employee_id: Number(employeeId) });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.attendance')} {...layoutProps}>
      <Card className="mb-4">
        <CardContent className="space-y-3 pt-6 text-start">
          <Label>{t('attendance.pickEmployee')}</Label>
          <HrmEmployeeSelect value={employeeId} onChange={setEmployeeId} />
          <div className="flex flex-wrap gap-2">
            <Button disabled={!employeeId} onClick={() => void punch('in')}>{t('checkIn')}</Button>
            <Button variant="outline" disabled={!employeeId} onClick={() => void punch('out')}>{t('checkOut')}</Button>
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('employee')}</TableHead>
                <TableHead>{t('attendanceDate')}</TableHead>
                <TableHead>{t('checkInTime')}</TableHead>
                <TableHead>{t('checkOutTime')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow><TableCell colSpan={4}><PageEmptyState description={tNav('common.noData')} /></TableCell></TableRow>
              ) : rows.map((r) => {
                const emp = r.employee as { first_name?: string; last_name?: string } | undefined;
                const name = emp ? `${emp.first_name ?? ''} ${emp.last_name ?? ''}`.trim() : String(r.employee_id ?? '');
                return (
                  <TableRow key={String(r.id)}>
                    <TableCell>{name}</TableCell>
                    <TableCell>{r.date ? formatDate(String(r.date)) || String(r.date) : '—'}</TableCell>
                    <TableCell>{clock(r.check_in ?? r.check_in_at)}</TableCell>
                    <TableCell>{clock(r.check_out ?? r.check_out_at)}</TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </HrmPageLayout>
  );
}
