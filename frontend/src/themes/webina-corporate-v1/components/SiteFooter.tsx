import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { siteHref } from '@/lib/public-api-server';

const LEGAL = [
  { slug: 'terms', labelKey: 'site.footer.terms' },
  { slug: 'privacy', labelKey: 'site.footer.privacy' },
  { slug: 'conflict-of-interest', labelKey: 'site.footer.conflict' },
];

export async function SiteFooter({ siteName }: { siteName: string }) {
  const t = await getTranslations();
  return (
    <footer className="border-t border-white/10 bg-[#0a0a0a] text-white/80">
      <div className="container mx-auto grid gap-8 px-4 py-12 md:grid-cols-3">
        <div>
          <p className="text-lg font-semibold text-white">{siteName}</p>
          <p className="mt-2 text-sm">{t('site.footer.tagline')}</p>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.footer.quickAccess')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link href={siteHref(undefined, 'services')} className="hover:text-[#0066FF]">{t('site.nav.services')}</Link></li>
            <li><Link href={siteHref(undefined, 'solutions')} className="hover:text-[#0066FF]">{t('site.nav.solutions')}</Link></li>
            <li><Link href={siteHref(undefined, 'blog')} className="hover:text-[#0066FF]">{t('site.nav.blog')}</Link></li>
            <li><Link href={siteHref(undefined, 'consultation')} className="hover:text-[#0066FF]">{t('site.nav.consultation')}</Link></li>
          </ul>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.footer.legal')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            {LEGAL.map((l) => (
              <li key={l.slug}>
                <Link href={siteHref(undefined, `pages/${l.slug}`)} className="hover:text-[#0066FF]">
                  {t(l.labelKey)}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div className="border-t border-white/10 py-4 text-center text-xs text-white/50">
        © {new Date().getFullYear()} {siteName}. {t('site.footer.rights')}
      </div>
    </footer>
  );
}
