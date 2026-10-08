'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmField, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { PayrollPeriodFields, usePayrollPeriod } from '@/features/modules/hrm/jalali_period_fields';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { downloadHrmFile, getInsuranceListForPeriod, getPayrollSettings, savePayrollSettings } from '@/lib/api/hrm';

const WORKSHOP_KEYS = [
  'workshop_code',
  'workshop_name',
  'employer_name',
  'workshop_address',
  'sso_list_no',
  'sso_contract_row',
  'sso_list_description',
  'employer_insurance_percent',
] as const;
const LTR_KEYS = new Set(['workshop_code', 'sso_list_no', 'sso_contract_row', 'employer_insurance_percent']);

const DOWNLOADS = [
  { format: 'zip', key: 'zip', file: (s: string) => `sso-diskette-${s}.zip` },
  { format: 'dbf_wor', key: 'dbfWor', file: () => 'DSKWOR00.DBF' },
  { format: 'dbf_kar', key: 'dbfKar', file: () => 'DSKKAR00.DBF' },
  { format: 'dskwor', key: 'txtWor', file: (s: string) => `DSKWOR-${s}.txt` },
  { format: 'dskkar', key: 'txtKar', file: (s: string) => `DSKKAR-${s}.txt` },
  { format: 'list', key: 'listFa', file: (s: string) => `sso-list-fa-${s}.csv` },
  { format: 'csv', key: 'csv', file: (s: string) => `sso-list-${s}.csv` },
] as const;

const TOTAL_KEYS = ['count', 'days', 'monthly_wage', 'benefits', 'insurable', 'employee_insurance', 'employer_insurance', 'unemployment_insurance'] as const;

export function SsoListPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber } = useLocale();
  const initial = usePayrollPeriod();
  const [year, setYear] = useState(initial.year);
  const [month, setMonth] = useState(initial.month);
  const [workshop, setWorkshop] = useState<Record<string, string>>({});
  const [data, setData] = useState<HrmRow | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    void getPayrollSettings().then((res) => {
      const s = (res ?? {}) as HrmRow;
      const next: Record<string, string> = {};
      WORKSHOP_KEYS.forEach((k) => { next[k] = s[k] == null ? '' : String(s[k]); });
      setWorkshop(next);
    }).catch(applyAxiosError);
  }, [applyAxiosError]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await getInsuranceListForPeriod(year, month));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, year, month]);

  useEffect(() => { void load(); }, [load]);

  const saveWorkshop = async () => {
    try {
      await savePayrollSettings(workshop);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const rows = Array.isArray(data?.rows) ? (data?.rows as HrmRow[]) : [];
  const totals = (data?.totals ?? {}) as HrmRow;
  const warnings = Number(data?.warnings ?? 0);
  const suffix = `${year}-${String(month).padStart(2, '0')}`;
  const download = (format: string, filename: string) =>
    void downloadHrmFile(`payroll/insurance-list?year=${year}&month=${month}&format=${format}`, filename).catch(applyAxiosError);

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.ssoList')} description={t('sso.hint')} {...layoutProps}>
      <div className="space-y-4">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('sso.workshopSection')}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            {WORKSHOP_KEYS.map((k) => (
              <HrmField key={k} label={t(`sso.fields.${k}`)} hint={t.has(`sso.fieldHints.${k}`) ? t(`sso.fieldHints.${k}`) : undefined}>
                <Input
                  dir={LTR_KEYS.has(k) ? 'ltr' : undefined}
                  inputMode={LTR_KEYS.has(k) ? 'numeric' : undefined}
                  value={workshop[k] ?? ''}
                  onChange={(e) => setWorkshop((w) => ({ ...w, [k]: e.target.value }))}
                />
              </HrmField>
            ))}
            <div className="md:col-span-2"><Button onClick={() => void saveWorkshop()}>{tNav('common.save')}</Button></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('sso.periodSection')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <PayrollPeriodFields year={year} month={month} onYear={setYear} onMonth={setMonth} />
            <div className="flex flex-wrap gap-2">
              {DOWNLOADS.map((d) => (
                <Button key={d.format} variant={d.format === 'zip' ? 'default' : 'outline'} disabled={rows.length === 0} onClick={() => download(d.format, d.file(suffix))}>
                  {t(`sso.downloads.${d.key}`)}
                </Button>
              ))}
            </div>
            <p className="text-xs text-muted-foreground">{t('sso.formatNote')}</p>
          </CardContent>
        </Card>

        {warnings > 0 ? (
          <Alert className="border-destructive text-destructive">
            <AlertDescription>{t('sso.warningsCount', { count: formatNumber(warnings) })}</AlertDescription>
          </Alert>
        ) : null}

        <Card>
          <CardHeader><CardTitle className="text-base">{t('sso.totals')}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {TOTAL_KEYS.map((k) => (
              <div key={k} className="rounded border p-3">
                <div className="text-xs text-muted-foreground">{t(`sso.cols.${k}`)}</div>
                <div className="font-semibold">{formatNumber(Number(totals[k] ?? 0))}</div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('sso.preview')}</CardTitle></CardHeader>
          <CardContent className="overflow-x-auto">
            {loading ? <PageLoadingState /> : (
              <Table>
                <TableHeader><TableRow>
                  <TableHead>{t('staffCode')}</TableHead>
                  <TableHead>{t('employee')}</TableHead>
                  <TableHead>{t('nationalId')}</TableHead>
                  <TableHead>{t('sso.cols.insurance_no')}</TableHead>
                  <TableHead>{t('sso.cols.days')}</TableHead>
                  <TableHead>{t('sso.cols.monthly_wage')}</TableHead>
                  <TableHead>{t('sso.cols.benefits')}</TableHead>
                  <TableHead>{t('sso.cols.insurable')}</TableHead>
                  <TableHead>{t('sso.cols.employee_insurance')}</TableHead>
                  <TableHead>{t('sso.cols.employer_insurance')}</TableHead>
                  <TableHead>{t('sso.cols.warnings')}</TableHead>
                </TableRow></TableHeader>
                <TableBody>
                  {rows.length === 0 ? (
                    <TableRow><TableCell colSpan={11}><PageEmptyState description={t('sso.empty')} /></TableCell></TableRow>
                  ) : rows.map((r) => {
                    const w = Array.isArray(r.warnings) ? (r.warnings as string[]) : [];
                    return (
                      <TableRow key={String(r.employee_id)}>
                        <TableCell><HrmDigits value={r.employee_code} /></TableCell>
                        <TableCell>{`${String(r.first_name ?? '')} ${String(r.last_name ?? '')}`.trim()}</TableCell>
                        <TableCell><HrmDigits value={r.national_id} /></TableCell>
                        <TableCell><HrmDigits value={r.insurance_no} /></TableCell>
                        <TableCell><HrmDigits value={r.days} /></TableCell>
                        <TableCell>{formatNumber(Number(r.monthly_wage ?? 0))}</TableCell>
                        <TableCell>{formatNumber(Number(r.benefits ?? 0))}</TableCell>
                        <TableCell>{formatNumber(Number(r.insurable ?? 0))}</TableCell>
                        <TableCell>{formatNumber(Number(r.employee_insurance ?? 0))}</TableCell>
                        <TableCell>{formatNumber(Number(r.employer_insurance ?? 0))}</TableCell>
                        <TableCell className="space-y-1">
                          {w.length === 0 ? <Badge variant="secondary">{t('sso.ok')}</Badge> : w.map((code) => (
                            <Badge key={code} variant="destructive" className="me-1">{t.has(`sso.warnings.${code}`) ? t(`sso.warnings.${code}`) : code}</Badge>
                          ))}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </HrmPageLayout>
  );
}
