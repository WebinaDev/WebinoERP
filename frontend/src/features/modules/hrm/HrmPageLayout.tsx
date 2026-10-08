'use client';

import type { ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useLocale } from '@/hooks/use-locale-next';

type Props = {
  title: string;
  description?: string;
  actions?: ReactNode;
  error?: string | null;
  success?: string | null;
  onDismissError?: () => void;
  onDismissSuccess?: () => void;
  children: ReactNode;
};

/** HR screens: explicit RTL in Persian, right-aligned tables, locale digits via children. */
export function HrmPageLayout(props: Props) {
  const { isRtl, locale } = useLocale();
  return (
    <div
      dir={isRtl ? 'rtl' : 'ltr'}
      lang={locale}
      className={
        isRtl
          ? 'hrm-region text-start [&_table]:w-full [&_th]:text-right [&_td]:text-right'
          : 'hrm-region text-start'
      }
    >
      <CrmPageLayout {...props} />
    </div>
  );
}

export function HrmDigits({ value }: { value: unknown }) {
  const { formatDigits } = useLocale();
  if (value == null || value === '') return <>—</>;
  return <>{formatDigits(String(value))}</>;
}

export function HrmStatus({ value }: { value: unknown }) {
  const t = useTranslations('hrm.suite.status');
  const code = String(value ?? '');
  if (!code) return <>—</>;
  if (t.has(code)) return <>{t(code)}</>;
  if (code.startsWith('pending')) return <>{t('pending')}</>;
  return <>{code}</>;
}
