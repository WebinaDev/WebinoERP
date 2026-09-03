'use client';

/**
 * LocaleDatePicker — switches Jalali (`fa`) / Gregorian (`en`).
 * **Always stores ISO Gregorian strings** (`YYYY-MM-DD`) via `onChange`.
 */
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { JalaliDatePicker } from '@/components/ui/date-picker-jalali';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/locale/format-date';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
  /** ISO date `YYYY-MM-DD` */
  value?: string;
  /** Receives ISO Gregorian `YYYY-MM-DD` */
  onChange?: (iso: string) => void;
  placeholder?: string;
  className?: string;
};

export function LocaleDatePicker({ value, onChange, placeholder, className }: Props) {
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const t = useTranslations('date');

  if (locale === 'fa') {
    return (
      <JalaliDatePicker
        value={value}
        onChange={(iso) => {
          if (iso) onChange?.(iso);
        }}
        placeholder={placeholder}
        className={className}
      />
    );
  }

  return (
    <GregorianDatePicker
      value={value}
      onChange={onChange}
      placeholder={placeholder ?? t('pickDate')}
      className={className}
    />
  );
}

function GregorianDatePicker({ value, onChange, placeholder, className }: Props) {
  const [open, setOpen] = useState(false);
  const date = value ? new Date(value) : undefined;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn('w-full justify-start text-start font-normal', !value && 'text-muted-foreground', className)}
        >
          <CalendarIcon className="me-2 size-4" />
          {value ? formatDate(value, { locale: 'en' }) : placeholder}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={(d) => {
            if (d) {
              onChange?.(d.toISOString().slice(0, 10));
              setOpen(false);
            }
          }}
          initialFocus
        />
      </PopoverContent>
    </Popover>
  );
}
