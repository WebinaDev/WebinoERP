'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmField, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { PayrollPeriodFields, usePayrollPeriod } from '@/features/modules/hrm/jalali_period_fields';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { deleteWorkforceBudget, getWorkforceBudgets, saveWorkforceBudget } from '@/lib/api/hrm';

const OTHER = '__other__';

function useBudgetData(onError: (err: unknown) => void) {
  const initial = usePayrollPeriod();
  const [year, setYear] = useState(initial.year);
  const [month, setMonth] = useState(initial.month);
  const [annual, setAnnual] = useState(false);
  const [data, setData] = useState<HrmRow | null>(null);
  const [loading, setLoading] = useState(true);
  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await getWorkforceBudgets({ year, month: annual ? undefined : month }));
    } catch (err) {
      onError(err);
    } finally {
      setLoading(false);
    }
  }, [annual, month, onError, year]);
  useEffect(() => { void load(); }, [load]);
  return { year, setYear, month, setMonth, annual, setAnnual, data, loading, load };
}

function signed(n: number, fmt: (v: number) => string) {
  if (n > 0) return `+${fmt(n)}`;
  if (n < 0) return `−${fmt(Math.abs(n))}`;
  return fmt(0);
}

function ComparisonTable({ data }: { data: HrmRow }) {
  const t = useTranslations('hrm');
  const { formatNumber, locale } = useLocale();
  const pct = locale === 'fa' ? '\u066A' : '%';
  const rows = Array.isArray(data.rows) ? (data.rows as HrmRow[]) : [];
  const totals = (data.totals ?? {}) as HrmRow;
  const totalHeadVar = Number(totals.headcount_actual ?? 0) - Number(totals.headcount_budget ?? 0);
  const totalCostVar = Number(totals.cost_actual ?? 0) - Number(totals.cost_budget ?? 0);
  const totalUsage = Number(totals.cost_budget ?? 0) > 0 ? Math.round((Number(totals.cost_actual ?? 0) / Number(totals.cost_budget)) * 100) : 0;
  if (rows.length === 0) return <PageEmptyState description={t('budget.empty')} />;
  return (
    <Table>
      <TableHeader><TableRow>
        <TableHead>{t('department')}</TableHead>
        <TableHead>{t('budget.basis')}</TableHead>
        <TableHead>{t('budget.headBudget')}</TableHead>
        <TableHead>{t('budget.headActual')}</TableHead>
        <TableHead>{t('budget.headVariance')}</TableHead>
        <TableHead>{t('budget.costBudget')}</TableHead>
        <TableHead>{t('budget.costActual')}</TableHead>
        <TableHead>{t('budget.costVariance')}</TableHead>
        <TableHead>{t('budget.usage')}</TableHead>
      </TableRow></TableHeader>
      <TableBody>
        {rows.map((r) => {
          const usage = r.cost_usage_percent == null ? null : Number(r.cost_usage_percent);
          const over = usage != null && usage > 100;
          return (
            <TableRow key={String(r.department)}>
              <TableCell>{String(r.department || t('budget.noDepartment'))}</TableCell>
              <TableCell>{t(`budget.bases.${String(r.basis ?? 'none')}`)}</TableCell>
              <TableCell>{r.headcount_budget == null ? '—' : formatNumber(Number(r.headcount_budget))}</TableCell>
              <TableCell>{formatNumber(Number(r.headcount_actual ?? 0))}</TableCell>
              <TableCell className={Number(r.headcount_variance ?? 0) > 0 ? 'text-destructive' : ''}>{r.headcount_variance == null ? '—' : signed(Number(r.headcount_variance), formatNumber)}</TableCell>
              <TableCell>{r.cost_budget == null ? '—' : formatNumber(Number(r.cost_budget))}</TableCell>
              <TableCell>{formatNumber(Number(r.cost_actual ?? 0))}</TableCell>
              <TableCell className={Number(r.cost_variance ?? 0) > 0 ? 'text-destructive' : ''}>{r.cost_variance == null ? '—' : signed(Number(r.cost_variance), formatNumber)}</TableCell>
              <TableCell className="min-w-32">
                {usage == null ? '—' : (
                  <div className="space-y-1">
                    <Progress value={Math.min(100, usage)} />
                    <span className={over ? 'text-xs text-destructive' : 'text-xs text-muted-foreground'}>{formatNumber(usage)}{pct}</span>
                  </div>
                )}
              </TableCell>
            </TableRow>
          );
        })}
        <TableRow className="font-semibold">
          <TableCell>{t('budget.total')}</TableCell>
          <TableCell />
          <TableCell>{formatNumber(Number(totals.headcount_budget ?? 0))}</TableCell>
          <TableCell>{formatNumber(Number(totals.headcount_actual ?? 0))}</TableCell>
          <TableCell>{signed(totalHeadVar, formatNumber)}</TableCell>
          <TableCell>{formatNumber(Number(totals.cost_budget ?? 0))}</TableCell>
          <TableCell>{formatNumber(Number(totals.cost_actual ?? 0))}</TableCell>
          <TableCell>{signed(totalCostVar, formatNumber)}</TableCell>
          <TableCell>{formatNumber(totalUsage)}{pct}</TableCell>
        </TableRow>
      </TableBody>
    </Table>
  );
}

function PeriodControls({ state }: { state: ReturnType<typeof useBudgetData> }) {
  const t = useTranslations('hrm');
  return (
    <div className="space-y-3">
      <PayrollPeriodFields year={state.year} month={state.month} onYear={state.setYear} onMonth={state.setMonth} />
      <label className="flex items-center gap-2 text-sm">
        <Switch checked={state.annual} onCheckedChange={state.setAnnual} />
        {t('budget.annualView')}
      </label>
    </div>
  );
}

/** Compact budget-vs-actual block for the analytics dashboard. */
export function WorkforceBudgetSection() {
  const t = useTranslations('hrm');
  const { applyAxiosError } = useCrmFeedback();
  const state = useBudgetData(applyAxiosError);
  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{t('budget.title')}</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <PeriodControls state={state} />
        {state.loading || !state.data ? <PageLoadingState /> : <div className="overflow-x-auto"><ComparisonTable data={state.data} /></div>}
      </CardContent>
    </Card>
  );
}

export function WorkforceBudgetPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber, formatDigits } = useLocale();
  const state = useBudgetData(applyAxiosError);
  const [department, setDepartment] = useState('');
  const [customDepartment, setCustomDepartment] = useState('');
  const [scope, setScope] = useState<'annual' | 'month'>('annual');
  const [headcount, setHeadcount] = useState('');
  const [cost, setCost] = useState('');
  const [notes, setNotes] = useState('');

  const departments = Array.isArray(state.data?.departments) ? (state.data?.departments as string[]) : [];
  const budgets = Array.isArray(state.data?.budgets) ? (state.data?.budgets as HrmRow[]) : [];
  const dept = department === OTHER ? customDepartment.trim() : department;
  const toAscii = (v: string) => v.replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[,،\s]/g, '');

  const save = async () => {
    try {
      await saveWorkforceBudget({
        department: dept,
        year: Number(state.year),
        month: scope === 'month' ? Number(state.month) : null,
        headcount: Number(toAscii(headcount) || 0),
        cost_budget: Number(toAscii(cost) || 0),
        notes: notes || null,
      });
      setSuccess(tNav('common.saved'));
      setHeadcount('');
      setCost('');
      setNotes('');
      void state.load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.workforceBudget')} description={t('budget.hint')} {...layoutProps}>
      <div className="space-y-4">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('budget.period')}</CardTitle></CardHeader>
          <CardContent><PeriodControls state={state} /></CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('budget.compare')}</CardTitle></CardHeader>
          <CardContent className="overflow-x-auto">
            {state.loading || !state.data ? <PageLoadingState /> : <ComparisonTable data={state.data} />}
            <p className="mt-2 text-xs text-muted-foreground">{t('budget.actualNote')}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('budget.define')}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            <HrmField label={t('department')}>
              <Select value={department || undefined} onValueChange={setDepartment}>
                <SelectTrigger><SelectValue placeholder={tNav('common.select')} /></SelectTrigger>
                <SelectContent>
                  {departments.map((d) => <SelectItem key={d} value={d}>{d}</SelectItem>)}
                  <SelectItem value={OTHER}>{t('budget.otherDepartment')}</SelectItem>
                </SelectContent>
              </Select>
            </HrmField>
            {department === OTHER ? (
              <HrmField label={t('budget.departmentName')}><Input value={customDepartment} onChange={(e) => setCustomDepartment(e.target.value)} /></HrmField>
            ) : null}
            <HrmField label={t('budget.scope')}>
              <Select value={scope} onValueChange={(v) => setScope(v === 'month' ? 'month' : 'annual')}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="annual">{t('budget.scopeAnnual', { year: formatDigits(state.year) })}</SelectItem>
                  <SelectItem value="month">{t('budget.scopeMonth', { year: formatDigits(state.year), month: t(`months.${Number(state.month)}`) })}</SelectItem>
                </SelectContent>
              </Select>
            </HrmField>
            <HrmField label={t('budget.headBudget')}><Input inputMode="numeric" value={headcount} onChange={(e) => setHeadcount(e.target.value)} /></HrmField>
            <HrmField label={t('budget.costBudget')} hint={cost ? formatNumber(Number(toAscii(cost) || 0)) : t('budget.costHint')}>
              <Input inputMode="numeric" value={cost} onChange={(e) => setCost(e.target.value)} />
            </HrmField>
            <HrmField label={t('notes')}><Input value={notes} onChange={(e) => setNotes(e.target.value)} /></HrmField>
            <div className="md:col-span-2"><Button disabled={!dept || headcount === ''} onClick={() => void save()}>{tNav('common.save')}</Button></div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('budget.defined', { year: formatDigits(state.year) })}</CardTitle></CardHeader>
          <CardContent>
            <Table>
              <TableHeader><TableRow>
                <TableHead>{t('department')}</TableHead>
                <TableHead>{t('budget.scope')}</TableHead>
                <TableHead>{t('budget.headBudget')}</TableHead>
                <TableHead>{t('budget.costBudget')}</TableHead>
                <TableHead>{t('notes')}</TableHead>
                <TableHead>{tNav('common.actions')}</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {budgets.length === 0 ? (
                  <TableRow><TableCell colSpan={6}><PageEmptyState /></TableCell></TableRow>
                ) : budgets.map((b) => (
                  <TableRow key={String(b.id)}>
                    <TableCell>{String(b.department ?? '')}</TableCell>
                    <TableCell>{b.month ? t(`months.${Number(b.month)}`) : t('budget.annual')}</TableCell>
                    <TableCell>{formatNumber(Number(b.headcount ?? 0))}</TableCell>
                    <TableCell>{formatNumber(Number(b.cost_budget ?? 0))}</TableCell>
                    <TableCell>{String(b.notes ?? '—')}</TableCell>
                    <TableCell>
                      <Button size="sm" variant="destructive" onClick={() => {
                        if (!window.confirm(tNav('common.confirmDelete'))) return;
                        void deleteWorkforceBudget(b.id as number).then(() => { setSuccess(tNav('common.deleted')); void state.load(); }).catch(applyAxiosError);
                      }}>{tNav('common.delete')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </HrmPageLayout>
  );
}
