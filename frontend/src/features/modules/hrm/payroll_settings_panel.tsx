'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { getPayrollSettings, savePayrollSettings } from '@/lib/api/hrm';

const ENGAGEMENTS = ['full_time', 'part_time', 'contractor', 'freelance', 'project', 'remote'] as const;

const NUMBER_GROUPS: Array<{ title: string; keys: Array<[string, string]> }> = [
  {
    title: 'settings.sectionInsurance',
    keys: [
      ['employee_insurance_percent', 'settings.employeeInsurance'],
      ['employer_insurance_percent', 'settings.employerInsurance'],
      ['unemployment_insurance_percent', 'settings.unemployment'],
      ['insurance_ceiling', 'settings.insuranceCeiling'],
      ['insurance_deductible_fraction', 'settings.insuranceFraction'],
    ],
  },
  {
    title: 'settings.sectionWage',
    keys: [
      ['minimum_monthly_wage', 'settings.minimumWage'],
      ['minimum_daily_wage', 'settings.minimumDaily'],
      ['minimum_hourly_wage', 'settings.minimumHourly'],
      ['working_days_per_month', 'settings.workingDays'],
      ['seniority_daily_rate', 'settings.seniorityDaily'],
      ['seniority_monthly_amount', 'settings.seniorityMonthly'],
    ],
  },
  {
    title: 'settings.sectionTime',
    keys: [
      ['overtime_multiplier', 'settings.overtimeMultiplier'],
      ['monthly_hours', 'settings.monthlyHours'],
      ['mission_daily_allowance', 'settings.missionDaily'],
      ['eydi_month', 'settings.eydiMonth'],
      ['eydi_months_factor', 'settings.eydiFactor'],
      ['eydi_cap_min_wage_months', 'settings.eydiCap'],
      ['severance_months_per_year', 'settings.severanceMonths'],
      ['law_year', 'settings.lawYear'],
    ],
  },
];

type Bracket = { up_to: string; rate: string };

export function PayrollSettingsPanel() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { isRtl } = useLocale();
  const { setSuccess, applyAxiosError } = useCrmFeedback();
  const [numbers, setNumbers] = useState<Record<string, string>>({});
  const [lawLabel, setLawLabel] = useState('');
  const [minWage, setMinWage] = useState<string[]>(['full_time']);
  const [benefits, setBenefits] = useState<string[]>(['full_time', 'part_time', 'remote']);
  const [eydiProrate, setEydiProrate] = useState(true);
  const [severanceProrate, setSeveranceProrate] = useState(true);
  const [brackets, setBrackets] = useState<Bracket[]>([{ up_to: '', rate: '0' }]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await getPayrollSettings();
      const raw = (res as { data?: Record<string, unknown> })?.data ?? (res as Record<string, unknown>) ?? {};
      const s = raw as Record<string, unknown>;
      const next: Record<string, string> = {};
      for (const group of NUMBER_GROUPS) {
        for (const [key] of group.keys) {
          next[key] = s[key] != null && typeof s[key] !== 'object' ? String(s[key]) : '';
        }
      }
      setNumbers(next);
      setLawLabel(typeof s.law_year_label === 'string' ? s.law_year_label : '');
      if (Array.isArray(s.min_wage_engagements)) setMinWage(s.min_wage_engagements.map(String));
      if (Array.isArray(s.apply_benefits_engagements)) setBenefits(s.apply_benefits_engagements.map(String));
      if (typeof s.eydi_prorate === 'boolean') setEydiProrate(s.eydi_prorate);
      if (typeof s.severance_prorate === 'boolean') setSeveranceProrate(s.severance_prorate);
      if (Array.isArray(s.tax_brackets)) {
        setBrackets(
          (s.tax_brackets as Array<Record<string, unknown>>).map((row) => ({
            up_to: row.up_to == null ? '' : String(row.up_to),
            rate: String(row.rate ?? '0'),
          })),
        );
      }
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggle = (list: string[], value: string, setList: (next: string[]) => void) => {
    setList(list.includes(value) ? list.filter((item) => item !== value) : [...list, value]);
  };

  const save = async () => {
    setBusy(true);
    try {
      const payload: Record<string, unknown> = {
        law_year_label: lawLabel,
        min_wage_engagements: minWage,
        apply_benefits_engagements: benefits,
        eydi_prorate: eydiProrate,
        severance_prorate: severanceProrate,
        tax_brackets: brackets.map((row) => ({
          up_to: row.up_to.trim() === '' ? null : Number(row.up_to),
          rate: Number(row.rate || 0),
        })),
      };
      for (const [key, value] of Object.entries(numbers)) {
        if (value.trim() !== '') payload[key] = Number(value);
      }
      await savePayrollSettings(payload);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div dir={isRtl ? 'rtl' : 'ltr'} className="space-y-4 text-start">
      <p className="text-sm text-muted-foreground">{t('iranianDefaultsHint')}</p>
      <p className="text-sm text-muted-foreground">{t('settings.minWageRule')}</p>
      {NUMBER_GROUPS.map((group) => (
        <Card key={group.title}>
          <CardHeader><CardTitle className="text-base">{t(group.title)}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2">
            {group.keys.map(([key, label]) => (
              <div key={key} className="space-y-1">
                <Label>{t(label)}</Label>
                <Input dir="ltr" className="text-end" type="number" value={numbers[key] ?? ''} onChange={(e) => setNumbers((s) => ({ ...s, [key]: e.target.value }))} />
              </div>
            ))}
            {group.title === 'settings.sectionTime' ? (
              <div className="space-y-1">
                <Label>{t('settings.lawYearLabel')}</Label>
                <Input value={lawLabel} onChange={(e) => setLawLabel(e.target.value)} />
              </div>
            ) : null}
          </CardContent>
        </Card>
      ))}
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.sectionFlags')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          <div className="flex items-center justify-between gap-3">
            <Label>{t('settings.eydiProrate')}</Label>
            <Switch checked={eydiProrate} onCheckedChange={setEydiProrate} />
          </div>
          <div className="flex items-center justify-between gap-3">
            <Label>{t('settings.severanceProrate')}</Label>
            <Switch checked={severanceProrate} onCheckedChange={setSeveranceProrate} />
          </div>
          <EngagementChecks title={t('settings.minWageEngagements')} selected={minWage} onToggle={(v) => toggle(minWage, v, setMinWage)} />
          <EngagementChecks title={t('settings.benefitEngagements')} selected={benefits} onToggle={(v) => toggle(benefits, v, setBenefits)} />
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('settings.taxBrackets')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          <p className="text-xs text-muted-foreground">{t('settings.taxBracketHint')}</p>
          {brackets.map((row, index) => (
            <div key={index} className="grid gap-2 sm:grid-cols-[1fr_8rem_auto]">
              <div className="space-y-1">
                <Label>{t('settings.bracketUpTo')}</Label>
                <Input dir="ltr" className="text-end" type="number" value={row.up_to} placeholder={t('settings.bracketOpen')} onChange={(e) => setBrackets((rows) => rows.map((item, i) => i === index ? { ...item, up_to: e.target.value } : item))} />
              </div>
              <div className="space-y-1">
                <Label>{t('settings.bracketRate')}</Label>
                <Input dir="ltr" className="text-end" type="number" value={row.rate} onChange={(e) => setBrackets((rows) => rows.map((item, i) => i === index ? { ...item, rate: e.target.value } : item))} />
              </div>
              <Button className="self-end" variant="outline" onClick={() => setBrackets((rows) => rows.filter((_, i) => i !== index))}>{tNav('common.delete')}</Button>
            </div>
          ))}
          <Button variant="outline" onClick={() => setBrackets((rows) => [...rows, { up_to: '', rate: '0' }])}>{t('settings.addBracket')}</Button>
        </CardContent>
      </Card>
      <Button onClick={() => void save()} disabled={busy}>{tNav('common.save')}</Button>
    </div>
  );
}

function EngagementChecks({ title, selected, onToggle }: { title: string; selected: string[]; onToggle: (value: string) => void }) {
  const t = useTranslations('hrm');
  return (
    <div className="space-y-2">
      <Label>{title}</Label>
      <div className="flex flex-wrap gap-3">
        {ENGAGEMENTS.map((code) => (
          <label key={code} className="flex items-center gap-2 text-sm">
            <Checkbox checked={selected.includes(code)} onCheckedChange={() => onToggle(code)} />
            {t(`engagement.types.${code}`)}
          </label>
        ))}
      </div>
    </div>
  );
}
