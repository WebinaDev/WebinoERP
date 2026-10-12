import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { siteHref } from '@/lib/public-api-server';
import { FOOTER_QUICK_LINKS } from '../home-content';
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

export type FooterSocial = { label: string; href: string };
export type FooterContact = { phone?: string | null; email?: string | null; address?: string | null };

/** Accepts the stored social_links shapes: [{label|name|platform, url|href}] or { platform: url }. */
export function normalizeSocials(raw: unknown): FooterSocial[] {
  if (Array.isArray(raw)) {
    return raw
      .map((row) => {
        if (!row || typeof row !== 'object') return null;
        const r = row as Record<string, unknown>;
        const href = String(r.url ?? r.href ?? '');
        const label = String(r.label ?? r.name ?? r.platform ?? href);
        return href ? { label, href } : null;
      })
      .filter(Boolean) as FooterSocial[];
  }
  if (raw && typeof raw === 'object') {
    return Object.entries(raw as Record<string, unknown>)
      .filter(([, v]) => typeof v === 'string' && v)
      .map(([k, v]) => ({ label: k, href: String(v) }));
  }
  return [];
}

export async function SiteFooter({
  siteName,
  logoUrl,
  badges,
  socials,
  contact,
}: {
  siteName: string;
  logoUrl?: string | null;
  badges?: BadgeInput[] | null;
  socials?: FooterSocial[] | null;
  contact?: FooterContact | null;
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
  const hasContact = Boolean(contact?.phone || contact?.email || contact?.address);

  return (
    <footer className="site-footer">
      <div className="webina-shell footer-grid v2">
        <div>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} compact tagline={t('site.footer.agencyTagline')} />
          <p className="mt-5 max-w-sm text-sm leading-7">{t('site.footer.tagline')}</p>
          <Link href={siteHref(undefined, 'consultation')} className="btn-primary mt-6">
            {t('site.v2.heroCta1')}
          </Link>
        </div>
        <div>
          <h2>{t('site.v2.footerQuick')}</h2>
          <ul>
            {FOOTER_QUICK_LINKS.map((link) => (
              <li key={link.href}>
                <Link href={siteHref(undefined, link.href)}>{t(`site.v2.quick.${link.key}`)}</Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2>{t('site.v2.footerContact')}</h2>
          <ul>
            {contact?.phone ? (
              <li>
                <a href={`tel:${contact.phone}`} dir="ltr">{contact.phone}</a>
              </li>
            ) : null}
            {contact?.email ? (
              <li>
                <a href={`mailto:${contact.email}`} dir="ltr">{contact.email}</a>
              </li>
            ) : null}
            {contact?.address ? <li>{contact.address}</li> : null}
            <li>
              <Link href={siteHref(undefined, 'contact')} className={hasContact ? '' : 'font-bold text-[var(--brand-primary)]'}>
                {t('site.v2.footerContactPage')}
              </Link>
            </li>
            {LEGAL.map((l) => (
              <li key={l.slug}>
                <Link href={siteHref(undefined, `pages/${l.slug}`)}>{t(l.labelKey)}</Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2>{t('site.v2.footerSocial')}</h2>
          {socials?.length ? (
            <div className="footer-social">
              {socials.map((s) => (
                <a key={s.href} href={s.href} target="_blank" rel="noreferrer">
                  {s.label}
                </a>
              ))}
            </div>
          ) : (
            <p className="mt-3 text-sm leading-7 text-[var(--muted)]">{t('site.v2.footerSocialEmpty')}</p>
          )}
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
