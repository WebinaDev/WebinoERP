'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale-next';
import { HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { approvePayrollRun, calculatePayrollRun, downloadHrmFile, getPayrollPayslips, getPayrollRun } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';

type Props = { id: string };

export function PayrollRunDetailPage({ id }: Props) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { formatNumber } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [run, setRun] = useState<Record<string, unknown>>({});
  const [payslips, setPayslips] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const r = await getPayrollRun(id);
      setRun(r as Record<string, unknown>);
      const p = await getPayrollPayslips(id);
      setPayslips((p as { data?: Record<string, unknown>[] })?.data ?? (Array.isArray(p) ? p : []));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [id, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const status = String(run.status ?? 'draft');

  return (
    <HrmPageLayout
      title={t('payrollRun')}
      actions={
        <>
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'hrm/payroll')}>{t('backToList')}</Link>
          </Button>
          {status === 'draft' || status === 'calculated' ? (
            <Button onClick={() => void calculatePayrollRun(id).then(() => { setSuccess(tNav('common.saved')); void load(); })}>{t('payrollCalculate')}</Button>
          ) : null}
          {status === 'calculated' ? (
            <Button variant="outline" onClick={() => void approvePayrollRun(id).then(() => void load())}>{t('payrollApprove')}</Button>
          ) : null}
          <Button variant="outline" onClick={() => void downloadHrmFile(`payroll/runs/${id}/bank-export?channel=paya&format=csv`, `paya-${id}.csv`)}>{t('suite.bank.paya')}</Button>
          <Button variant="outline" onClick={() => void downloadHrmFile(`payroll/runs/${id}/bank-export?channel=satna&format=csv`, `satna-${id}.csv`)}>{t('suite.bank.satna')}</Button>
          <Button variant="outline" onClick={() => void downloadHrmFile(`payroll/runs/${id}/insurance-list?format=csv`, `sso-${id}.csv`)}>{t('suite.bank.ssoCsv')}</Button>
          <Button variant="outline" onClick={() => void downloadHrmFile(`payroll/runs/${id}/insurance-list?format=txt`, `DSKWOR-${id}.txt`)}>{t('suite.bank.ssoTxt')}</Button>
          <Button variant="outline" onClick={() => void downloadHrmFile(`payroll/runs/${id}/insurance-list?format=zip`, `sso-diskette-${id}.zip`)}>{t('suite.bank.ssoZip')}</Button>
        </>
      }
      {...layoutProps}
    >
      <Card>
        <CardHeader><CardTitle className="text-base">{String(run.period ?? run.name ?? id)}</CardTitle></CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm">
          <span>{t('status')}: <Badge variant="secondary"><HrmStatus value={status} /></Badge></span>
          <span>{t('gross')}: {run.total_gross != null ? formatNumber(Number(run.total_gross)) : '—'}</span>
          <span>{t('net')}: {run.total_net != null ? formatNumber(Number(run.total_net)) : '—'}</span>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('payslips')}</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('employee')}</TableHead>
                <TableHead>{t('gross')}</TableHead>
                <TableHead>{t('net')}</TableHead>
                <TableHead>{t('status')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {payslips.map((p) => (
                <TableRow key={String(p.id)}>
                  <TableCell>{String(p.employee_name ?? p.employee_id ?? '')}</TableCell>
                  <TableCell>{p.gross != null && p.gross !== '' ? formatNumber(Number(p.gross)) : '—'}</TableCell>
                  <TableCell>{p.net != null && p.net !== '' ? formatNumber(Number(p.net)) : '—'}</TableCell>
                  <TableCell><HrmStatus value={p.status} /></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </HrmPageLayout>
  );
}
