import Link from 'next/link';
import { ThemeSlot } from '@/builder/theme/ThemeSlot';
import { getPublicSite } from '@/lib/public-api-server';
import { SiteFooter } from '@/themes/webina-corporate-v1/components/SiteFooter';
import { SiteHeader } from '@/themes/webina-corporate-v1/components/SiteHeader';

export default async function NotFound() {
  let siteName = 'Webina';
  let logoUrl: string | null = null;
  try {
    const res = await getPublicSite();
    siteName = res.data.name ?? siteName;
    logoUrl = res.data.logo_url ?? logoUrl;
  } catch {
    /* corporate chrome still renders */
  }

  return (
    <div className="flex min-h-svh flex-col bg-[#07070a] text-white">
      <ThemeSlot kind="header" context={{ notFound: true, path: '/404' }}>
        <SiteHeader siteName={siteName} logoUrl={logoUrl} />
      </ThemeSlot>
      <main className="flex-1">
        <ThemeSlot kind="not_found" context={{ notFound: true, path: '/404' }}>
          <div className="px-4 py-24 text-center text-white">
            <p className="text-sm tracking-[0.25em] text-[#9cc4ff]">404</p>
            <h1 className="mt-3 text-4xl font-black">این مسیر در نقشه وبینا نیست</h1>
            <p className="mx-auto mt-4 max-w-lg text-white/60">از خانه، خدمات یا نمونه‌کارها ادامه دهید.</p>
            <div className="mt-8 flex justify-center gap-3 text-sm">
              <Link href="/" className="rounded-full bg-[#0066FF] px-5 py-2">خانه</Link>
              <Link href="/services" className="rounded-full border border-white/15 px-5 py-2">خدمات</Link>
              <Link href="/portfolio" className="rounded-full border border-white/15 px-5 py-2">نمونه‌کارها</Link>
            </div>
          </div>
        </ThemeSlot>
      </main>
      <ThemeSlot kind="footer" context={{ notFound: true, path: '/404' }}>
        <SiteFooter siteName={siteName} />
      </ThemeSlot>
    </div>
  );
}
