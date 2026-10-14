import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { ThemeSlot } from '@/builder/theme/ThemeSlot';
import { SiteFooter } from '@/themes/webina-corporate-v1/components/SiteFooter';
import { SiteHeader } from '@/themes/webina-corporate-v1/components/SiteHeader';
import { getPublicSite } from '@/lib/public-api-server';
import { JsonLd, siteOrigin } from '@/lib/seo';
import '@/themes/webina-corporate-v1/components/public-site.css';

export const revalidate = 60;

export async function generateMetadata(): Promise<Metadata> {
  return {
    metadataBase: new URL(await siteOrigin()),
    title: {
      default: 'وبینا',
      template: '%s | وبینا',
    },
    description: 'شرکت توسعه کسب و کار وبینا. خدمات دیجیتال و محصول‌های Dashboard، ERP، مستندات و سکوی میزبانی.',
  };
}

type TrustBadge = {
  id?: string;
  label?: string;
  hint?: string;
  href?: string | null;
  image?: string | null;
};

export default async function SiteLayout({ children }: { children: ReactNode }) {
  let siteName = 'وبینا';
  let logoUrl: string | null = null;
  let badges: TrustBadge[] | null = null;
  let socials: unknown = null;
  let contact: unknown = null;
  let header: unknown = null;
  let footer: unknown = null;
  let company: unknown = null;
  try {
    const res = await getPublicSite();
    siteName = res.data.name ?? siteName;
    logoUrl = res.data.logo_url ?? logoUrl;
    const branding = res.data.branding;
    if (branding && Array.isArray(branding.trust_badges)) {
      badges = branding.trust_badges as TrustBadge[];
    }
    socials = res.data.social_links ?? null;
    contact = branding?.contact ?? null;
    header = branding?.header ?? null;
    footer = branding?.footer ?? null;
    company = branding?.company ?? null;
  } catch {
    /* fallback chrome still renders */
  }

  const origin = await siteOrigin();
  const sameAs = Array.isArray(socials) ? (socials as { url?: string; href?: string }[]).map((x) => x?.url || x?.href).filter((u): u is string => typeof u === 'string' && u.startsWith('http')) : [];
  const org = {
    '@context': 'https://schema.org', '@type': 'Organization', name: siteName, url: origin,
    ...(logoUrl ? { logo: logoUrl.startsWith('http') ? logoUrl : `${origin}${logoUrl}` } : {}),
    ...(sameAs.length ? { sameAs } : {}),
  };
  return (
    <div className="webina-public flex min-h-svh flex-col">
      <JsonLd data={[org, { '@context': 'https://schema.org', '@type': 'WebSite', name: siteName, url: origin, inLanguage: 'fa-IR' }]} />
      <ThemeSlot kind="header">
        <SiteHeader siteName={siteName} logoUrl={logoUrl} header={header} />
      </ThemeSlot>
      <main className="flex-1">{children}</main>
      <ThemeSlot kind="footer">
        <SiteFooter siteName={siteName} logoUrl={logoUrl} badges={badges} socials={socials} contact={contact} footer={footer} company={company} />
      </ThemeSlot>
    </div>
  );
}
