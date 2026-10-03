'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Card, CardContent } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getPayrollDecrees, savePayrollDecree } from '@/lib/api/hrm';

export function PayrollDecreesPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [employeeId, setEmployeeId] = useState('');
  const [daily, setDaily] = useState('0');
  const [baseSalary, setBaseSalary] = useState('');
  const [projectFee, setProjectFee] = useState('');
  const [engagement, setEngagement] = useState('full_time');
  const [basis, setBasis] = useState('monthly');
  const [jobCode, setJobCode] = useState('');
  const [effectiveFrom, setEffectiveFrom] = useState(() => new Date().toISOString().slice(0, 10));
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getPayrollDecrees();
      setRows(res?.decrees ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    try {
      await savePayrollDecree({
        employee_id: Number(employeeId),
        decree_type: 'hire',
        status: 'issued',
        effective_from: effectiveFrom,
        daily_wage: Number(daily) || 0,
        base_salary: baseSalary !== '' ? Number(baseSalary) : null,
        project_fee: projectFee !== '' ? Number(projectFee) : null,
        engagement_type: engagement,
        pay_basis: basis,
        job_code: jobCode,
      });
      setSuccess(tNav('common.saved'));
      setEmployeeId('');
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.decrees')} {...layoutProps}>
      <Card className="mb-4 text-start">
        <CardContent className="pt-6 flex flex-wrap gap-2">
          <Input
            className="max-w-[8rem]"
            value={employeeId}
            onChange={(e) => setEmployeeId(e.target.value)}
            placeholder={t('loans.employeeId')}
          />
          <Select value={engagement} onValueChange={setEngagement}>
            <SelectTrigger className="max-w-[10rem]"><SelectValue /></SelectTrigger>
            <SelectContent>
              {(['full_time','part_time','contractor','freelance','project','remote'] as const).map((k) => (
                <SelectItem key={k} value={k}>{t(`engagement.types.${k}`)}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={basis} onValueChange={setBasis}>
            <SelectTrigger className="max-w-[10rem]"><SelectValue /></SelectTrigger>
            <SelectContent>
              {(['monthly','daily','hourly','project_fee'] as const).map((k) => (
                <SelectItem key={k} value={k}>{t(`engagement.bases.${k}`)}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Input className="max-w-[8rem]" value={baseSalary} onChange={(e) => setBaseSalary(e.target.value)} placeholder={t('portal.baseSalary')} />
          <Input className="max-w-[8rem]" value={projectFee} onChange={(e) => setProjectFee(e.target.value)} placeholder={t('engagement.projectFee')} />
          <Input
            className="max-w-[8rem]"
            value={daily}
            onChange={(e) => setDaily(e.target.value)}
            placeholder={t('portal.dailyWage')}
          />
          <Input
            className="max-w-[8rem]"
            value={jobCode}
            onChange={(e) => setJobCode(e.target.value)}
            placeholder={t('portal.jobCode')}
          />
          <LocaleDatePicker
            className="max-w-[12rem]"
            value={effectiveFrom}
            onChange={setEffectiveFrom}
          />
          <Button onClick={() => void create()} disabled={!employeeId}>
            {t('portal.issueDecree')}
          </Button>
        </CardContent>
      </Card>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-2">
          {rows.length === 0 ? (
            <div className="py-8 text-muted-foreground">{tNav('common.empty')}</div>
          ) : (
            rows.map((d) => (
              <div
                key={String(d.id)}
                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
              >
                <span>
                  {String(d.decree_no ?? d.id)} — {String(d.engagement_type ?? '')} / {String(d.pay_basis ?? '')} — {String(d.status)}
                </span>
              </div>
            ))
          )}
        </div>
      )}
    </HrmPageLayout>
  );
}
