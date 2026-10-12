import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { siteHref } from '@/lib/public-api-server';
import {
  footerDefaults,
  isExternalHref,
  mergeContent,
  normalizeCompany,
  normalizeContact,
  normalizeSocials,
  type FooterData,
} from '../site-content';
import { LogoLockup } from './LogoLockup';
import { TrustBadgeRail, type TrustBadge } from './TrustBadgeRail';

type BadgeInput = {
  id?: string;
  label?: string;
  hint?: string;
  href?: string | null;
  image?: string | null;
};

function go(path: string) {
  return isExternalHref(path) ? path : siteHref(undefined, path.replace(/^\//, ''));
}

function SmartA({ to, children, className, dir }: { to: string; children: React.ReactNode; className?: string; dir?: string }) {
  if (isExternalHref(to)) {
    return (
      <a href={to} className={className} dir={dir} target={to.startsWith('http') ? '_blank' : undefined} rel="noreferrer">
        {children}
      </a>
    );
  }
  return (
    <Link href={go(to)} className={className} dir={dir}>
      {children}
    </Link>
  );
}

/**
 * Footer content is CMS-driven: `branding.footer`, `branding.contact`, `social_links` and
 * `branding.trust_badges` (page builder → پابرگ / تماس و شبکه‌ها / نشان‌های اعتماد).
 */
export async function SiteFooter({
  siteName,
  logoUrl,
  badges,
  socials,
  contact,
  footer,
  company,
}: {
  siteName: string;
  logoUrl?: string | null;
  badges?: BadgeInput[] | null;
  socials?: unknown;
  contact?: unknown;
  footer?: unknown;
  company?: unknown;
}) {
  const t = await getTranslations();
  const locale = await getLocale();
  const fa = locale === 'fa';
  const data: FooterData = fa ? mergeContent(footerDefaults(t), footer) : footerDefaults(t);
  const links = normalizeSocials(socials);
  const reach = normalizeContact(contact);
  const firm = fa ? normalizeCompany(company) : null;
  const year = new Intl.DateTimeFormat(fa ? 'fa-IR-u-ca-persian' : 'en-US', { year: 'numeric' }).format(new Date());
  const fallback: TrustBadge[] = [
    { id: 'enamad', label: t('site.footer.badgeEnamad'), hint: t('site.footer.badgeEnamadHint') },
    { id: 'samandehi', label: t('site.footer.badgeSamandehi'), hint: t('site.footer.badgeSamandehiHint') },
    { id: 'ssl', label: t('site.footer.badgeSsl'), hint: t('site.footer.badgeSslHint') },
    { id: 'hosting', label: t('site.footer.badgeHosting'), hint: t('site.footer.badgeHostingHint') },
  ];
  const rail: TrustBadge[] = badges?.length
    ? badges.map((b, i) => ({
        id: b.id || `badge-${i}`,
        label: b.label || fallback[i]?.label || data.trust_title,
        hint: b.hint || fallback[i]?.hint || data.trust_lead,
        href: b.href,
        image: b.image,
      }))
    : fallback;

  return (
    <footer className="site-footer">
      <div className="webina-shell footer-grid v2">
        <div>
          <LogoLockup siteName={siteName} logoUrl={logoUrl} compact tagline={data.tagline} />
          {data.description ? <p className="mt-5 max-w-sm text-sm leading-7">{data.description}</p> : null}
          {data.cta_label && data.cta_href ? (
            <SmartA to={data.cta_href} className="btn-primary mt-6">
              {data.cta_label}
            </SmartA>
          ) : null}
        </div>
        <div>
          <h2>{data.quick_title}</h2>
          <ul>
            {data.quick_links.map((link, i) => (
              <li key={`${link.href}-${i}`}>
                <SmartA to={link.href}>{link.label}</SmartA>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2>{data.contact_title}</h2>
          <ul>
            {reach?.items.map((item, i) => (
              <li key={`${item.value}-${i}`} className="footer-contact">
                {item.label ? <span className="footer-contact-label">{item.label}</span> : null}
                {item.href ? (
                  <SmartA to={item.href} dir="ltr">
                    {item.value}
                  </SmartA>
                ) : (
                  <span dir="ltr">{item.value}</span>
                )}
              </li>
            ))}
            {reach?.email ? (
              <li>
                <a href={`mailto:${reach.email}`} dir="ltr">
                  {reach.email}
                </a>
              </li>
            ) : null}
            {reach?.address ? <li>{reach.address}</li> : null}
            {data.contact_page_label && data.contact_page_href ? (
              <li>
                <SmartA to={data.contact_page_href} className="font-bold text-[var(--brand-primary)]">
                  {data.contact_page_label}
                </SmartA>
              </li>
            ) : null}
          </ul>
        </div>
        <div>
          <h2>{data.social_title}</h2>
          {links.length ? (
            <ul className="footer-social">
              {links.map((s) => (
                <li key={s.href}>
                  <a href={s.href} target="_blank" rel="noreferrer">
                    <span>{s.label}</span>
                    {s.handle ? <small dir="ltr">{s.handle}</small> : null}
                  </a>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-3 text-sm leading-7 text-[var(--muted)]">{data.social_empty}</p>
          )}
          {data.legal_links.length ? (
            <ul className="mt-6">
              {data.legal_links.map((l, i) => (
                <li key={`${l.href}-${i}`}>
                  <SmartA to={l.href}>{l.label}</SmartA>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      </div>
      <TrustBadgeRail
        badges={rail}
        title={data.trust_title}
        lead={data.trust_lead}
        prevLabel={t('site.footer.trustPrev')}
        nextLabel={t('site.footer.trustNext')}
      />
      {firm?.show_in_footer ? (
        <div className="webina-shell footer-legal">
          <strong>
            {firm.name}
            {firm.legal_type ? ` (${firm.legal_type})` : ''}
          </strong>
          {firm.registration_no ? <span>شماره ثبت: {firm.registration_no}</span> : null}
          {firm.national_id ? <span>شناسه ملی: {firm.national_id}</span> : null}
          {firm.address ? <span className="footer-legal-address">{firm.address}</span> : null}
        </div>
      ) : null}
      <div className="webina-shell footer-base">
        <span>{data.copyright.replace('{year}', year).replace('{site}', siteName)}</span>
        {data.legal_name ? <span>{data.legal_name}</span> : null}
      </div>
    </footer>
  );
}
