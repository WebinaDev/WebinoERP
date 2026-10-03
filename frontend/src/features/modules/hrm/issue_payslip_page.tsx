'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { PayrollPeriodFields, usePayrollPeriod } from '@/features/modules/hrm/jalali_period_fields';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { issueEmployeePayslip } from '@/lib/api/hrm';
import { useLocale } from '@/hooks/use-locale-next';

export function IssuePayslipPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatNumber } = useLocale();
  const initial = usePayrollPeriod();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [employeeId, setEmployeeId] = useState('');
  const [year, setYear] = useState(initial.year);
  const [month, setMonth] = useState(initial.month);
  const [html, setHtml] = useState('');
  const [summary, setSummary] = useState<Record<string, unknown> | null>(null);
  const [busy, setBusy] = useState(false);

  const issue = async () => {
    if (!employeeId) return;
    setBusy(true);
    try {
      const res = await issueEmployeePayslip({
        employee_id: Number(employeeId),
        year: Number(year),
        month: Number(month),
      });
      const data = (res as { data?: { html?: string; item?: Record<string, unknown> } })?.data ?? (res as { html?: string; item?: Record<string, unknown> });
      setHtml(data?.html ?? '');
      setSummary((data?.item as Record<string, unknown>) ?? null);
      setSuccess(t('payslip.issued'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  const breakdown = (summary?.breakdown ?? {}) as Record<string, unknown>;
  const lines = (breakdown.lines ?? {}) as Record<string, number>;

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.issuePayslip')} {...layoutProps}>
      <div className="grid gap-4 text-start lg:grid-cols-2">
        <Card>
          <CardContent className="space-y-3 pt-6">
            <p className="text-sm text-muted-foreground">{t('payslip.hint')}</p>
            <div className="space-y-1">
              <Label>{t('employee')}</Label>
              <HrmEmployeeSelect value={employeeId} onChange={setEmployeeId} />
            </div>
            <PayrollPeriodFields
              year={year}
              month={month}
              onYear={setYear}
              onMonth={setMonth}
              onPickDate={(_iso, nextYear, nextMonth) => {
                setYear(nextYear);
                setMonth(nextMonth);
              }}
            />
            <Button onClick={() => void issue()} disabled={busy || !employeeId}>{t('payslip.issue')}</Button>
            {summary ? (
              <div className="space-y-1 text-sm">
                <div>{t('gross')}: {formatNumber(Number(summary.gross ?? 0))}</div>
                <div>{t('net')}: {formatNumber(Number(summary.net ?? 0))}</div>
                <div>{t('settings.employeeInsurance')}: {formatNumber(Number(breakdown.employee_insurance ?? 0))}</div>
                <div>{t('payslip.tax')}: {formatNumber(Number(breakdown.tax ?? 0))}</div>
                <div>{t('payslip.employerInsurance')}: {formatNumber(Number(breakdown.employer_insurance ?? 0))}</div>
                {Object.entries(lines).map(([k, v]) => (
                  <div key={k}>{t.has(`payslip.lines.${k}`) ? t(`payslip.lines.${k}`) : k}: {formatNumber(Number(v))}</div>
                ))}
              </div>
            ) : null}
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            {html ? (
              <div className="rounded border bg-white p-3 text-black" dangerouslySetInnerHTML={{ __html: html }} />
            ) : (
              <p className="text-sm text-muted-foreground">{t('payslip.emptyPreview')}</p>
            )}
          </CardContent>
        </Card>
      </div>
    </HrmPageLayout>
  );
}
