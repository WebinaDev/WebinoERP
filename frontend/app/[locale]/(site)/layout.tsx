import type { ReactNode } from 'react';
import { SiteFooter } from '@/src/themes/webina-corporate-v1/components/SiteFooter';
import { SiteHeader } from '@/src/themes/webina-corporate-v1/components/SiteHeader';
import { getPublicSite } from '@/src/lib/api-server';

export default async function SiteLayout({
  children,
  params: { locale },
}: {
  children: ReactNode;
  params: { locale: string };
}) {
  let siteName = 'وبینا';
  let logoUrl: string | null = '/brand/logo.png';
  try {
    const res = await getPublicSite();
    siteName = res.data.name ?? siteName;
    logoUrl = res.data.logo_url ?? logoUrl;
  } catch {
    /* fallback */
  }

  return (
    <div className="flex min-h-svh flex-col bg-background text-foreground [--brand-primary:#0066FF]">
      <SiteHeader siteName={siteName} logoUrl={logoUrl} />
      <main className="flex-1">{children}</main>
      <SiteFooter siteName={siteName} />
    </div>
  );
}
