import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { ThemeSlot } from '@/builder/theme/ThemeSlot';
import { siteHref } from '@/lib/public-api-server';

export default async function SiteNotFound() {
  const t = await getTranslations();
  return (
    <ThemeSlot kind="not_found" context={{ notFound: true, path: '/404' }}>
      <div className="webina-shell py-24 text-center">
        <p className="text-[clamp(5rem,14vw,10rem)] font-extralight leading-none text-[var(--brand-primary)]">{t('site.notFound.code')}</p>
        <h1 className="mt-6 text-3xl font-extrabold tracking-tight md:text-4xl">{t('site.notFound.title')}</h1>
        <p className="mx-auto mt-4 max-w-lg text-[var(--muted)]">{t('site.notFound.lead')}</p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <Link href={siteHref(undefined, '')} className="btn-primary">{t('site.notFound.home')}</Link>
          <Link href={siteHref(undefined, 'services')} className="btn-ghost">{t('site.notFound.services')}</Link>
          <Link href={siteHref(undefined, 'portfolio')} className="btn-ghost">{t('site.nav.portfolio')}</Link>
        </div>
      </div>
    </ThemeSlot>
  );
}
