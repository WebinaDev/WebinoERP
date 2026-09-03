import type { ReactNode } from 'react';
import { SiteFooter } from '@/themes/webina-corporate-v1/components/SiteFooter';
import { SiteHeader } from '@/themes/webina-corporate-v1/components/SiteHeader';
import { getPublicSite } from '@/lib/public-api-server';

export const revalidate = 60;

export default async function SiteLayout({
  children,
}: {
  children: ReactNode;
}) {
  let siteName = 'Webina';
  let logoUrl: string | null = null;
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
