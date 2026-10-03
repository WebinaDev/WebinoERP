'use client';

import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getHrmMe, getMyPayslips } from '@/lib/api/hrm';

export function MyPayrollPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const intlLocale = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<
    Array<{
      id: number;
      run_title?: string;
      jalali_year?: number;
      jalali_month?: number;
      gross: number;
      net: number;
      deductions?: number;
      breakdown?: {
        employee_insurance?: number;
        employer_insurance?: number;
        unemployment_insurance?: number;
        tax?: number;
        loan_deduction?: number;
        advance_deduction?: number;
        overtime?: number;
        mission?: number;
        lines?: Record<string, number>;
      };
    }>
  >([]);
  const [decree, setDecree] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);

  const formatNumber = (n: number) =>
    new Intl.NumberFormat(intlLocale === 'fa' ? 'fa-IR' : 'en-US').format(n);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [psRes, meRes] = await Promise.all([getMyPayslips(), getHrmMe()]);
      setItems(psRes?.payslips ?? []);
      setDecree((meRes?.decree as Record<string, unknown>) ?? null);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myPayroll')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          {decree ? (
            <Card>
              <CardContent className="pt-6 text-sm space-y-1">
                <div className="font-medium">{t('portal.currentDecree')}</div>
                <div>
                  {String(decree.decree_no ?? '')} · {String(decree.job_title ?? '')}
                </div>
                <div>
                  {t('portal.baseSalary')}: {formatNumber(Number(decree.base_salary ?? 0))}
                </div>
              </CardContent>
            </Card>
          ) : null}
          <Card>
            <CardContent className="pt-6 space-y-3">
              {items.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                items.map((ps) => (
                  <div key={ps.id} className="rounded-md border px-3 py-3 text-sm space-y-2">
                    <div className="font-medium">
                      {ps.run_title || `#${ps.id}`}
                      {ps.jalali_year ? ` · ${ps.jalali_year}/${ps.jalali_month}` : ''}
                    </div>
                    <div className="grid gap-1 sm:grid-cols-2 text-muted-foreground">
                      <span>
                        {t('gross')}: {formatNumber(ps.gross)}
                      </span>
                      <span>
                        {t('net')}: {formatNumber(ps.net)}
                      </span>
                      {ps.deductions != null ? (
                        <span>
                          {t('portal.deductions')}: {formatNumber(ps.deductions)}
                        </span>
                      ) : null}
                      {ps.breakdown?.employee_insurance != null ? (
                        <span>{t('settings.employeeInsurance')}: {formatNumber(ps.breakdown.employee_insurance)}</span>
                      ) : null}
                      {ps.breakdown?.tax != null ? (
                        <span>{t('payslip.tax')}: {formatNumber(ps.breakdown.tax)}</span>
                      ) : null}
                      {ps.breakdown?.loan_deduction != null ? (
                        <span>{t('loans.loan')}: {formatNumber(ps.breakdown.loan_deduction)}</span>
                      ) : null}
                      {ps.breakdown?.lines
                        ? Object.entries(ps.breakdown.lines).map(([k, v]) => (
                            <span key={k}>{k}: {formatNumber(Number(v))}</span>
                          ))
                        : null}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
