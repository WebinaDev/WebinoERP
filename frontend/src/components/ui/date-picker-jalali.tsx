'use client';

import { useTranslations } from 'next-intl';

import { useMemo } from 'react';
import DatePicker from 'react-multi-date-picker';
import DateObject from 'react-date-object';
import persian from 'react-date-object/calendars/persian';
import gregorian from 'react-date-object/calendars/gregorian';
import persian_fa from 'react-date-object/locales/persian_fa';
import { cn } from '@/lib/utils';

export type JalaliDatePickerProps = {
  value?: string | null;
  onChange?: (isoDate: string | null) => void;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
};

/** Controlled Jalali date picker; value/onChange use ISO Gregorian `yyyy-mm-dd` strings. */
export function JalaliDatePicker({
  value,
  onChange,
  className,
  placeholder,
  disabled,
}: JalaliDatePickerProps) {

  const t = useTranslations();
  const resolvedPlaceholder = placeholder ?? t('auto.date_picker_jalali.s_85607c4d');
  const dateObj = useMemo(() => {
    if (!value) {
      return undefined;
    }
    const d = new DateObject({
      date: value,
      format: 'YYYY-MM-DD',
      calendar: gregorian,
    });
    return d.isValid ? d.convert(persian) : undefined;
  }, [value]);

  return (
    <div className={cn('w-full', className)} dir="rtl">
      <DatePicker
        calendar={persian}
        locale={persian_fa}
        value={dateObj}
        onChange={(d: unknown) => {
          if (!onChange) {
            return;
          }
          if (!d || Array.isArray(d)) {
            onChange(null);
            return;
          }
          const converted = new DateObject(d as DateObject).convert(gregorian);
          onChange(converted.format('YYYY-MM-DD'));
        }}
        disabled={disabled}
        inputClass="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        containerClassName="w-full"
        placeholder={resolvedPlaceholder}
      />
    </div>
  );
}
