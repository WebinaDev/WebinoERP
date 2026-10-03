import type { Metadata } from 'next';
import type { ReactNode } from 'react';
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
  try {
    const res = await getPublicSite();
    siteName = res.data.name ?? siteName;
    logoUrl = res.data.logo_url ?? logoUrl;
    const branding = res.data.branding;
    if (branding && Array.isArray(branding.trust_badges)) {
      badges = branding.trust_badges as TrustBadge[];
    }
  } catch {
    /* fallback chrome still renders */
  }

  return (
    <div className="webina-public flex min-h-svh flex-col">
      <SiteHeader siteName={siteName} logoUrl={logoUrl} />
      <main className="flex-1">{children}</main>
      <SiteFooter siteName={siteName} logoUrl={logoUrl} badges={badges} />
    </div>
  );
}
