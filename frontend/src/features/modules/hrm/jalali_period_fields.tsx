'use client';

import { useTranslations } from 'next-intl';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale-next';
import { toJalali } from '@/lib/locale/calendar-date';

const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as const;

export function usePayrollPeriod(initial?: { year?: string; month?: string }) {
  const { locale } = useLocale();
  const now = new Date();
  const jalali = toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
  const year = initial?.year ?? String(locale === 'fa' ? jalali.jy : now.getFullYear());
  const month = initial?.month ?? String(locale === 'fa' ? jalali.jm : now.getMonth() + 1);
  return { year, month, jalaliYear: jalali.jy, gregorianYear: now.getFullYear(), locale };
}

export function PayrollPeriodFields({
  year,
  month,
  onYear,
  onMonth,
  onPickDate,
}: {
  year: string;
  month: string;
  onYear: (value: string) => void;
  onMonth: (value: string) => void;
  onPickDate?: (iso: string, year: string, month: string) => void;
}) {
  const t = useTranslations('hrm');
  const { locale, formatDigits } = useLocale();
  const now = new Date();
  const jalali = toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
  const base = locale === 'fa' ? jalali.jy : now.getFullYear();
  const years = Array.from({ length: 7 }, (_, i) => String(base - 3 + i));
  if (!years.includes(year)) years.push(year);

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <div className="space-y-1">
        <Label>{locale === 'fa' ? t('wizard.periodYear') : t('year')}</Label>
        <Select value={year} onValueChange={onYear}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {years.map((value) => <SelectItem key={value} value={value}>{formatDigits(value)}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-1">
        <Label>{locale === 'fa' ? t('wizard.periodMonth') : t('month')}</Label>
        <Select value={String(Number(month))} onValueChange={onMonth}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            {MONTHS.map((value) => (
              <SelectItem key={value} value={String(value)}>
                {locale === 'fa' ? t(`months.${value}`) : String(value)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {onPickDate ? (
        <div className="space-y-1 sm:col-span-2">
          <Label>{t('wizard.periodDate')}</Label>
          <LocaleDatePicker
            onChange={(iso) => {
              const [gy, gm, gd] = iso.split('-').map(Number);
              if (locale === 'fa') {
                const j = toJalali(gy, gm, gd);
                onPickDate(iso, String(j.jy), String(j.jm));
              } else {
                onPickDate(iso, String(gy), String(gm));
              }
            }}
          />
        </div>
      ) : null}
    </div>
  );
}
