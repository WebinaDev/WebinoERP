import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { siteHref } from '@/lib/public-api-server';
import { SERVICE_MEGA, SOLUTION_MEGA } from '../site-nav';
import { LogoLockup } from './LogoLockup';
import { TrustBadgeRail, type TrustBadge } from './TrustBadgeRail';

const LEGAL = [
  { slug: 'terms', labelKey: 'site.footer.terms' },
  { slug: 'privacy', labelKey: 'site.footer.privacy' },
  { slug: 'conflict-of-interest', labelKey: 'site.footer.conflict' },
];

type BadgeInput = {
  id?: string;
  label?: string;
  hint?: string;
  href?: string | null;
  image?: string | null;
};

export async function SiteFooter({
  siteName,
  logoUrl,
  badges,
}: {
  siteName: string;
  logoUrl?: string | null;
  badges?: BadgeInput[] | null;
}) {
  const t = await getTranslations();
  const locale = await getLocale();
  const year = new Intl.DateTimeFormat(locale === 'fa' ? 'fa-IR-u-ca-persian' : 'en-US', { year: 'numeric' }).format(new Date());
  const fallback: TrustBadge[] = [
    { id: 'enamad', label: t('site.footer.badgeEnamad'), hint: t('site.footer.badgeEnamadHint') },
    { id: 'samandehi', label: t('site.footer.badgeSamandehi'), hint: t('site.footer.badgeSamandehiHint') },
    { id: 'ssl', label: t('site.footer.badgeSsl'), hint: t('site.footer.badgeSslHint') },
    { id: 'hosting', label: t('site.footer.badgeHosting'), hint: t('site.footer.badgeHostingHint') },
  ];
  const rail: TrustBadge[] = badges?.length
    ? badges.map((b, i) => ({
        id: b.id || `badge-${i}`,
        label: b.label || fallback[i]?.label || t('site.footer.trustTitle'),
        hint: b.hint || fallback[i]?.hint || t('site.footer.trustLead'),
        href: b.href,
        image: b.image,
      }))
    : fallback;

  return (
    <footer className="site-footer">
      <div className="webina-shell footer-grid">
        <div>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} compact tagline={t('site.footer.agencyTagline')} />
          <p className="mt-5 max-w-sm text-sm leading-7">{t('site.footer.tagline')}</p>
          <p className="footer-legal mt-2 text-xs leading-6">{t('site.footer.legalName')}</p>
          <Link href={siteHref(undefined, 'consultation')} className="btn-primary mt-6">
            {t('site.nav.freeConsultation')}
          </Link>
        </div>
        <div>
          <h2>{t('site.nav.services')}</h2>
          <ul>
            {SERVICE_MEGA.columns.map((c) => (
              <li key={c.titleKey}>
                <Link href={siteHref(undefined, c.href || 'services')}>{t(c.titleKey)}</Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2>{t('site.nav.solutions')}</h2>
          <ul>
            {SOLUTION_MEGA.columns.map((c) => (
              <li key={c.titleKey}>
                <Link href={siteHref(undefined, c.href || 'solutions')}>{t(c.titleKey)}</Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2>{t('site.nav.products')}</h2>
          <ul>
            <li><Link href={siteHref(undefined, 'products#dashboard')}>{t('site.product.dashboardTitle')}</Link></li>
            <li><Link href={siteHref(undefined, 'products#erp')}>{t('site.product.erpTitle')}</Link></li>
            <li><Link href={siteHref(undefined, 'products#docs')}>{t('site.product.docsTitle')}</Link></li>
            <li><Link href={siteHref(undefined, 'products#hosting')}>{t('site.product.hostingTitle')}</Link></li>
            <li><Link href={siteHref(undefined, 'portfolio')}>{t('site.nav.portfolio')}</Link></li>
          </ul>
        </div>
        <div>
          <h2>{t('site.nav.company')}</h2>
          <ul>
            <li><Link href={siteHref(undefined, 'about')}>{t('site.nav.about')}</Link></li>
            <li><Link href={siteHref(undefined, 'team')}>{t('site.nav.team')}</Link></li>
            <li><Link href={siteHref(undefined, 'blog')}>{t('site.nav.blog')}</Link></li>
            <li><Link href={siteHref(undefined, 'academy')}>{t('site.nav.academy')}</Link></li>
            <li><Link href={siteHref(undefined, 'faq')}>{t('site.nav.faq')}</Link></li>
            <li><Link href={siteHref(undefined, 'proposal')}>{t('site.nav.proposal')}</Link></li>
            <li><Link href={siteHref(undefined, 'contact')}>{t('site.nav.contact')}</Link></li>
            {LEGAL.map((l) => (
              <li key={l.slug}>
                <Link href={siteHref(undefined, `pages/${l.slug}`)}>{t(l.labelKey)}</Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <TrustBadgeRail
        badges={rail}
        title={t('site.footer.trustTitle')}
        lead={t('site.footer.trustLead')}
        prevLabel={t('site.footer.trustPrev')}
        nextLabel={t('site.footer.trustNext')}
      />
      <div className="webina-shell footer-base">
        <span>
          © {year} {siteName}. {t('site.footer.rights')}
        </span>
        <span>{t('site.footer.legalName')}</span>
      </div>
    </footer>
  );
}
