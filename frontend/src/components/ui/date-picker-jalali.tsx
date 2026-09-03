'use client';

import { useMemo } from 'react';
import DatePicker from 'react-multi-date-picker';
import DateObject from 'react-date-object';
import persian from 'react-date-object/calendars/persian';
import persian_fa from 'react-date-object/locales/persian_fa';
import { cn } from '@/lib/utils';

export type JalaliDatePickerProps = {
  value?: string | null;
  onChange?: (isoDate: string | null) => void;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
};

/** Controlled Jalali date picker; value/onChange use ISO `yyyy-mm-dd` strings. */
export function JalaliDatePicker({ value, onChange, className, placeholder = 'انتخاب تاریخ', disabled }: JalaliDatePickerProps) {
  const dateObj = useMemo(() => {
    if (!value) {
      return undefined;
    }
    const d = new DateObject({ date: value, format: 'YYYY-MM-DD' });
    return d.isValid ? d : undefined;
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
          const obj = d as { format?: (f: string) => string };
          if (typeof obj.format === 'function') {
            onChange(obj.format('YYYY-MM-DD'));
          } else {
            onChange(null);
          }
        }}
        disabled={disabled}
        inputClass="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        containerClassName="w-full"
        placeholder={placeholder}
      />
    </div>
  );
}
