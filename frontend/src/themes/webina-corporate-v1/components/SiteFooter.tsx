import Link from 'next/link';
import { siteHref } from '@/src/lib/api-server';

const LEGAL = [
  { slug: 'terms', label: 'قوانین و مقررات' },
  { slug: 'privacy', label: 'حریم خصوصی' },
  { slug: 'conflict-of-interest', label: 'تضاد منافع' },
];

export function SiteFooter({ siteName }: { siteName: string }) {
  return (
    <footer className="border-t border-white/10 bg-[#0a0a0a] text-white/80">
      <div className="container mx-auto grid gap-8 px-4 py-12 md:grid-cols-3">
        <div>
          <p className="text-lg font-semibold text-white">{siteName}</p>
          <p className="mt-2 text-sm">شریک دیجیتال کسب‌وکارها — طراحی، توسعه و بازاریابی آنلاین</p>
        </div>
        <div>
          <p className="font-medium text-white">دسترسی سریع</p>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link href={siteHref(undefined, 'services')} className="hover:text-[#0066FF]">خدمات</Link></li>
            <li><Link href={siteHref(undefined, 'solutions')} className="hover:text-[#0066FF]">راهکارها</Link></li>
            <li><Link href={siteHref(undefined, 'blog')} className="hover:text-[#0066FF]">بلاگ</Link></li>
            <li><Link href={siteHref(undefined, 'consultation')} className="hover:text-[#0066FF]">مشاوره</Link></li>
          </ul>
        </div>
        <div>
          <p className="font-medium text-white">قوانین</p>
          <ul className="mt-3 space-y-2 text-sm">
            {LEGAL.map((l) => (
              <li key={l.slug}>
                <Link href={siteHref(undefined, `pages/${l.slug}`)} className="hover:text-[#0066FF]">
                  {l.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div className="border-t border-white/10 py-4 text-center text-xs text-white/50">
        © {new Date().getFullYear()} {siteName}. تمامی حقوق محفوظ است.
      </div>
    </footer>
  );
}
