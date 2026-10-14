import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { headers } from 'next/headers';
import { requestPageLang } from '@/builder/page-lang';
import { loadPublishedGlobals } from '@/builder/public-document';
import { CustomCodeSlot } from '@/builder/render/custom-code';
import { ThemeSlot } from '@/builder/theme/ThemeSlot';
import { SiteFooter } from '@/themes/webina-corporate-v1/components/SiteFooter';
import { SiteHeader } from '@/themes/webina-corporate-v1/components/SiteHeader';
import { getPublicSite } from '@/lib/public-api-server';
import '@/themes/webina-corporate-v1/components/public-site.css';

export const revalidate = 60;

export const metadata: Metadata = {
  title: {
    default: 'وبینا',
    template: '%s | وبینا',
  },
  description: 'شرکت توسعه کسب و کار وبینا. خدمات دیجیتال و محصول‌های Dashboard، ERP، مستندات و سکوی میزبانی.',
};

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

  const requestPath = ((await headers()).get('x-webino-path') ?? '/').split('?')[0];
  const [globals, pageLang] = await Promise.all([loadPublishedGlobals().catch(() => null), requestPageLang().catch(() => null)]);

  return (
    <div className="webina-public flex min-h-svh flex-col" {...(pageLang ? { dir: pageLang.dir, lang: pageLang.lang } : {})}>
      <CustomCodeSlot entries={globals?.customCode} location="head" path={requestPath} />
      <ThemeSlot kind="header">
        <SiteHeader siteName={siteName} logoUrl={logoUrl} header={header} />
      </ThemeSlot>
      <main className="flex-1">{children}</main>
      <ThemeSlot kind="footer">
        <SiteFooter siteName={siteName} logoUrl={logoUrl} badges={badges} socials={socials} contact={contact} footer={footer} company={company} />
      </ThemeSlot>
      <CustomCodeSlot entries={globals?.customCode} location="body_end" path={requestPath} />
    </div>
  );
}
