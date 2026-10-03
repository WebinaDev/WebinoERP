'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { issueEmployeePayslip } from '@/lib/api/hrm';

export function IssuePayslipPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const now = new Date();
  const [employeeId, setEmployeeId] = useState('');
  const [year, setYear] = useState(String(now.getFullYear()));
  const [month, setMonth] = useState(String(now.getMonth() + 1));
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
    <CrmPageLayout title={tNav('nav.erp.hrm.issuePayslip')} {...layoutProps}>
      <div className="grid gap-4 text-start lg:grid-cols-2">
        <Card>
          <CardContent className="space-y-3 pt-6">
            <p className="text-sm text-muted-foreground">{t('payslip.hint')}</p>
            <div className="space-y-1">
              <Label>{t('loans.employeeId')}</Label>
              <Input value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <Input type="number" value={year} onChange={(e) => setYear(e.target.value)} placeholder={t('year')} />
              <Input type="number" min={1} max={12} value={month} onChange={(e) => setMonth(e.target.value)} placeholder={t('month')} />
            </div>
            <Button onClick={() => void issue()} disabled={busy || !employeeId}>{t('payslip.issue')}</Button>
            {summary ? (
              <div className="space-y-1 text-sm">
                <div>{t('gross')}: {String(summary.gross ?? '')}</div>
                <div>{t('net')}: {String(summary.net ?? '')}</div>
                <div>{t('settings.employeeInsurance')}: {String(breakdown.employee_insurance ?? '')}</div>
                <div>{t('payslip.tax')}: {String(breakdown.tax ?? '')}</div>
                <div>{t('payslip.employerInsurance')}: {String(breakdown.employer_insurance ?? '')}</div>
                {Object.entries(lines).map(([k, v]) => (
                  <div key={k}>{k}: {String(v)}</div>
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
    </CrmPageLayout>
  );
}
