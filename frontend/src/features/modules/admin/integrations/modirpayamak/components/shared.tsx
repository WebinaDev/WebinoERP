'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { ERP_SUBMODULES } from '@/lib/module-registry';
import { dashboardHref } from '@/lib/route-resolver';

export function ModirPayamakBreadcrumb({ current }: { current?: string }) {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const hub = dashboardHref(locale, 'admin/integrations/modirpayamak');

  return (
    <nav className="text-sm text-muted-foreground">
      <Link href={hub} className="hover:text-foreground">
        {t('nav.erp.sales.modirpayamak')}
      </Link>
      {current ? (
        <>
          <span className="mx-2">/</span>
          <span className="text-foreground">{current}</span>
        </>
      ) : null}
    </nav>
  );
}

export function ModirPayamakQuickLinks() {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const sub = ERP_SUBMODULES.find((s) => s.id === 'modirpayamak');
  const children = sub?.menu.children ?? [];

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {children.map((item) => (
        <Link
          key={item.id}
          href={dashboardHref(locale, item.path)}
          className="rounded-lg border p-4 transition-colors hover:bg-muted/50"
        >
          <p className="font-medium">{t(item.titleKey)}</p>
        </Link>
      ))}
    </div>
  );
}

export function ModirPayamakNotConfigured() {
  const t = useTranslations('modirpayamak');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';

  return (
    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
      {t('notConfigured')}{' '}
      <Link href={dashboardHref(locale, 'admin/integrations/modirpayamak/settings')} className="underline">
        {t('configureCta')}
      </Link>
    </div>
  );
}
