import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { siteHref } from '@/lib/public-api-server';
import { SERVICE_MEGA, SOLUTION_MEGA } from '../site-nav';

const LEGAL = [
  { slug: 'terms', labelKey: 'site.footer.terms' },
  { slug: 'privacy', labelKey: 'site.footer.privacy' },
  { slug: 'conflict', labelKey: 'site.footer.conflict' },
];

export async function SiteFooter({ siteName }: { siteName: string }) {
  const t = await getTranslations();
  return (
    <footer className="border-t border-white/10 bg-[#050508] text-white/75">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:grid-cols-2 lg:grid-cols-6 lg:px-6">
        <div className="lg:col-span-2">
          <p className="text-xl font-semibold text-white">{siteName}</p>
          <p className="mt-3 max-w-sm text-sm leading-7">{t('site.footer.tagline')}</p>
          <Link
            href={siteHref(undefined, 'consultation')}
            className="mt-5 inline-flex rounded-full bg-[#0066FF] px-4 py-2 text-sm font-medium text-white hover:bg-[#0052cc]"
          >
            {t('site.nav.freeConsultation')}
          </Link>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.nav.services')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            {SERVICE_MEGA.columns.map((c) => (
              <li key={c.titleKey}>
                <Link href={siteHref(undefined, c.href || 'services')} className="hover:text-[#6ea8ff]">
                  {t(c.titleKey)}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.nav.solutions')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            {SOLUTION_MEGA.columns.map((c) => (
              <li key={c.titleKey}>
                <Link href={siteHref(undefined, c.href || 'solutions')} className="hover:text-[#6ea8ff]">
                  {t(c.titleKey)}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.nav.resources')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link href={siteHref(undefined, 'blog')} className="hover:text-[#6ea8ff]">{t('site.nav.blog')}</Link></li>
            <li><Link href={siteHref(undefined, 'academy')} className="hover:text-[#6ea8ff]">{t('site.nav.academy')}</Link></li>
            <li><Link href={siteHref(undefined, 'downloads')} className="hover:text-[#6ea8ff]">{t('site.nav.downloads')}</Link></li>
            <li><Link href={siteHref(undefined, 'faq')} className="hover:text-[#6ea8ff]">{t('site.nav.faq')}</Link></li>
            <li><Link href={siteHref(undefined, 'portfolio')} className="hover:text-[#6ea8ff]">{t('site.nav.portfolio')}</Link></li>
          </ul>
        </div>
        <div>
          <p className="font-medium text-white">{t('site.footer.legal')}</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link href={siteHref(undefined, 'about')} className="hover:text-[#6ea8ff]">{t('site.nav.about')}</Link></li>
            <li><Link href={siteHref(undefined, 'contact')} className="hover:text-[#6ea8ff]">{t('site.nav.contact')}</Link></li>
            <li><Link href={siteHref(undefined, 'cooperation')} className="hover:text-[#6ea8ff]">{t('site.nav.cooperation')}</Link></li>
            {LEGAL.map((l) => (
              <li key={l.slug}>
                <Link href={siteHref(undefined, `pages/${l.slug}`)} className="hover:text-[#6ea8ff]">
                  {t(l.labelKey)}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div className="border-t border-white/10 py-4 text-center text-xs text-white/40">
        © {new Date().getFullYear()} {siteName}. {t('site.footer.rights')}
      </div>
    </footer>
  );
}
