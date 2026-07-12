'use client';

import { useTranslations } from 'next-intl';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFiscalYear } from './FiscalYearProvider';

type Props = {
  allowAll?: boolean;
  className?: string;
};

export function FiscalYearSelect({ allowAll, className }: Props) {
  const t = useTranslations('finance');
  const { years, fiscalYearId, setFiscalYearId } = useFiscalYear();

  return (
    <Select
      value={fiscalYearId > 0 ? String(fiscalYearId) : allowAll ? 'all' : ''}
      onValueChange={(v) => setFiscalYearId(v === 'all' ? 0 : Number(v))}
    >
      <SelectTrigger className={className ?? 'w-[180px]'}>
        <SelectValue placeholder={t('fiscalYear')} />
      </SelectTrigger>
      <SelectContent>
        {allowAll ? <SelectItem value="all">{t('allFiscalYears')}</SelectItem> : null}
        {years.map((y) => (
          <SelectItem key={y.id} value={String(y.id)}>
            {y.name ?? y.title ?? `#${y.id}`}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
