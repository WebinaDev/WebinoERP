import Link from 'next/link';
import { ThemeSlot } from '@/builder/theme/ThemeSlot';
import { siteHref } from '@/lib/public-api-server';

export default function SiteNotFound() {
  return (
    <ThemeSlot kind="not_found" context={{ notFound: true, path: '/404' }}>
      <div className="bg-[#07070a] px-4 py-24 text-center text-white">
        <p className="text-sm tracking-[0.25em] text-[#9cc4ff]">404</p>
        <h1 className="mt-3 text-4xl font-black">این مسیر در نقشه وبینا نیست</h1>
        <p className="mx-auto mt-4 max-w-lg text-white/60">از خانه، خدمات یا نمونه‌کارها ادامه دهید.</p>
        <div className="mt-8 flex justify-center gap-3">
          <Link href={siteHref(undefined, '')} className="rounded-full bg-[#0066FF] px-5 py-2 text-sm">خانه</Link>
          <Link href={siteHref(undefined, 'services')} className="rounded-full border border-white/15 px-5 py-2 text-sm">خدمات</Link>
        </div>
      </div>
    </ThemeSlot>
  );
}
